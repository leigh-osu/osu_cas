<?php

declare(strict_types=1);

namespace Drupal\Tests\toc_js_filter\FunctionalJavascript;

use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the TocJsFilter JavaScript functionality.
 *
 * @group toc_js_filter
 */
class TocJsFilterJavascriptTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'filter',
    'text',
    'toc_js',
    'toc_js_filter',
    'toc_js_test_module',
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
  protected $formatName = 'toc_filter_js_test_format';

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
      'name' => 'TOC Filter JS Test Format',
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
            // > .div added to exclude the member footer with h4.
            'container' => '.node > div',
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
   * Tests that JavaScript generates the TOC with links to headings.
   */
  public function testJavascriptGeneratesTocLinks(): void {
    // Create an article node with the [toc] shortcode and headings.
    $body_content = '<p>[toc]</p><h2>Section One</h2><p>First section content.</p><h2>Section Two</h2><p>Second section content.</p><h3>Subsection Two-A</h3><p>Subsection content.</p>';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'TOC JavaScript Test Node',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'status' => 1,
    ]);

    // Visit the node.
    $this->drupalGet($node->toUrl());

    // Verify the TOC navigation list was created by JavaScript.
    $assert_session = $this->assertSession();
    $toc_list = $assert_session->waitForElement('css', '.toc-js-container nav ul');
    $this->assertNotEmpty($toc_list, 'TOC list element was created by JavaScript');

    // Verify TOC links are present.
    $toc_links = $this->getSession()->getPage()->findAll('css', '.toc-js nav ul li a');
    $this->assertNotEmpty($toc_links, 'TOC links were created by JavaScript');
    $this->assertCount(3, $toc_links, 'Three TOC links should be generated for the three headings');

    // Extract the text from the TOC links.
    $toc_text = [];
    foreach ($toc_links as $link) {
      $toc_text[] = $link->getText();
    }

    // Verify each heading appears in the TOC links.
    $this->assertContains('Section One', $toc_text, 'Section One heading appears in the TOC');
    $this->assertContains('Section Two', $toc_text, 'Section Two heading appears in the TOC');
    $this->assertContains('Subsection Two-A', $toc_text, 'Subsection Two-A heading appears in the TOC');

    // Verify links have href attributes with anchors.
    foreach ($toc_links as $link) {
      $href = $link->getAttribute('href');
      $this->assertNotEmpty($href, 'TOC link has an href attribute');
      $this->assertStringStartsWith('#', $href, 'TOC link href starts with # (anchor)');
    }
  }

  /**
   * Tests that headings get IDs added by JavaScript.
   */
  public function testHeadingsGetIds(): void {
    // Create a node with headings.
    $body_content = '<p>[toc]</p><h2>Test Heading</h2><p>Content.</p><h3>Sub Heading</h3><p>More content.</p>';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Heading IDs Test',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'status' => 1,
    ]);

    $this->drupalGet($node->toUrl());

    // Wait for JavaScript to process.
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', '.toc-js-container nav ul');

    // Verify headings have IDs added.
    $h2 = $assert_session->elementExists('css', 'h2');
    $h2_id = $h2->getAttribute('id');
    $this->assertNotEmpty($h2_id, 'H2 heading has an ID attribute');

    $h3 = $assert_session->elementExists('css', 'h3');
    $h3_id = $h3->getAttribute('id');
    $this->assertNotEmpty($h3_id, 'H3 heading has an ID attribute');
  }

  /**
   * Tests that clicking a TOC link scrolls to the heading.
   */
  public function testTocLinkClickScrollsToHeading(): void {
    // Create a node with multiple headings and enough content to require
    // scrolling.
    $body_content = '<p>[toc]</p>';
    $body_content .= '<h2>Section One</h2>' . str_repeat('<p>Lorem ipsum dolor sit amet.</p>', 20);
    $body_content .= '<h2>Section Two</h2>' . str_repeat('<p>Consectetur adipiscing elit.</p>', 20);
    $body_content .= '<h2>Section Three</h2>' . str_repeat('<p>Sed do eiusmod tempor.</p>', 20);

    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'TOC Click Test',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'status' => 1,
    ]);

    $this->drupalGet($node->toUrl());

    // Wait for TOC to be generated.
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', '.toc-js-container nav ul li a');

    // Find the link to "Section Three".
    $page = $this->getSession()->getPage();
    $toc_links = $page->findAll('css', '.toc-js nav ul li a');
    $section_three_link = NULL;
    foreach ($toc_links as $link) {
      if ($link->getText() === 'Section Three') {
        $section_three_link = $link;
        break;
      }
    }

    $this->assertNotNull($section_three_link, 'Found link to Section Three in TOC');

    // Click the link.
    $section_three_link->click();

    // Wait a moment for smooth scrolling to complete.
    $this->getSession()->wait(1000);

    // Verify that the Section Three heading is now visible in the viewport.
    $section_three_heading = $assert_session->elementExists('css', 'h2:contains("Section Three")');
    $this->assertTrue($section_three_heading->isVisible(), 'Section Three heading is visible after clicking TOC link');
  }

  /**
   * Tests that nested list structure is created for different heading levels.
   */
  public function testNestedTocStructure(): void {
    // Create content with multiple heading levels.
    $body_content = '<p>[toc]</p>';
    $body_content .= '<h2>Main Section 1</h2><p>Content.</p>';
    $body_content .= '<h3>Subsection 1.1</h3><p>Content.</p>';
    $body_content .= '<h3>Subsection 1.2</h3><p>Content.</p>';
    $body_content .= '<h2>Main Section 2</h2><p>Content.</p>';
    $body_content .= '<h3>Subsection 2.1</h3><p>Content.</p>';
    $body_content .= '<h4>Sub-subsection 2.1.1</h4><p>Content.</p>';

    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Nested TOC Test',
      'body' => [
        'value' => $body_content,
        'format' => $this->formatName,
      ],
      'status' => 1,
    ]);

    $this->drupalGet($node->toUrl());

    // Wait for TOC to be generated.
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', '.toc-js-container nav ul');

    // Verify the main TOC list exists.
    $main_list = $assert_session->elementExists('css', '.toc-js nav > ul');
    $this->assertNotEmpty($main_list, 'Main TOC list exists');

    // Verify nested lists are created for subsections.
    $nested_lists = $this->getSession()->getPage()->findAll('css', '.toc-js nav ul ul');
    $this->assertNotEmpty($nested_lists, 'Nested lists were created for subsections');

    // Verify all heading levels are represented in the TOC.
    $all_links = $this->getSession()->getPage()->findAll('css', '.toc-js nav a');
    $link_texts = array_map(fn($link) => $link->getText(), $all_links);

    $this->assertContains('Main Section 1', $link_texts);
    $this->assertContains('Subsection 1.1', $link_texts);
    $this->assertContains('Subsection 1.2', $link_texts);
    $this->assertContains('Main Section 2', $link_texts);
    $this->assertContains('Subsection 2.1', $link_texts);
    $this->assertContains('Sub-subsection 2.1.1', $link_texts);
  }

  /**
   * Tests that multiple TOC instances work independently.
   */
  public function testMultipleTocInstances(): void {
    // Create content with two [toc] shortcodes and headings in between.
    $body_content = '<p>[toc]</p>';
    $body_content .= '<h2>First Section</h2><p>Content.</p>';
    $body_content .= '<h2>Second Section</h2><p>Content.</p>';
    $body_content .= '<p>[toc]</p>';
    $body_content .= '<h2>Third Section</h2><p>Content.</p>';

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

    // Wait for TOC elements to be generated.
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', '.toc-js nav ul');

    // Find all TOC containers.
    $toc_containers = $this->getSession()->getPage()->findAll('css', '.toc-js');
    $this->assertCount(2, $toc_containers, 'Two TOC containers are present');

    // Verify both TOCs have been processed by JavaScript.
    foreach ($toc_containers as $index => $container) {
      $nav_list = $container->find('css', 'nav ul');
      $this->assertNotEmpty($nav_list, "TOC container {$index} has a navigation list");

      $links = $container->findAll('css', 'nav a');
      $this->assertNotEmpty($links, "TOC container {$index} has links");
    }
  }

}
