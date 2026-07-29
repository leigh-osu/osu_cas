<?php

namespace Drupal\Tests\toc_js\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\filter\Entity\FilterFormat;

/**
 * Tests the TOC JS module default behavior.
 *
 * @group toc_js
 */
class TocJsJavascriptTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'toc_js',
    'block',
    'user',
    'filter',
    'text',
    'toc_js_test_module',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to create and view content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $contentUser;

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
    $this->createContentType([
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

    // Create a user with required permissions and log them in.
    $permissions = [
      'create article content',
      'edit own article content',
      'access content',
      'administer blocks',
      'use text format ' . $this->formatName,
    ];
    $account = $this->drupalCreateUser($permissions);
    $this->drupalLogin($account);

    // Place the TOC block in the content region.
    $this->drupalPlaceBlock('toc_js_block', [
      'region' => 'content',
      'label' => 'TOC Block',
    ]);
  }

  /**
   * Test that TOC JS works with default settings.
   */
  public function testTocJsAppears() {
    // Create an article node with headings.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'TOC Test Node',
      'body' => [
        'value' => '<h2>Section One</h2><p>Text</p><h2>Section Two</h2><p>Text</p>',
        'format' => $this->formatName,
      ],
      'status' => 1,
    ]);

    // Visit the node.
    $this->drupalGet($node->toUrl());

    // Verify the section headings appear in the page.
    $page = $this->getSession()->getPage();
    $this->assertStringContainsString('Section One', $page->getText(), 'Section One heading was found');
    $this->assertStringContainsString('Section Two', $page->getText(), 'Section Two heading was found');

    // Wait for JavaScript to process and the TOC to render.
    $assert_session = $this->assertSession();
    $toc_element = $assert_session->waitForElement('css', '.toc-js-container nav ul');
    $this->assertNotEmpty($toc_element, 'TOC UL element was found');

    // Specifically, check for headings within the TOC element itself.
    $toc_links = $toc_element->findAll('css', 'a');
    $this->assertNotEmpty($toc_links, 'TOC links were found inside the TOC element');

    // Extract the text from the TOC links to verify headings are present.
    $toc_text = [];
    foreach ($toc_links as $link) {
      $toc_text[] = $link->getText();
    }

    // Verify each heading appears in the TOC links.
    $this->assertContains('Section One', $toc_text, 'Section One heading appears in the TOC');
    $this->assertContains('Section Two', $toc_text, 'Section Two heading appears in the TOC');
  }

}
