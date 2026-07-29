<?php

namespace Drupal\google_cse\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\search\Entity\SearchPage;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for google_cse.
 */
class GoogleCseHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path) {
    // Register the Google Search Results theme template.
    return [
      'google_cse_results' => [
        'variables' => [
          'path' => $path,
          'noscript' => NULL,
          'results_prefix' => NULL,
          'results_suffix' => NULL,
          'primary_attributes' => NULL,
          'secondary_attributes' => NULL,
        ],
        'template' => 'google_cse_results',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_item_list__search_results().
   */
  #[Hook('preprocess_item_list__search_results')]
  public function preprocessItemListSearchResults(&$variables) {
    if (!isset($variables['context']['plugin']) || $variables['context']['plugin'] !== 'google_cse_search') {
      return;
    }
    // In the context of Google PSE, we rely on Google to render the content.
    // Therefore, we do not want to use Drupal's default list
    // (see Drupal\search\Controller\SearchController::view()).
    // The simplest way to achieve this in a Drupal context is to
    // unset the "items" sent to the list template and render the
    // Google PSE results as the 'empty' value.
    if (isset($variables['items'][0]['value'])) {
      $variables['empty'] = $variables['items'][0];
      unset($variables['empty']['attributes']);
      unset($variables['items']);
    }
    // Clear 'Your search yielded no results' from Drupal SearchController().
    $variables['empty']['#markup'] = '';
  }

  /**
   * Implements hook_library_info_build().
   */
  #[Hook('library_info_build')]
  public function libraryInfoBuild() {
    $libraries = [];
    $search_implementations = SearchPage::loadMultiple();
    // Add separate CSS libraries for any search plugins that set external CSS.
    // These are attached in template_preprocess_google_cse_results().
    foreach ($search_implementations as $search) {
      if ($search->getPlugin()->getPluginId() !== 'google_cse_search') {
        continue;
      }
      $config = $search->getPlugin()->getConfiguration();
      $external_css = $config['custom_css'];
      if ($external_css) {
        $libraries['customCSS_' . md5($external_css)] = [
          'css' => [
            'theme' => [
              $external_css => [
                'type' => 'external',
              ],
            ],
          ],
        ];
      }
    }
    return $libraries;
  }

  /**
   * Implements hook_entity_insert().
   *
   * Clear appropriate caches on storing Google Search plugin entity instance.
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity) {
    if ($entity instanceof SearchPage) {
      $plugin = $entity->getPlugin()->getPluginId();
      if ($plugin == 'google_cse_search') {
        \Drupal::service('router.builder')->rebuild();
      }
    }
  }

  /**
   * Implements hook_migration_plugins_alter().
   */
  #[Hook('migration_plugins_alter')]
  public function migrationPluginsAlter(array &$migrations) {
    if (!in_array('d7_search_settings', array_keys($migrations))) {
      return;
    }
    $variable_source = \Drupal::service('plugin.manager.migration')->createStubMigration([
      'id' => 'foo',
      'idMap' => [
        'plugin' => 'null',
      ],
      'source' => [
        'plugin' => 'variable',
        'ignore_map' => TRUE,
      ],
      'destination' => [
        'plugin' => 'null',
      ],
    ])->getSourcePlugin();
    if (!$variable_source instanceof DrupalSqlBase) {
      return;
    }
    try {
      $variable_source->checkRequirements();
    }
    catch (RequirementsException $e) {
      // Variable source plugin requirements aren't met, this is not a Drupal
      // source.
      return;
    }
    $system_data = $variable_source->getSystemData();
    if (empty($system_data['module']['google_cse']['status'])) {
      unset($migrations['d7_google_cse']);
      return;
    }
    if (!empty($migrations['d7_search_settings']['process']['default_page']) && array_key_exists('map', $migrations['d7_search_settings']['process']['default_page'])) {
      $migrations['d7_search_settings']['process']['default_page']['map']['google_cse'] = 'google_cse_search';
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.google_cse':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Google Programmable Search is an embedded search engine that can be used to search any set of one or more sites.  No Google API key is required. Read more at <a href="https://developers.google.com/custom-search" target="blank">https://developers.google.com/custom-search</a>.') . '</p>';
        $output .= '<h3>' . $this->t('Setup') . '</h3>';
        $output .= '<ol>' . $this->t('<li>Before installing this module, register a Google Search engine at <a href="https://programmablesearchengine.google.com/cse/all">https://programmablesearchengine.google.com/cse/all</a>.</li><li>Install this module and create a search instance at <a href="/admin/config/search/pages">/admin/config/search/pages</a> and configure it by entering your Google Search ID.</li><li>Optionally set it as the default search module</li><li>Grant the <a href="/admin/people/permissions/module/google_cse">"View Google Programmable Search"</a> permission to one or more roles to use Google Search.</li>') . '</ol>';
        $output .= '<p>If you set this search instance as the default Drupal search, the core search block will redirect directly to your site\'s Google search results page.</p><p>If you instead want to embed the search form and its results within a page, use the Google Programmable Search block, described below.</p>';
        $output .= '<h4>' . $this->t('Search as Block') . '</h4>';
        $output .= '<p>' . $this->t('For sites that do not want search results to display on a standalone page, this module includes a Google Programmable Search block which can be enabled at <a href="/admin/structure/block">/admin/structure/block</a>. This block provides a combined search box and with search results. After entering search terms, the user will be returned to the same page and the results will be displayed. <strong>Important: Do not configure this block to appear on the search page, as the search results will fail to display</strong>.') . '</p>';
        $output .= '<h4>' . $this->t('Customizing Programmable Search Elements') . '</h4>';
        $output .= '<p>' . $this->t("You can use optional attributes to overwrite configurations created in the Programmable Search Engine control panel. This enables you to create a page-specific search experience. These attributes can be added via key/value inputs in the Drupal search configuration form. This module does not document these attributes; rather, it is the responsibility of the site maintainer to understand the behavior of the available attributes, which are listed at <a href='https://developers.google.com/custom-search/docs/element'>https://developers.google.com/custom-search/docs/element</a>.") . '</p>';
        return $output;
    }
  }

}
