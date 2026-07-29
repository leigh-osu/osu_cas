<?php

namespace Drupal\styleguide\Plugin\Styleguide;

use Drupal\node\Entity\Node;
use Drupal\styleguide\Plugin\StyleguidePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Search Styleguide items implementation.
 *
 * @Plugin(
 *   id = "search_styleguide",
 *   label = @Translation("Search Styleguide elements"),
 *   provider = "search",
 * )
 */
class SearchStyleguide extends StyleguidePluginBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Renderer service to format the username and node.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The search manager.
   *
   * @var \Drupal\search\SearchPluginManager
   */
  protected $searchManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->renderer = $container->get('renderer');
    $instance->searchManager = $container->get('plugin.manager.search');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function items() {
    $items = [];

    $items['search_block_form'] = [
      'title' => $this->t('Search block form'),
      'content' => $this->formBuilder->getForm('Drupal\search\Form\SearchBlockForm'),
      'group' => $this->t('Search'),
    ];

    $items['search_no_results'] = [
      'title' => $this->t('Search, no results'),
      'content' => [
        '#theme' => 'item_list__search_results',
        '#items' => [],
        '#empty' => [
          '#markup' => '<h3>' . $this->t('Your search yielded no results.') . '</h3>',
        ],
      ],
      'group' => $this->t('Search'),
    ];

    // Generate sample results.
    $this->searchResults($items);

    return $items;
  }

  /**
   * Generate fake search results.
   *
   * @param array $items
   *   An array of the search Styleguide elements.
   */
  private function searchResults(array &$items) {
    $results = [];

    $definitions = $this->searchManager->getDefinitions();
    // If definitions has "user_search" provider,
    // we should show user for admin users.
    if (in_array('user_search', array_keys($definitions))) {
      $definitions['user_search_admin'] = [
        'id' => 'user_search_admin',
        'provider' => 'user_admin',
      ];
    }
    foreach ($definitions as $definition) {
      $search_provider = $definition['provider'];
      // Generate fake search results.
      for ($i = 0; $i < 5; $i++) {
        if ($search_provider == 'node') {
          $title = $this->t('Node Search, results');
          $result = $this->searchNodePrepare($i);
        }
        else {
          if ($search_provider == 'user_admin') {
            $title = $this->t('User Search, results (Has permission "administer users")');
            $result = [
              'title' => $this->generator->words(1) . " (user_{$i}@email.com)",
            ];
          }
          else {
            $title = $this->t('@type Search, results', ['@type' => ucfirst($search_provider)]);
            $result = [
              'title' => $this->generator->words(1),
            ];
          }
        }

        $results[$i] = [
          '#theme' => 'search_result',
          '#result' => [
            'link' => '#',
          ],
          '#plugin_id' => $definition['id'],
        ];
        $results[$i]['#result'] = array_merge($results[$i]['#result'], $result);
      }

      // Attach fake search results to the items.
      $items["{$definition['id']}_search_results"] = [
        'title' => $title,
        'content' => [
          '#theme' => 'item_list__search_results',
          '#items' => $results,
          '#empty' => [
            '#markup' => '<h3>' . $this->t('Your search yielded no results.') . '</h3>',
          ],
          '#list_type' => 'ol',
          '#context' => [
            'plugin' => $definition['id'],
          ],
        ],
        'group' => $this->t('Search'),
      ];
    }
  }

  /**
   * Helper method to prepare a fake node for the search.
   *
   * @param int $i
   *   Search row number.
   *
   * @return array
   *   Returns a prepared node for the node search sample.
   */
  private function searchNodePrepare($i) {
    // Render a fake node.
    $node = Node::create([
      'type' => 'article',
      'title' => "Title {$i}",
      'body' => $this->generator->words(20),
      'in_preview' => TRUE,
    ]);
    $build = $this->entityTypeManager
      ->getViewBuilder('node')
      ->view($node, 'search_result');
    unset($build['#theme']);
    $rendered = $this->renderer->renderInIsolation($build);

    return [
      'title' => $node->label(),
      'node' => $node,
      'extra' => $this->moduleHandler->invokeAll('node_search_result', [$node]),
      'snippet' => search_excerpt('title', $rendered),
      'date' => time(),
      'user' => [
        '#theme' => 'username',
        '#account' => $this->currentUser,
      ],
    ];
  }

}
