<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests uninstall of Layout Builder when overrides present.
 *
 * @group layout_builder
 */
class LayoutBuilderUninstallTest extends BrowserTestBase {

  /**
   * Warning text that appears when Layout Builder cant be uninstalled.
   */
  const UNINSTALL_WARNING = 'The following reason prevents Layout Builder from being uninstalled';

  /**
   * Label for the button that removes overrides and disables Layout Builder.
   */
  const REVERT_ALL_LABEL = 'Disable Layout Builder for this display and revert all layout overrides';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'node',
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

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer modules',
    ]));

    // We need more then one content type for this test.
    $this->createContentType(['type' => 'first_bundle']);
    $this->createContentType(['type' => 'second_bundle']);
  }

  /**
   * Enables then creates a layout override in a bundle.
   *
   * @param string $bundle
   *   The node bundle.
   */
  protected function enableOverridesThenCreateNode($bundle) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet("admin/structure/types/manage/$bundle/display/default");
    $this->submitForm(['layout[enabled]' => TRUE], 'Save');
    $this->submitForm(['layout[allow_custom]' => TRUE], 'Save');

    $node = $this->createNode([
      'type' => $bundle,
      'body' => [
        [
          'value' => 'The node body',
        ],
      ],
    ]);
    $nid = $node->id();
    $this->drupalGet("node/$nid/layout");

    $assert_session->elementExists('css', '.layout-builder__message.layout-builder__message--overrides');
    $page->clickLink('Add block');
    $page->clickLink('Powered by Drupal');
    $page->fillField('settings[label]', 'This is an override');
    $page->checkField('settings[label_display]');
    $page->pressButton('Add block');
    $page->pressButton('Save layout');
    $assert_session->pageTextContains('This is an override');

    $overrides_for_bundle = $this->getOverridesForBundle($bundle);
    $this->assertCount(1, $overrides_for_bundle);
  }

  /**
   * Get all overrides belonging to a bundle.
   *
   * @param string $bundle
   *   The node bundle.
   */
  protected function getOverridesForBundle($bundle) {
    return \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->exists('layout_builder__layout')
      ->execute();
  }

  /**
   * Reverts layout overrides for a bundle.
   *
   * @param string $bundle
   *   The node bundle.
   * @param bool $last_bundle_with_overrides
   *   Set to TRUE if this is the only bundle with overrides.
   */
  protected function revertLayoutsForBundle($bundle, $last_bundle_with_overrides = FALSE) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet("admin/structure/types/manage/$bundle/display/default");
    $assert_session->fieldDisabled('layout[enabled]');
    $assert_session->fieldDisabled('layout[allow_custom]');
    $page->pressButton(static::REVERT_ALL_LABEL);
    $assert_session->addressEquals("admin/structure/types/manage/$bundle/display/default/layout/revert-all");
    $this->submitForm([], 'Confirm');
    $assert_session->addressEquals("admin/structure/types/manage/$bundle/display/default");
    $this->submitForm(['layout[allow_custom]' => FALSE], 'Save');
    $this->submitForm(['layout[enabled]' => FALSE], 'Save');
  }

  /**
   * Test.
   */
  public function testUninstallWithOverrides() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/modules/uninstall');
    $assert_session->pageTextNotContains(static::UNINSTALL_WARNING);

    foreach (['first_bundle', 'second_bundle'] as $bundle) {
      $this->enableOverridesThenCreateNode($bundle);
    }

    $this->drupalGet('admin/modules/uninstall');
    $assert_session->pageTextContains(static::UNINSTALL_WARNING);

    $this->revertLayoutsForBundle('first_bundle');
    $this->drupalGet('admin/modules/uninstall');
    $assert_session->pageTextContains(static::UNINSTALL_WARNING);

    $this->revertLayoutsForBundle('second_bundle', TRUE);
    $this->drupalGet('admin/modules/uninstall');
    $assert_session->pageTextNotContains(static::UNINSTALL_WARNING);

    $this->submitForm(['uninstall[layout_builder]' => TRUE], 'Uninstall');
    $assert_session->pageTextContains('first_bundle content items');
    $assert_session->pageTextContains('second_bundle content items');
    $assert_session->pageTextContains('Would you like to continue with uninstalling the above?');
    $page->pressButton('Uninstall');
    $assert_session->addressEquals('admin/modules/uninstall');
    $assert_session->pageTextContains('The selected modules have been uninstalled.');
    $assert_session->pageTextNotContains('Layout Builder');
  }

}
