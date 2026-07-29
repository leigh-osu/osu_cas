<?php

declare(strict_types=1);

namespace Drupal\Tests\node_revision_delete\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the admin settings form, node type overrides, and reset form.
 *
 * @covers \Drupal\node_revision_delete\Form\AdminSettingsForm
 * @covers \Drupal\node_revision_delete\Form\ResetNodeTypeForm
 */
#[Group('node_revision_delete')]
#[RunTestsInSeparateProcesses]
class AdminSettingsFormTest extends BrowserTestBase {

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
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
  }

  /**
   * Tests saving default plugin settings via the admin form.
   */
  public function testAdminSettingsForm(): void {
    $user = $this->drupalCreateUser(['administer node_revision_delete']);
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/content/node_revision_delete');

    // Verify content types are listed with default status.
    $this->assertSession()->pageTextContains('Basic page');
    $this->assertSession()->pageTextContains('Article');
    $this->assertNodeTypeStatus('page', 'Default');
    $this->assertNodeTypeStatus('article', 'Default');

    // Save default settings: enable the Amount plugin with 5 revisions.
    $this->submitForm([
      'amount[status]' => TRUE,
      'amount[settings][amount]' => 5,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify config was saved.
    $config = $this->config('node_revision_delete.settings')->get('defaults');
    $this->assertTrue($config['amount']['status']);
    $this->assertEquals(5, $config['amount']['settings']['amount']);
  }

  /**
   * Tests overriding settings per node type and the reset confirm form.
   */
  public function testNodeTypeOverrideAndReset(): void {
    $user = $this->drupalCreateUser([
      'administer node_revision_delete',
      'administer content types',
    ]);
    $this->drupalLogin($user);

    // Set defaults first.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'amount' => [
          'status' => TRUE,
          'settings' => ['amount' => 5],
        ],
      ])
      ->save();

    // Verify admin page shows both types as Default.
    $this->drupalGet('admin/config/content/node_revision_delete');
    $this->assertNodeTypeStatus('page', 'Default');
    $this->assertNodeTypeStatus('article', 'Default');
    $this->assertSession()->linkNotExists('Reset to defaults');

    // Override settings for the page content type via the node type form.
    $this->drupalGet('admin/structure/types/manage/page');
    $this->assertSession()->fieldDisabled('amount[status]');
    $this->submitForm([
      'node_revision_delete[override]' => TRUE,
    ], 'Save');

    $this->drupalGet('admin/structure/types/manage/page');
    $this->assertSession()->fieldEnabled('amount[status]');
    $this->submitForm([
      'amount[status]' => TRUE,
      'amount[settings][amount]' => 10,
    ], 'Save');

    // Verify the override is stored as a third-party setting.
    $node_type = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->load('page');
    $third_party = $node_type->getThirdPartySettings('node_revision_delete');
    $this->assertNotEmpty($third_party);
    $this->assertEquals(10, $third_party['amount']['settings']['amount']);

    // Admin page should now show page as Overridden with a reset link.
    $this->drupalGet('admin/config/content/node_revision_delete');
    $this->assertNodeTypeStatus('page', 'Overridden');
    $this->assertNodeTypeStatus('article', 'Default');
    $this->assertSession()->linkExists('Reset to defaults');

    // Visit the reset confirm form.
    $this->drupalGet('admin/config/content/node_revision_delete/reset/page');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Are you sure you want to reset the revision delete settings for Basic page to defaults?');

    // Confirm the reset.
    $this->submitForm([], 'Confirm');
    $this->assertSession()->pageTextContains('The revision delete settings for Basic page have been reset to defaults.');

    // Verify the override was removed.
    $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->resetCache(['page']);
    $node_type = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->load('page');
    $this->assertEmpty($node_type->getThirdPartySettings('node_revision_delete'));

    // Admin page should show all types as Default again.
    $this->assertNodeTypeStatus('page', 'Default');
    $this->assertNodeTypeStatus('article', 'Default');
    $this->assertSession()->linkNotExists('Reset to defaults');
  }

  /**
   * Tests the reset form returns 404 for a non-existent node type.
   */
  public function testResetNonExistentNodeType(): void {
    $user = $this->drupalCreateUser(['administer node_revision_delete']);
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/content/node_revision_delete/reset/nonexistent');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests that unchecking the override checkbox removes the override.
   */
  public function testOverrideRemovedByUncheckingOverride(): void {
    $user = $this->drupalCreateUser([
      'administer node_revision_delete',
      'administer content types',
    ]);
    $this->drupalLogin($user);

    // Set defaults.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'amount' => [
          'status' => TRUE,
          'settings' => ['amount' => 5],
        ],
      ])
      ->save();

    // Create an override.
    $this->drupalGet('admin/structure/types/manage/page');
    $this->submitForm([
      'node_revision_delete[override]' => TRUE,
      'amount[status]' => TRUE,
      'amount[settings][amount]' => 10,
    ], 'Save');

    // Verify override exists.
    $this->drupalGet('admin/config/content/node_revision_delete');
    $this->assertNodeTypeStatus('page', 'Overridden');

    // Uncheck the override checkbox to remove the override.
    $this->drupalGet('admin/structure/types/manage/page');
    $this->submitForm([
      'node_revision_delete[override]' => FALSE,
    ], 'Save');

    // Override should be removed.
    $this->drupalGet('admin/config/content/node_revision_delete');
    $this->assertNodeTypeStatus('page', 'Default');
  }

  /**
   * Asserts the settings status displayed for a node type in the table.
   *
   * @param string $node_type_id
   *   The node type machine name.
   * @param string $expected_status
   *   The expected status text (e.g. "Default" or "Overridden").
   */
  private function assertNodeTypeStatus(string $node_type_id, string $expected_status): void {
    $row = $this->assertSession()->elementExists('xpath', '//table//tr[td[text()="' . $node_type_id . '"]]');
    $cells = $row->findAll('css', 'td');
    $this->assertSame($expected_status, $cells[2]->getText());
  }

}
