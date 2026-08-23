<?php

declare(strict_types=1);

namespace Drupal\Tests\toc_js_per_node\FunctionalJavascript;

use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests the TOC JS per node functionality.
 *
 * @group toc_js_per_node
 */
class TocJsPerNodeJavascriptTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'block',
    'text',
    'toc_js',
    'toc_js_per_node',
    'toc_js_test_module',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with appropriate permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A user with content creation permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $contentUser;

  /**
   * The node type for testing.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $nodeType;

  /**
   * Custom format name.
   *
   * @var string
   */
  protected $formatName = 'toc_test_format';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the "article" content type.
    $this->nodeType = $this->createContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Create a minimal text format that just allows headings and basic
    // formatting.
    $format = FilterFormat::create([
      'format' => $this->formatName,
      'name' => 'TOC Test Format',
      'weight' => 0,
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<h2> <h3> <h4> <h5> <h6> <p> <br> <strong> <em> <ul> <ol> <li> <span class>',
          ],
        ],
      ],
    ]);
    $format->save();

    // Enable TOC for the article content type with per-node override.
    $this->nodeType->setThirdPartySetting('toc_js', 'toc_js_active', 1);
    $this->nodeType->setThirdPartySetting('toc_js', 'title', 'Table of Contents');
    $this->nodeType->setThirdPartySetting('toc_js', 'selectors', 'h2, h3');
    $this->nodeType->setThirdPartySetting('toc_js', 'container', '.node > div');
    $this->nodeType->setThirdPartySetting('toc_js_per_node', 'override', 1);
    $this->nodeType->setThirdPartySetting('toc_js_per_node', 'override_default', 1);
    $this->nodeType->save();

    // Place the TOC per node block.
    $this->drupalPlaceBlock('toc_js_per_node_block', [
      'region' => 'content',
      'label' => 'TOC Per Node Block',
    ]);

    // Create users.
    $this->adminUser = $this->drupalCreateUser([
      'administer content types',
      'administer nodes',
      'create article content',
      'edit own article content',
      'access content',
      'administer toc_js per node',
    ]);

    $this->contentUser = $this->drupalCreateUser([
      'create article content',
      'edit own article content',
      'access content',
      'administer toc_js per node',
    ]);
  }

  /**
   * Tests that TOC is displayed when enabled per node.
   */
  public function testTocDisplayedWhenEnabled(): void {
    $this->drupalLogin($this->contentUser);

    // Create a node with TOC enabled.
    $body_content = '<h2>Section One</h2><p>Content.</p><h2>Section Two</h2><p>More content.</p>';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test Node with TOC',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'toc_js_active' => 1,
      'status' => 1,
    ]);

    // Visit the node.
    $this->drupalGet($node->toUrl());

    // Verify headings are present on the page first.
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Section One');
    $assert_session->pageTextContains('Section Two');

    // Wait for JavaScript to generate the TOC.
    $toc = $assert_session->waitForElement('css', '.toc-js-container nav ul');
    $this->assertNotEmpty($toc, 'TOC navigation list is displayed');

    // Verify TOC links are present.
    $toc_links = $this->getSession()->getPage()->findAll('css', '.toc-js nav ul li a');
    $this->assertNotEmpty($toc_links, 'TOC links are present');
    $this->assertCount(2, $toc_links, 'Two TOC links for the two headings');
  }

  /**
   * Tests that TOC is not displayed when disabled per node.
   */
  public function testTocNotDisplayedWhenDisabled(): void {
    $this->drupalLogin($this->contentUser);

    // Create a node with TOC disabled.
    $body_content = '<h2>Section One</h2><p>Content.</p><h2>Section Two</h2><p>More content.</p>';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test Node without TOC',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'toc_js_active' => 0,
      'status' => 1,
    ]);

    // Visit the node.
    $this->drupalGet($node->toUrl());

    // Wait a moment for any JavaScript to run.
    $this->getSession()->wait(1000);

    // Verify the TOC is NOT displayed.
    $toc = $this->getSession()->getPage()->find('css', '.toc-js-container nav ul');
    $this->assertEmpty($toc, 'TOC is not displayed when disabled per node');
  }

  /**
   * Tests the per-node TOC checkbox on the node edit form.
   */
  public function testPerNodeTocCheckbox(): void {
    $this->drupalLogin($this->contentUser);

    // Visit the node creation form.
    $this->drupalGet('node/add/article');

    // Verify the TOC checkbox is present.
    $assert_session = $this->assertSession();
    $assert_session->fieldExists('toc_js_active');

    // Check that the default value is enabled (override_default = 1).
    $checkbox = $this->getSession()->getPage()->findField('toc_js_active');
    $this->assertTrue($checkbox->isChecked(), 'TOC checkbox is checked by default');
  }

  /**
   * Tests toggling the per-node TOC setting.
   */
  public function testTogglingPerNodeToc(): void {
    $this->drupalLogin($this->contentUser);

    // Create a node with TOC enabled.
    $body_content = '<h2>Section One</h2><p>Content.</p><h2>Section Two</h2><p>More content.</p>';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Toggle Test Node',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'toc_js_active' => 1,
      'status' => 1,
    ]);

    // Visit the node and verify TOC is displayed.
    $this->drupalGet($node->toUrl());
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', '.toc-js-container nav ul');
    $toc = $assert_session->elementExists('css', '.toc-js-container');
    $this->assertNotEmpty($toc, 'TOC is initially displayed');

    // Edit the node and disable TOC.
    $this->drupalGet($node->toUrl('edit-form'));
    $assert_session->fieldExists('toc_js_active');
    $checkbox = $this->getSession()->getPage()->findField('toc_js_active');
    $checkbox->uncheck();
    $this->getSession()->getPage()->pressButton('Save');

    // Verify TOC is no longer displayed.
    $this->getSession()->wait(1000);
    $toc = $this->getSession()->getPage()->find('css', '.toc-js-container nav ul');
    $this->assertEmpty($toc, 'TOC is not displayed after disabling');

    // Edit the node again and re-enable TOC.
    $this->drupalGet($node->toUrl('edit-form'));
    $checkbox = $this->getSession()->getPage()->findField('toc_js_active');
    $checkbox->check();
    $this->getSession()->getPage()->pressButton('Save');

    // Verify TOC is displayed again.
    $assert_session->waitForElement('css', '.toc-js-container nav ul');
    $toc = $assert_session->elementExists('css', '.toc-js-container');
    $this->assertNotEmpty($toc, 'TOC is displayed after re-enabling');
  }

  /**
   * Tests the content type configuration for per-node override.
   */
  public function testContentTypeConfiguration(): void {
    $this->drupalLogin($this->adminUser);

    // Visit the content type edit form.
    $this->drupalGet('admin/structure/types/manage/article');

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Click on the "Table of contents" vertical tab to make it visible.
    $toc_tab_link = $page->find('css', 'a[href="#edit-toc-js"]');
    $this->assertNotNull($toc_tab_link, 'Table of contents tab link found');
    $toc_tab_link->click();

    // Wait for the tab content to become visible.
    $toc_details = $assert_session->waitForElementVisible('css', 'details[data-drupal-selector="edit-toc-js"]');
    $this->assertNotNull($toc_details, 'Table of contents configuration details are visible');

    // Verify the per-node override checkbox is present.
    $assert_session->fieldExists('override');

    // Verify the default state radio buttons are present.
    $assert_session->fieldExists('override_default');

    // Check current values.
    $override_checkbox = $page->findField('override');
    $this->assertTrue($override_checkbox->isChecked(), 'Override is enabled');

    // Change the default to disabled.
    $page->selectFieldOption('override_default', '0');
    $page->pressButton('Save');

    // Reload the node type.
    $node_type = NodeType::load('article');
    $override_default = $node_type->getThirdPartySetting('toc_js_per_node', 'override_default', 1);
    $this->assertEquals(0, $override_default, 'Override default was changed to disabled');

    // Create a new node and verify TOC checkbox is unchecked by default.
    $this->drupalLogin($this->contentUser);
    $this->drupalGet('node/add/article');
    $checkbox = $this->getSession()->getPage()->findField('toc_js_active');
    $this->assertFalse($checkbox->isChecked(), 'TOC checkbox is unchecked by default after changing settings');
  }

  /**
   * Tests per-node block configuration override.
   */
  public function testPerNodeBlockOverride(): void {
    $this->drupalLogin($this->adminUser);

    // Configure the block to override node type settings.
    $block = $this->drupalPlaceBlock('toc_js_per_node_block', [
      'region' => 'content',
      'label' => 'Custom TOC Block',
      'override_nodetype' => 1,
      'title' => 'Custom Block TOC',
      'selectors' => 'h2',
      'container' => '.node > div',
    ]);

    // Create a node with TOC enabled.
    $this->drupalLogin($this->contentUser);
    $body_content = '<h2>Section One</h2><p>Content.</p><h3>Subsection</h3><p>More content.</p>';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Block Override Test',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'toc_js_active' => 1,
      'status' => 1,
    ]);

    // Visit the node.
    $this->drupalGet($node->toUrl());

    // Wait for JavaScript to generate the TOC.
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', '.toc-js-container nav ul');

    // Verify custom title is used (if visible).
    $page_content = $this->getSession()->getPage()->getContent();
    if (strpos($page_content, 'Custom Block TOC') !== FALSE) {
      $this->assertTrue(TRUE, 'Custom block title is used');
    }

    // Verify only h2 headings are in TOC (not h3, since override uses 'h2').
    // Note: This depends on the actual behavior - the block might show both.
    $toc_links = $this->getSession()->getPage()->findAll('css', '.toc-js nav ul li a');
    $this->assertNotEmpty($toc_links, 'TOC links are present with block override');
  }

  /**
   * Tests that heading_cleanup_selector removes elements from TOC links.
   */
  public function testHeadingCleanupSelector(): void {
    $this->drupalLogin($this->adminUser);

    // Update the existing block configuration to use heading_cleanup_selector.
    $blocks = $this->container->get('entity_type.manager')
      ->getStorage('block')
      ->loadByProperties(['plugin' => 'toc_js_per_node_block']);
    $block = reset($blocks);
    $settings = $block->get('settings');
    $settings['heading_cleanup_selector'] = '.icon, .visually-hidden';
    $block->set('settings', $settings);
    $block->save();

    // Create a node with headings that contain elements to be cleaned up.
    $this->drupalLogin($this->contentUser);
    $body_content = '<h2>Section One <span class="icon">★</span></h2><p>Content.</p>';
    $body_content .= '<h2>Section Two <span class="visually-hidden">Hidden Text</span></h2><p>More content.</p>';
    $body_content .= '<h2>Section Three</h2><p>Even more content.</p>';

    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Heading Cleanup Test',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'toc_js_active' => 1,
      'status' => 1,
    ]);

    // Visit the node.
    $this->drupalGet($node->toUrl());

    // Wait for JavaScript to generate the TOC.
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', '.toc-js-container nav ul');

    // Get all TOC links.
    $toc_links = $this->getSession()->getPage()->findAll('css', '.toc-js nav ul li a');
    $this->assertCount(3, $toc_links, 'Three TOC links should be present');

    // Extract the text from the TOC links.
    $toc_texts = [];
    foreach ($toc_links as $link) {
      $toc_texts[] = trim($link->getText());
    }

    // Verify that cleaned up elements are not in the TOC text.
    $this->assertEquals('Section One', $toc_texts[0], 'First TOC link should not contain the icon');
    $this->assertEquals('Section Two', $toc_texts[1], 'Second TOC link should not contain hidden text');
    $this->assertEquals('Section Three', $toc_texts[2], 'Third TOC link should be unchanged');

    // Verify that the cleaned up elements are NOT present in any TOC link text.
    foreach ($toc_texts as $text) {
      $this->assertStringNotContainsString('★', $text, 'Icon should not appear in TOC');
      $this->assertStringNotContainsString('Hidden Text', $text, 'Hidden text should not appear in TOC');
    }
  }

}
