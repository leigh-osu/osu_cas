<?php

declare(strict_types=1);

namespace Drupal\Tests\toc_js_per_node\Functional;

use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the TOC JS per node functionality.
 *
 * @group toc_js_per_node
 */
class TocJsPerNodeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'block',
    'text',
    'field_ui',
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
            'allowed_html' => '<h2> <h3> <h4> <h5> <h6> <p> <br> <strong> <em> <ul> <ol> <li>',
          ],
        ],
      ],
    ]);
    $format->save();

    // Enable TOC for the article content type with per-node override.
    $this->nodeType->setThirdPartySetting('toc_js', 'toc_js_active', TRUE);
    $this->nodeType->setThirdPartySetting('toc_js', 'title', 'Table of Contents');
    $this->nodeType->setThirdPartySetting('toc_js', 'selectors', 'h2, h3');
    $this->nodeType->setThirdPartySetting('toc_js', 'container', '.node > div');
    $this->nodeType->setThirdPartySetting('toc_js_per_node', 'override', TRUE);
    $this->nodeType->setThirdPartySetting('toc_js_per_node', 'override_default', TRUE);
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
      'administer node display',
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
   * Tests TOC behavior with multiple nodes on the same page.
   */
  public function testMultipleNodesWithDifferentTocSettings(): void {
    $this->drupalLogin($this->contentUser);

    // Create two nodes: one with TOC enabled, one with TOC disabled.
    $body_content = '<h2>Section</h2><p>Content.</p>';
    $node1 = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Node with TOC',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'toc_js_active' => TRUE,
      'status' => 1,
    ]);

    $node2 = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Node without TOC',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'toc_js_active' => FALSE,
      'status' => 1,
    ]);

    // Visit first node and verify TOC is displayed.
    $this->drupalGet($node1->toUrl());
    $assert_session = $this->assertSession();
    $toc = $assert_session->elementExists('css', '.toc-js');
    $this->assertNotEmpty($toc, 'TOC is displayed on node1');

    // Visit second node and verify TOC is NOT displayed.
    $this->drupalGet($node2->toUrl());
    $toc = $this->getSession()->getPage()->find('css', '.toc-js');
    $this->assertEmpty($toc, 'TOC is not displayed on node2');
  }

  /**
   * Tests permission for per-node TOC control.
   */
  public function testPerNodeTocPermission(): void {
    // Create a user without the 'administer toc_js per node' permission.
    $limited_user = $this->drupalCreateUser([
      'create article content',
      'edit own article content',
      'access content',
    ]);

    $this->drupalLogin($limited_user);

    // Visit the node creation form.
    $this->drupalGet('node/add/article');

    // Verify the TOC checkbox is NOT present (no permission).
    $assert_session = $this->assertSession();
    $assert_session->fieldNotExists('toc_js_active');

    // Login with proper permissions.
    $this->drupalLogin($this->contentUser);
    $this->drupalGet('node/add/article');

    // Verify the TOC checkbox IS present.
    $assert_session->fieldExists('toc_js_active');
  }

  /**
   * Tests the extra field for TOC display.
   */
  public function testTocExtraField(): void {
    $this->drupalLogin($this->adminUser);

    // Visit the manage display page for the content type.
    $this->drupalGet('admin/structure/types/manage/article/display');

    // Verify the 'toc_js' extra field is available in the field list.
    $assert_session = $this->assertSession();

    // Check for the field row by ID (Drupal uses field-{field_name} pattern).
    $assert_session->elementExists('css', '#toc-js');

    // Verify the field has a region selector (indicating it's manageable).
    $assert_session->fieldExists('fields[toc_js][region]');
  }

  /**
   * Tests that the TOC is rendered via the extra field display.
   */
  public function testTocRenderedViaExtraField(): void {
    $this->drupalLogin($this->adminUser);

    // First, disable the block so we can test the extra field in isolation.
    $blocks = $this->container->get('entity_type.manager')
      ->getStorage('block')
      ->loadByProperties(['plugin' => 'toc_js_per_node_block']);
    foreach ($blocks as $block) {
      $block->disable()->save();
    }

    // Ensure the toc_js extra field is enabled and visible.
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->submitForm([
      'fields[toc_js][region]' => 'content',
    ], 'Save');

    // Create a node with TOC enabled and content.
    $this->drupalLogin($this->contentUser);
    $body_content = '<h2>Section One</h2><p>Content.</p><h2>Section Two</h2><p>More content.</p>';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'TOC Extra Field Test',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'toc_js_active' => TRUE,
      'status' => 1,
    ]);

    // Visit the node and verify TOC is rendered.
    $this->drupalGet($node->toUrl());
    $assert_session = $this->assertSession();

    // Verify the TOC container is present.
    $assert_session->elementExists('css', '.toc-js');

    // Verify the TOC title is rendered.
    $assert_session->pageTextContains('Table of Contents');
  }

  /**
   * Tests that disabling override removes the checkbox from node form.
   */
  public function testDisablingOverride(): void {
    $this->drupalLogin($this->adminUser);

    // Disable the per-node override.
    $this->nodeType->setThirdPartySetting('toc_js_per_node', 'override', FALSE);
    $this->nodeType->save();

    // Visit the node creation form.
    $this->drupalLogin($this->contentUser);
    $this->drupalGet('node/add/article');

    // Verify the TOC checkbox is NOT present.
    $assert_session = $this->assertSession();
    $assert_session->fieldNotExists('toc_js_active');
  }

  /**
   * Tests that programmatically created nodes get the correct default value.
   */
  public function testProgrammaticNodeCreationDefaultValue(): void {
    // Case 1: Default is enabled (TRUE).
    // (Already set to TRUE in setUp, but let's be explicit).
    $this->nodeType->setThirdPartySetting('toc_js_per_node', 'override_default', TRUE);
    $this->nodeType->save();

    $node_enabled = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Programmatic Node Enabled',
    ]);
    $this->assertEquals(TRUE, $node_enabled->toc_js_active->value, 'Default value is TRUE when override_default is TRUE');

    // Case 2: Default is disabled (FALSE).
    $this->nodeType->setThirdPartySetting('toc_js_per_node', 'override_default', FALSE);
    $this->nodeType->save();

    $node_disabled = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Programmatic Node Disabled',
    ]);
    $this->assertEquals(FALSE, $node_disabled->toc_js_active->value, 'Default value is FALSE when override_default is FALSE');
  }

}
