<?php

declare(strict_types=1);

namespace Drupal\Tests\toc_js_filter\Functional;

use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the TocJsFilter functionality.
 *
 * @group toc_js_filter
 */
class TocJsFilterTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'filter',
    'text',
    'toc_js',
    'toc_js_filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to create content and use text formats.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $contentUser;

  /**
   * Custom format name.
   *
   * @var string
   */
  protected $formatName = 'toc_filter_test_format';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the "article" content type.
    $this->createContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Create a text format that includes the toc_js_filter.
    $format = FilterFormat::create([
      'format' => $this->formatName,
      'name' => 'TOC Filter Test Format',
      'weight' => 0,
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<h2> <h3> <h4> <h5> <h6> <p> <br> <strong> <em> <ul> <ol> <li> <div> <nav> <a>',
          ],
        ],
        'toc_js_filter' => [
          'status' => TRUE,
          'settings' => [
            'title' => 'Table of Contents',
            'title_tag' => 'div',
            'list_type' => 'ul',
            'selectors' => 'h2, h3, h4',
            'back_to_top' => 0,
            'smooth_scrolling' => 1,
            'scroll_to_offset' => 0,
          ],
        ],
      ],
    ]);
    $format->save();

    // Create a user with required permissions and log them in.
    $permissions = [
      'create article content',
      'edit own article content',
      'access content',
      'use text format ' . $this->formatName,
    ];
    $this->contentUser = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->contentUser);
  }

  /**
   * Tests that [toc] shortcode is replaced with rendered TOC.
   */
  public function testTocShortcodeReplacement(): void {
    // Create an article node with the [toc] shortcode and headings.
    $body_content = '<p>[toc]</p><h2>Section One</h2><p>First section content.</p><h2>Section Two</h2><p>Second section content.</p><h3>Subsection Two-A</h3><p>Subsection content.</p>';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'TOC Filter Test Node',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'status' => 1,
    ]);

    // Visit the node.
    $this->drupalGet($node->toUrl());

    // Verify the section headings appear in the page.
    $this->assertSession()->pageTextContains('Section One');
    $this->assertSession()->pageTextContains('Section Two');
    $this->assertSession()->pageTextContains('Subsection Two-A');

    // Verify that [toc] shortcode has been replaced (not visible).
    $this->assertSession()->pageTextNotContains('[toc]');

    // Verify that the TOC container is rendered (with toc-js class).
    $this->assertSession()->elementExists('css', '.toc-js');

    // Verify the TOC title is rendered.
    $this->assertSession()->pageTextContains('Table of Contents');
  }

  /**
   * Tests that content without [toc] shortcode is not affected.
   */
  public function testNoShortcode(): void {
    // Create an article node without the [toc] shortcode.
    $body_content = '<h2>Section One</h2><p>First section content.</p><h2>Section Two</h2><p>Second section content.</p>';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'No TOC Test Node',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'status' => 1,
    ]);

    // Visit the node.
    $this->drupalGet($node->toUrl());

    // Verify the section headings appear in the page.
    $this->assertSession()->pageTextContains('Section One');
    $this->assertSession()->pageTextContains('Section Two');

    // Verify that the TOC container is NOT rendered since no [toc] shortcode.
    $this->assertSession()->elementNotExists('css', '.toc-js');
  }

  /**
   * Tests case-insensitive [toc] shortcode matching.
   */
  public function testCaseInsensitiveShortcode(): void {
    // Test uppercase [TOC].
    $body_content = '<p>[TOC]</p><h2>Section One</h2><p>Content here.</p>';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Uppercase TOC Test',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'status' => 1,
    ]);

    $this->drupalGet($node->toUrl());

    // Verify [TOC] has been replaced.
    $this->assertSession()->pageTextNotContains('[TOC]');
    $this->assertSession()->elementExists('css', '.toc-js');

    // Test mixed case [ToC].
    $body_content = '<p>[ToC]</p><h2>Another Section</h2><p>More content.</p>';
    $node2 = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Mixed Case TOC Test',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'status' => 1,
    ]);

    $this->drupalGet($node2->toUrl());

    // Verify [ToC] has been replaced.
    $this->assertSession()->pageTextNotContains('[ToC]');
    $this->assertSession()->elementExists('css', '.toc-js');
  }

  /**
   * Tests multiple [toc] shortcodes in the same content.
   */
  public function testMultipleTocShortcodes(): void {
    // Create content with multiple [toc] shortcodes.
    $body_content = '<p>[toc]</p><h2>First Section</h2><p>Content.</p><p>[toc]</p><h2>Second Section</h2><p>More content.</p>';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Multiple TOC Test',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'status' => 1,
    ]);

    $this->drupalGet($node->toUrl());

    // Verify that [toc] shortcodes have been replaced.
    $this->assertSession()->pageTextNotContains('[toc]');

    // Verify that multiple TOC containers are rendered.
    $toc_containers = $this->getSession()->getPage()->findAll('css', '.toc-js');
    $this->assertCount(2, $toc_containers, 'Two TOC containers should be rendered.');
  }

  /**
   * Tests filter configuration through the format admin UI.
   */
  public function testFilterConfiguration(): void {
    // Create an admin user who can configure text formats.
    $admin_user = $this->drupalCreateUser([
      'administer filters',
      'use text format ' . $this->formatName,
    ]);
    $this->drupalLogin($admin_user);

    // Visit the format edit page.
    $this->drupalGet('admin/config/content/formats/manage/' . $this->formatName);

    // Verify the toc_js_filter is enabled.
    $this->assertSession()->checkboxChecked('filters[toc_js_filter][status]');

    // Verify the filter settings form is present.
    $this->assertSession()->fieldExists('filters[toc_js_filter][settings][title]');
    $this->assertSession()->fieldExists('filters[toc_js_filter][settings][selectors]');

    // Update the TOC title setting.
    $new_title = 'Custom TOC Title';
    $edit = [
      'filters[toc_js_filter][settings][title]' => $new_title,
    ];
    $this->submitForm($edit, 'Save configuration');

    // Verify the configuration was saved.
    $this->assertSession()->statusMessageContains('The text format TOC Filter Test Format has been updated.', 'status');

    // Log back in as the content user.
    $this->drupalLogin($this->contentUser);

    // Create a node to verify the new title is used.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Custom Title Test',
      'body' => [
        'value' => '<p>[toc]</p><h2>Test Section</h2><p>Content.</p>',
        'format' => $this->formatName,
      ],
      'status' => 1,
    ]);

    $this->drupalGet($node->toUrl());

    // Verify the custom title appears.
    $this->assertSession()->pageTextContains($new_title);
  }

}
