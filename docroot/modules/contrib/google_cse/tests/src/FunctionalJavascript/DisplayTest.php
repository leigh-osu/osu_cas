<?php

declare(strict_types=1);

namespace Drupal\Tests\google_cse\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\search\Entity\SearchPage;

/**
 * Verify Google Search renders on a search page, matching configuration.
 *
 * @group google_cse
 */
#[Group('google_cse')]
#[RunTestsInSeparateProcesses]
class DisplayTest extends WebDriverTestBase {
  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'google_cse',
    'search',
  ];

  /**
   * Specify the theme to be used in testing.
   *
   * @var string
   */
  protected $defaultTheme = 'stable9';

  /**
   * A user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * Create user for the test.
   */
  protected function initializeTestUser() {
    $this->testUser = $this->drupalCreateUser([
      'administer blocks',
      'administer search',
      'search content',
      'search Google CSE',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->strictConfigSchema = NULL;
    parent::setUp();
    $this->initializeTestUser();
  }

  /**
   * Test that display matches various configuration options.
   *
   * 1. A search page entity can be created; its configuration is retained.
   * 2. Google renders expected HTML of search results from search term.
   * 3. Optional configuration (prefix text, input size) is respected.
   * 4. Configuration can display Google search form & suppress core form.
   * 5. Configuration renders one or more arbitrary data attributes.
   * 6. Form validation exists that ensures data attributes entered.
   * 7. Module-provided block can be placed in the layout & render results.
   * 8. Google watermark can be toggled.
   */
  public function testDisplay() {
    $this->getSession()->resizeWindow(1200, 2000);
    // Generic session invocations.
    $this->drupalLogin($this->testUser);
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Starting variables.
    $label = 'google_cse_search';
    $settings_form_path = Url::fromRoute('search.add_type', ['search_plugin_id' => $label]);
    $config_form_path = Url::fromRoute('entity.search_page.edit_form', ['search_page' => $label]);
    $search_page_path = 'google';
    // This is a test ID that returns results from gutenberg.org.
    $search_cx = '270c51fd0342eb4ae';

    // Create a Google CSE Search page displaying results only.
    $this->drupalGet($settings_form_path);
    $page->pressButton('Edit');
    $edit = [
      'edit-path' => $search_page_path,
      'edit-id' => $label,
      'edit-cx' => $search_cx,
      'edit-results-display-here' => 'here',
      'edit-custom-results-display-results-only' => 'results-only',
    ];
    $this->submitForm($edit, 'Save');

    // 1. A search page entity can be created; its configuration is retained.
    $this->drupalGet($config_form_path);
    $session->fieldValueEquals('edit-cx', $search_cx);
    $session->fieldValueEquals('edit-results-display-here', 'here');
    $session->fieldValueEquals('edit-custom-results-display-results-only', 'results-only');

    // Set this as the default search.
    $search_page_repository = \Drupal::service('search.search_page_repository');
    $entity = SearchPage::load($label);
    $search_page_repository->setDefaultSearchPage($entity);
    $entity_id = $search_page_repository->getDefaultSearchPage();

    // Place the core search block on pages.
    $this->drupalPlaceBlock('search_form_block');

    // Verify that the search form redirects to the expected path.
    $terms = ['keys' => '"dearest morsel of the earth"'];
    $this->drupalGet('');
    $this->submitForm($terms, 'Search', 'search-block-form');
    $search_page_with_query = Url::fromRoute('search.view_' . $entity_id, [], [
      'query' => [
        'keys' => $terms['keys'],
      ],
      'absolute' => TRUE,
    ])->toString();

    // 2. Google renders expected HTML of search results from search term.
    $this->assertEquals(urldecode($search_page_with_query), urldecode($this->getUrl()), 'Submitted to correct URL.');
    $session->elementExists('css', "div.gsc-webResult");
    // The first result from Google should contain text from Romeo & Juliet.
    $session->elementTextContains('css', 'div.gsc-control-cse.gsc-control-cse-en div.gsc-control-wrapper-cse div.gsc-results-wrapper-nooverlay.gsc-results-wrapper-visible div.gsc-wrapper div.gsc-resultsbox-visible div.gsc-resultsRoot.gsc-tabData.gsc-tabdActive div.gsc-results.gsc-webResult div.gsc-expansionArea div.gsc-webResult.gsc-result div.gs-webResult.gs-result div.gsc-table-result div.gsc-table-cell-snippet-close div.gs-bidi-start-align.gs-snippet', 'Gorg\'d with the dearest morsel of the earth');
    // The Drupal search box is not hidden.
    $session->elementAttributeContains('css', '#search-form #edit-keys', 'type', 'search');
    // The Google search box is not visible.
    $session->elementAttributeContains('css', 'form.gsc-search-box', 'style', 'display: none;');

    // 3. Optional configuration (prefix text, input size) is respected.
    $this->drupalGet($config_form_path);
    $page->fillField('edit-results-prefix', '<h3>Some results prefix text</h3><script>alert("hi");</script>');
    $page->fillField('edit-results-suffix', '<h3>Some results suffix text</h3>');
    $page->fillField('edit-results-searchbox-width', '10');
    $page->fillField('edit-custom-css', '/sites/default/files/custom.css');
    $page->pressButton('Save search page');
    $this->assertNotEmpty($session->waitForText('The Google Programmable Search search page has been updated.'));
    $this->drupalGet($search_page_with_query);
    $session->elementContains('css', '.google-cse-results-prefix', '<h3>Some results prefix text</h3>');
    $session->elementContains('css', '.google-cse-results-prefix', 'alert("hi");');
    $session->elementNotContains('css', '.google-cse-results-prefix', '<script>alert("hi");</script>');
    $session->elementContains('css', '.google-cse-results-suffix', '<h3>Some results suffix text</h3>');
    $session->elementAttributeContains('css', '#edit-basic input[name=keys]', 'size', '10');
    $session->elementAttributeContains('css', '#search-block-form input[name=keys]', 'size', '10');
    $session->elementAttributeContains('css', '#edit-basic input[name=keys]', 'value', '"dearest morsel of the earth"');
    $session->elementAttributeContains('css', '#search-block-form input[name=keys]', 'value', '"dearest morsel of the earth"');
    $session->responseContains('<link rel="stylesheet" media="all" href="/sites/default/files/custom.css">');

    // 4. Query parameter can be customized.
    $this->drupalGet($config_form_path);
    $custom_query_key = 'customquery';
    $page->fillField('edit-query-key', $custom_query_key);
    $page->pressButton('Save search page');
    $this->assertNotEmpty($session->waitForText('The Google Programmable Search search page has been updated.'));
    $search_page_with_query = Url::fromRoute('search.view_' . $entity_id, [], [
      'query' => [
        $custom_query_key => $terms['keys'],
      ],
      'absolute' => TRUE,
    ])->toString();
    $this->drupalGet($search_page_with_query);

    // 4. Configuration can display Google search form and suppress core form.
    $this->drupalGet($config_form_path);
    $page->uncheckField('edit-display-drupal-search');
    $page->selectFieldOption('custom_results_display', 'full-width');
    $page->pressButton('Save search page');
    $this->drupalGet($search_page_with_query);
    // The Drupal search box is removed.
    $session->elementNotExists('css', '#search-form #edit-' . $custom_query_key);
    // The Google search box is now visible.
    $session->elementAttributeNotExists('css', 'form.gsc-search-box', 'display');

    // 5. Configuration renders one or more arbitrary data attributes.
    // Request results in French, and open links in a new window.
    $this->drupalGet($config_form_path);
    $page->fillField('edit-data-attributes-0-key', 'data-lr');
    $page->fillField('edit-data-attributes-0-value', 'lang_fr');
    $page->fillField('edit-data-attributes-1-key', 'data-newWindow');
    $page->fillField('edit-data-attributes-1-value', 'true');
    $page->pressButton('Save search page');
    $this->drupalGet($search_page_with_query);
    // First search result contains "une chose pitoyable et burlesque".
    $session->elementTextContains('css',
      'div.gsc-control-cse.gsc-control-cse-en div.gsc-control-wrapper-cse div.gsc-results-wrapper-nooverlay.gsc-results-wrapper-visible div.gsc-wrapper div.gsc-resultsbox-visible div.gsc-resultsRoot.gsc-tabData.gsc-tabdActive div.gsc-results.gsc-webResult div.gsc-expansionArea div.gsc-webResult.gsc-result div.gs-webResult.gs-result div.gsc-table-result div.gsc-table-cell-snippet-close div.gs-bidi-start-align.gs-snippet',
      'une chose pitoyable et burlesque'
    );
    // Links open in a new window.
    $session->elementAttributeContains('css', 'h3.gs-title a.gs-title', 'target', '_blank');

    // 6. Form validation exists that ensures data attributes entered.
    $this->drupalGet($config_form_path);
    $page->fillField('edit-data-attributes-0-key', 'nodata-lr');
    $page->pressButton('Save search page');
    $session->pageTextContains('Data attributes must begin with data-.');
    $this->drupalGet($config_form_path);
    $page->fillField('edit-data-attributes-0-key', 'data-lr');
    $page->fillField('edit-data-attributes-0-value', '<strong>hi</strong>');
    $page->pressButton('Save search page');
    $session->pageTextContains('Attribute values may only contain alphanumerics, underscores, hyphens, and periods.');
    $this->drupalGet($config_form_path);
    $page->fillField('edit-data-attributes-0-key', 'data-lr');
    $page->fillField('edit-data-attributes-0-value', '');
    $page->pressButton('Save search page');
    $session->pageTextContains('You must provide both a key and value for this attribute. ');
    $this->drupalGet($config_form_path);
    $page->fillField('edit-data-attributes-0-key', '');
    $page->fillField('edit-data-attributes-0-value', 'lang_fr');
    $page->pressButton('Save search page');
    $session->pageTextContains('You must provide both a key and value for this attribute. ');

    // 7. Module-provided block can be placed in the layout & render results.
    $this->drupalGet('admin/structure/block');
    $this->getSession()->getPage()->findLink('Place block')->click();
    $this->assertNotEmpty($session->waitForText('Google Programmable Search'));
    $this->drupalPlaceBlock('google_cse', ['search_id' => $label]);
    $this->drupalGet('');
    $this->drupalGet($config_form_path);
    $page->checkField('edit-display-drupal-search');
    $page->selectFieldOption('custom_results_display', 'results-only');
    $page->pressButton('Save search page');
    $this->drupalGet('');
    // The Google search box is not visible.
    $session->elementAttributeContains('css', 'form.gsc-search-box', 'style', 'display: none;');
    // The Drupal search box is visible.
    $session->elementAttributeContains('css', '#google-cse-search-box-form .form-item-' . $custom_query_key . ' input', 'size', '10');
    // Search using the Google Search block.
    $this->drupalGet('');
    $terms = [$custom_query_key => '"dearest morsel of the earth"'];
    $this->submitForm($terms, 'Search', 'google-cse-search-box-form');
    // Module-provided block renders results on same page.
    $this->assertEquals(\Drupal::request()->getBasePath() . '/user/2', parse_url($this->getUrl(), PHP_URL_PATH), 'Submitted to correct URL.');
    $session->elementTextContains('css',
      'div.gsc-control-cse.gsc-control-cse-en div.gsc-control-wrapper-cse div.gsc-results-wrapper-nooverlay.gsc-results-wrapper-visible div.gsc-wrapper div.gsc-resultsbox-visible div.gsc-resultsRoot.gsc-tabData.gsc-tabdActive div.gsc-results.gsc-webResult div.gsc-expansionArea div.gsc-webResult.gsc-result div.gs-webResult.gs-result div.gsc-table-result div.gsc-table-cell-snippet-close div.gs-bidi-start-align.gs-snippet',
      'une chose pitoyable et burlesque'
    );

    // 8. Google watermark can be toggled.
    $watermark = $this->getSession()->evaluateScript('jQuery("img.gcsc-branding-img-noclear").css("display")');
    $this->assertSame("inline", $watermark);
    $this->drupalGet($config_form_path);
    $page->checkField('edit-watermark');
    $page->pressButton('Save search page');
    $this->drupalGet('');
    $watermark = $this->getSession()->evaluateScript('jQuery("img.gcsc-branding-img-noclear").css("display")');
    $this->assertNull($watermark, 'Watermark is not present');
  }

}
