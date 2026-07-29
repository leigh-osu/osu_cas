<?php

declare(strict_types=1);

namespace Drupal\Tests\node_revision_delete\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the override #states behavior and vertical tabs summary.
 */
#[Group('node_revision_delete')]
#[RunTestsInSeparateProcesses]
class NodeTypeOverrideTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'node_revision_delete',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    $user = $this->drupalCreateUser([
      'administer node_revision_delete',
      'administer content types',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests #states behavior and vertical tabs summary on the node type form.
   */
  public function testOverrideStatesAndSummary(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/structure/types/manage/page');

    // Click on the vertical tab to ensure the summary element is rendered.
    $tab = $assert_session->waitForElement('css', 'a[href="#edit-node-revision-delete"]');
    $this->assertNotNull($tab, 'Node Revision Delete vertical tab is present.');
    $tab->click();

    $override = $page->findField('node_revision_delete[override]');
    $this->assertNotNull($override, 'Override checkbox is present.');
    $this->assertFalse($override->isChecked(), 'Override is unchecked by default.');

    $status = $page->findField('amount[status]');
    $this->assertNotNull($status, 'Amount status checkbox is present.');
    $amount_field = $page->findField('amount[settings][amount]');
    $this->assertNotNull($amount_field, 'Amount setting field is present.');

    // Initially the override is unchecked, the status checkbox should be
    // disabled by #states.
    $this->assertTrue(
      $status->hasAttribute('disabled'),
      'Status checkbox is disabled when override is unchecked.'
    );
    // The amount field is required and should also be disabled.
    $this->assertTrue(
      $amount_field->hasAttribute('disabled'),
      'Amount field is disabled when override is unchecked.'
    );

    // Verify the initial summary text.
    $this->assertVerticalTabSummary('Using defaults, revision deletion is disabled');

    $assert_session->fieldValueEquals('amount[settings][amount]', '0');

    // Save without overriding anything.
    $this->submitForm([], 'Save');

    $assert_session->pageTextContains('The content type Basic page has been updated.');
    $assert_session->pageTextNotContains('field is required');

    // No third-party settings should have been stored.
    $node_type = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->load('page');
    $this->assertEmpty($node_type->getThirdPartySettings('node_revision_delete'));

    $this->drupalGet('admin/structure/types/manage/page');

    // Click on the vertical tab to ensure the summary element is rendered.
    $tab = $assert_session->waitForElement('css', 'a[href="#edit-node-revision-delete"]');
    $this->assertNotNull($tab, 'Node Revision Delete vertical tab is present.');
    $tab->click();

    // Check the override checkbox; this triggers an AJAX rebuild.
    $override->check();
    $assert_session->assertWaitOnAjaxRequest();

    // Re-fetch fields after the AJAX rebuild, since the old element handles
    // may be stale.
    $status = $page->findField('amount[status]');
    $amount_field = $page->findField('amount[settings][amount]');

    // The status checkbox should now be enabled.
    $this->assertFalse(
      $status->hasAttribute('disabled'),
      'Status checkbox is enabled after override is checked.'
    );
    // The amount field should also be enabled.
    $this->assertFalse(
      $amount_field->hasAttribute('disabled'),
      'Amount field is enabled after override is checked.'
    );

    // Summary should reflect overridden state but no plugins enabled.
    $this->assertVerticalTabSummary('Overridden, revision deletion is disabled');

    // Enable the amount plugin.
    $status->check();
    $amount_field->setValue(20);

    // Summary should now report enabled.
    $this->assertVerticalTabSummary('Overridden, revision deletion is enabled');

    // Uncheck override; the AJAX rebuild should reset the plugin sub-forms
    // to fresh defaults, so the status checkbox is no longer checked and the
    // summary reflects the (disabled) defaults.
    $override = $page->findField('node_revision_delete[override]');
    $override->uncheck();
    $assert_session->assertWaitOnAjaxRequest();

    $status = $page->findField('amount[status]');
    $amount_field = $page->findField('amount[settings][amount]');

    $this->assertFalse(
      $status->isChecked(),
      'Status checkbox is reset to unchecked after override is unchecked.'
    );
    $this->assertTrue(
      $status->hasAttribute('disabled'),
      'Status checkbox is disabled again when override is unchecked.'
    );
    $this->assertTrue(
      $amount_field->hasAttribute('disabled'),
      'Amount field is disabled again when override is unchecked.'
    );

    $this->assertVerticalTabSummary('Using defaults, revision deletion is disabled');
    $this->assertFalse($page->findField('amount[settings][amount]')->isVisible(), 'Amount field is not visible.');
    $this->assertSession()->fieldValueEquals('amount[settings][amount]', '0');

    // Now test the form when there are defaults in place.
    $this->drupalGet('admin/config/content/node_revision_delete');
    $this->submitForm([
      'amount[status]' => TRUE,
      'amount[settings][amount]' => 10,
    ], 'Save');
    $this->assertSession()->waitForText('The configuration options have been saved.');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $this->clickLink('Configure');
    $this->htmlOutput();
    $this->assertSession()->checkboxNotChecked('node_revision_delete[override]');
    $this->assertSession()->checkboxChecked('amount[status]');
    $this->assertSession()->fieldValueEquals('amount[settings][amount]', '10');

    $this->assertVerticalTabSummary('Using defaults, revision deletion is enabled');

    // Check the override checkbox; this triggers an AJAX rebuild.
    $page->checkField('node_revision_delete[override]');
    $assert_session->assertWaitOnAjaxRequest();
    $page->uncheckField('amount[status]');
    $this->assertFalse($page->findField('amount[settings][amount]')->isVisible(), 'Amount field is not visible.');
    $this->assertVerticalTabSummary('Overridden, revision deletion is disabled');

    $page->uncheckField('node_revision_delete[override]');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertVerticalTabSummary('Using defaults, revision deletion is enabled');
    $this->assertSession()->checkboxChecked('amount[status]');
    $this->assertSession()->fieldValueEquals('amount[settings][amount]', '10');
    $amount_field = $page->findField('amount[settings][amount]');
    $this->assertTrue($amount_field->isVisible() && $amount_field->hasAttribute('disabled'), 'Amount field is visible and disabled.');
  }

  /**
   * Asserts the Node Revision Delete vertical tab summary text.
   *
   * @param string $expected
   *   The expected summary text.
   */
  private function assertVerticalTabSummary(string $expected): void {
    // Give the browser a moment to fire the summary callback before polling
    // for the expected text.
    usleep(100000);

    $selector = '#edit-node-revision-delete .vertical-tabs__menu-item-summary, [data-drupal-selector="edit-node-revision-delete"] .vertical-tabs__menu-item-summary, .vertical-tabs__menu a[href="#edit-node-revision-delete"] .vertical-tabs__menu-item-summary';
    $page = $this->getSession()->getPage();
    // Wait for the text.
    $page->waitFor(10, function () use ($page, $selector, $expected) {
      $element = $page->find('css', $selector);
      return $element !== NULL && trim($element->getText()) === $expected;
    });
    $this->assertSession()->elementTextContains('css', $selector, $expected);
  }

}
