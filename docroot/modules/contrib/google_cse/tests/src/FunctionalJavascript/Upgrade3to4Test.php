<?php

declare(strict_types=1);

namespace Drupal\Tests\google_cse\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\search\Entity\SearchPage;

/**
 * Demonstrate configuration successfully migrated from version 3 to 4.
 *
 * @group google_cse
 */
#[Group('google_cse')]
#[RunTestsInSeparateProcesses]
class Upgrade3to4Test extends WebDriverTestBase {
  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  protected static $modules = [
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->strictConfigSchema = NULL;
    parent::setUp();
  }

  /**
   * Demonstrate configuration successfully migrated from version 3 to 4.
   *
   * 1. Create a search instance with legacy configuration.
   * 2. Perform database update from 3.x to 4.x.
   * 3. Verify updated configuration matches expectations.
   */
  public function testUpgrade() {
    // Starting variables.
    $label = 'google_cse_search';
    $search_page_path = 'google';
    // This is a test ID that returns results from gutenberg.org.
    $search_cx = '270c51fd0342eb4ae';

    // 1. Create a search instance with legacy configuration.
    $search_page = SearchPage::create([
      'id' => $label,
      'plugin' => $label,
      'label' => $label,
      'path' => $search_page_path,
    ]);
    $search_page->save();
    $legacy_config = [
      'cx' => $search_cx,
      'results_tab' => '',
      'results_width' => 600,
      'cof_here' => 'FORID:11',
      'cof_google' => 'FORID:0',
      'results_prefix' => '<h3>Some results prefix text</h3><script>alert("hi");</script>',
      'results_suffix' => '<h3>Some results suffix text</h3>',
      'results_searchbox_width' => 10,
      'results_display' => 'here',
      'results_display_images' => TRUE,
      'sitesearch' => '',
      'sitesearch_form' => 'radios',
      'sitesearch_option' => '',
      'sitesearch_default' => 0,
      'domain' => 'www.google.com',
      'limit_domain' => 'gutenberg.org',
      'cr' => 'countryFR',
      'gl' => 'test',
      'hl' => '',
      'locale_hl' => '',
      'ie' => 'utf-8',
      'lr' => 'lang_fr',
      'locale_lr' => '',
      'oe' => '',
      'safe' => 'medium',
      'custom_css' => '/sites/default/files/custom.css',
      'custom_results_display' => 'results-only',
    ];
    $search_page->getPlugin()->setConfiguration($legacy_config);
    $search_page->save();
    // Simulate database update.
    _google_cse_convert_from_3_to_4();
    // Set this as the default search.
    $search_page_repository = \Drupal::service('search.search_page_repository');
    $entity = SearchPage::load($label);
    $search_page_repository->setDefaultSearchPage($entity);

    $updated_config = $entity->getPlugin()->getConfiguration();
    $expected_config = [
      'cx' => '270c51fd0342eb4ae',
      'results_prefix' => '<h3>Some results prefix text</h3><script>alert("hi");</script>',
      'results_suffix' => '<h3>Some results suffix text</h3>',
      'results_searchbox_width' => 10,
      'results_display' => 'here',
      'limit_domain' => 'gutenberg.org',
      'custom_css' => '/sites/default/files/custom.css',
      'custom_results_display' => 'results-only',
      'data_attributes' => [
        ['key' => 'data-lr', 'value' => 'lang_fr'],
        ['key' => 'data-gl', 'value' => 'test'],
        ['key' => 'data-cr', 'value' => 'countryFR'],
        ['key' => 'data-as_sitesearch', 'value' => 'gutenberg.org'],
        ['key' => 'data-safeSearch', 'value' => 'moderate'],
      ],
    ];
    $this->assertEquals($updated_config, $expected_config);
  }

}
