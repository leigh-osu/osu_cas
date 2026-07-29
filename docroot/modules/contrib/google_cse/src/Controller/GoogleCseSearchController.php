<?php

namespace Drupal\google_cse\Controller;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\google_cse\Plugin\Search\GoogleSearch;
use Drupal\search\Controller\SearchController;
use Drupal\search\Form\SearchPageForm;
use Drupal\search\SearchPageInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Override the Route controller for search.
 */
class GoogleCseSearchController extends SearchController {

  /**
   * Creates a render array for the search page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\search\SearchPageInterface $entity
   *   The search page entity.
   *
   * @return array
   *   The search form and search results build array.
   */
  public function view(Request $request, SearchPageInterface $entity) {
    $configuration = $entity->get('configuration');
    $query_key = $configuration['query_key'] ?? GoogleSearch::$defaultQueryKey;
    // If the query parameter key hasn't been customized, use the parent class.
    if ($query_key === 'keys') {
      $build = parent::view($request, $entity);
      return $build;
    }

    $build = [];
    $plugin = $entity->getPlugin();

    // Build the form first, because it may redirect during the submit,
    // and we don't want to build the results based on last time's request.
    $build['#cache']['contexts'][] = 'url.query_args:' . $query_key;
    if ($request->query->has($query_key)) {
      $keys = trim($request->query->get($query_key));
      $plugin->setSearch($keys, $request->query->all(), $request->attributes->all());
    }

    $build['#title'] = $plugin->suggestedTitle();
    $build['search_form'] = $this->formBuilder()->getForm(SearchPageForm::class, $entity);

    // Build search results, if keywords or other search parameters are in the
    // GET parameters.
    $results = [];
    if ($request->query->has($query_key)) {
      if ($plugin->isSearchExecutable()) {
        // Log the search.
        if ($this->config('search.settings')->get('logging')) {
          $this->logger->notice('Searched %type for %keys.', ['%keys' => $keys, '%type' => $entity->label()]);
        }

        // Collect the search results.
        $results = $plugin->buildResults();
      }
      else {
        // The search not being executable means that no keywords or other
        // conditions were entered.
        $this->messenger()->addError($this->t('Please enter some keywords.'));
      }
    }

    if (count($results)) {
      $build['search_results_title'] = [
        '#markup' => '<h2>' . $this->t('Search results') . '</h2>',
      ];
    }

    $build['search_results'] = [
      '#theme' => ['item_list__search_results__' . $plugin->getPluginId(), 'item_list__search_results'],
      '#items' => $results,
      '#empty' => [
        '#markup' => '<h3>' . $this->t('Your search yielded no results.') . '</h3>',
      ],
      '#list_type' => 'ol',
      '#context' => [
        'plugin' => $plugin->getPluginId(),
      ],
    ];

    $this->renderer->addCacheableDependency($build, $entity);
    if ($plugin instanceof CacheableDependencyInterface) {
      $this->renderer->addCacheableDependency($build, $plugin);
    }

    // If this plugin uses a search index, then also add the cache tag tracking
    // that search index, so that cached search result pages are invalidated
    // when necessary.
    if ($plugin->getType()) {
      $build['search_results']['#cache']['tags'][] = 'search_index';
      $build['search_results']['#cache']['tags'][] = 'search_index:' . $plugin->getType();
    }

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

}
