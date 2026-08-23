<?php

namespace Drupal\group_content_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a generic Menu block.
 *
 * @Block(
 *   id = "group_content_menu",
 *   admin_label = @Translation("Group Menu"),
 *   category = @Translation("Group Menus"),
 *   deriver = "Drupal\group_content_menu\Plugin\Derivative\GroupMenuBlock",
 *   context_definitions = {
 *     "group" = @ContextDefinition("entity:group", required = FALSE)
 *   }
 * )
 */
class GroupMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The menu name.
   *
   * @var string
   */
  protected $menuName;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The active menu trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * GroupMenuBlock constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuTree = $menu_tree;
    $this->menuActiveTrail = $menu_active_trail;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;

    $defaults = $this->defaultConfiguration();
    $form['menu_levels'] = [
      '#type' => 'details',
      '#title' => $this->t('Menu levels'),
      // Open if not set to defaults.
      '#open' => $defaults['level'] !== $config['level'] || $defaults['depth'] !== $config['depth'],
      '#process' => [[self::class, 'processMenuLevelParents']],
    ];

    $options = range(0, $this->menuTree->maxDepth());
    unset($options[0]);

    $form['menu_levels']['level'] = [
      '#type' => 'select',
      '#title' => $this->t('Initial menu level'),
      '#default_value' => $config['level'],
      '#options' => $options,
      '#description' => $this->t('The menu will only be visible if the menu item for the current page is at or below the selected starting level. Select level 1 to always keep this menu visible.'),
      '#required' => TRUE,
    ];

    $options[0] = $this->t('Unlimited');

    $form['menu_levels']['depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Maximum number of menu levels to display'),
      '#default_value' => $config['depth'],
      '#options' => $options,
      '#description' => $this->t('The maximum number of menu levels to show, starting from the initial menu level. For example: with an initial level 2 and a maximum number of 3, menu levels 2, 3 and 4 can be displayed.'),
      '#required' => TRUE,
    ];

    $form['menu_levels']['expand_all_items'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expand all menu items'),
      '#default_value' => !empty($config['expand_all_items']),
      '#description' => $this->t('Override the option found on each menu link used for expanding children and instead display the whole menu tree as expanded.'),
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced options'),
      '#open' => FALSE,
    ];

    $form['advanced']['relative_visibility'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Make the visibility level and depth relative to the active menu item.</strong>'),
      '#default_value' => $config['relative_visibility'],
      '#description' => $this->t('If the active menu item is deeper than the initial visibility level set above, the visibility level will be relative to the depth of the active menu item. Otherwise, the visibility level of the tree will be fixed relative to the menu root.'),
    ];

    $theme_hook_suggestion = $config['theme_hook_suggestion'];
    if (!empty($theme_hook_suggestion)) {
      $description = $this->t("A unique machine-readable string that can be used to override this block's template. To preprocess this block, use <code>@hook_name</code>.", [
        '@hook_name' => 'mytheme_preprocess_menu__group_menu__' . $theme_hook_suggestion,
      ]);
    }
    else {
      $description = $this->t("A unique machine-readable string that can be used to override this block's template.");
    }
    $form['advanced']['theme_hook_suggestion'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Theme hook suggestion'),
      '#default_value' => $theme_hook_suggestion,
      '#description' => $description,
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [$this, 'themeHookSuggestionExists'],
        'standalone' => TRUE,
      ],
    ];

    return $form;
  }

  /**
   * Callback for the "theme_hook_suggestion" machine name form element.
   *
   * @return bool
   *   Always FALSE in order to allow the theme suggestion to be re-used between
   *   multiple blocks.
   */
  public function themeHookSuggestionExists(): bool {
    return FALSE;
  }

  /**
   * Form API callback: Processes the menu_levels field element.
   *
   * Adjusts the #parents of menu_levels to save its children at the top level.
   */
  public static function processMenuLevelParents(&$element, FormStateInterface $form_state, &$complete_form) {
    array_pop($element['#parents']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['level'] = $form_state->getValue('level');
    $this->configuration['depth'] = $form_state->getValue('depth');
    $this->configuration['expand_all_items'] = $form_state->getValue('expand_all_items');
    $this->configuration['relative_visibility'] = (bool) $form_state->getValue(['advanced', 'relative_visibility']);
    $this->configuration['theme_hook_suggestion'] = $form_state->getValue(['advanced', 'theme_hook_suggestion']);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $menu_name = $this->getMenuName();
    // If unable to determine the menu, prevent the block from rendering.
    if (!$menu_name = $this->getMenuName()) {
      return [];
    }

    // Get block configuration and menu tree parameters.
    $level = $this->configuration['level'];
    $depth = $this->configuration['depth'];
    $relative_visibility = $this->configuration['relative_visibility'] ?? FALSE;
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);

    // Adjust the menu tree parameters based on the block's configuration.
    $parameters->setMinDepth($level);

    // Adjust menu root in cases where the active menu item is below root and
    // only a subset of the full menu is to be shown, or possibly not at all.
    $relative_level = count($parameters->activeTrail);
    if ($level > 1 || ($relative_visibility && $relative_level > $level)) {
      $menu_trail_ids = array_reverse(array_values($parameters->activeTrail));
      // For relative visibility we reset the root relative to the active
      // menu item.
      if ($relative_visibility) {
        $menu_root = $menu_trail_ids[$relative_level - 2];
        $parameters->setRoot($menu_root);
      }
      // For absolute visibility, we reset root relative to the original and
      // adjust the minimum depth.
      elseif ($relative_level >= $level) {
        $menu_root = $menu_trail_ids[$level - 1];
        $parameters->setRoot($menu_root)->setMinDepth(1);
      }
      // If the active menu item is not at or above the visibility level, and
      // relative visibility is not in play, then do not show the menu.
      else {
        return [];
      }
    }

    // When the depth is configured to zero, there is no depth limit. When depth
    // is non-zero, it indicates the number of levels that must be displayed.
    // Hence, this is a relative depth that we must convert to an actual
    // (absolute) depth, that may never exceed the maximum depth.
    if ($depth > 0) {
      $relative_depth = $relative_visibility ? $level + $depth - 1 : $depth;
      $parameters->setMaxDepth(\min($relative_depth, $this->menuTree->maxDepth()));
    }

    // If expandedParents is empty, the whole menu tree is built.
    if ($this->configuration['expand_all_items']) {
      $parameters->expandedParents = [];
    }

    $tree = $this->menuTree->load($menu_name, $parameters);
    $tree = $this->menuTree->transform($tree, $this->getMenuManipulators());
    $build = $this->menuTree->build($tree);
    $menu_instance = $this->getMenuInstance();
    $build['#group_content_menu'] = [
      'menu_name' => $menu_name,
      'theme_hook_suggestion' => $this->configuration['theme_hook_suggestion'],
    ];
    if ($menu_instance instanceof GroupContentMenuInterface) {
      $build['#group_content_menu']['group_content_menu_type'] = $menu_instance->bundle();
      $build['#contextual_links']['group_menu'] = [
        'route_parameters' => [
          'group' => $this->getContext('group')->getContextData()->getValue()->id(),
          'group_content_menu' => $menu_instance->id(),
        ],
      ];

    }
    $build['#theme'] = 'menu';
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'level' => 1,
      'depth' => 0,
      'expand_all_items' => FALSE,
      'relative_visibility' => FALSE,
      'theme_hook_suggestion' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    if ($menu_name = $this->getMenuName()) {
      return Cache::mergeTags($tags, [$menu_name]);
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Vary by the active group as found by RouteGroupCacheContext.
    $contexts = ['route.group'];
    // If menu is defined add cacheable dependency.
    if ($menu_name = $this->getMenuName()) {
      // We use MenuLinkTreeInterface::getCurrentRouteMenuTreeParameters() to
      // generate menu tree parameters, and those take the active menu trail
      // into account. Therefore, we must vary the rendered menu by the active
      // trail of the rendered menu. Additional cache contexts, e.g. those
      // that determine link text or accessibility of a menu, will be bubbled
      // automatically.
      $contexts[] = 'route.menu_active_trails:' . $menu_name;
    }
    return Cache::mergeContexts(parent::getCacheContexts(), $contexts);
  }

  /**
   * Gets the menu instance for the current group.
   *
   * @return \Drupal\group_content_menu\GroupContentMenuInterface|null
   *   The instance of the menu or null if no instance is found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMenuInstance() {
    $entity = $this->getContext('group')->getContextData()->getValue();
    // Don't load menu for group entities that are new/unsaved.
    if (!$entity || $entity->isNew()) {
      return NULL;
    }

    $group_relationship_storage = $this->entityTypeManager->getStorage('group_content');
    assert($group_relationship_storage instanceof GroupRelationshipStorageInterface);
    $plugin_id = $group_relationship_storage->loadByPluginId($this->getPluginId());

    if (empty($plugin_id)) {
      return NULL;
    }

    $instances = $group_relationship_storage->loadByGroup($entity, $this->getPluginId());
    if ($instances) {
      return array_pop($instances)->getEntity();
    }
    return NULL;
  }

  /**
   * Returns a name for the menu.
   *
   * @return string
   *   The name of the menu.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMenuName() {
    if (isset($this->menuName)) {
      return $this->menuName;
    }
    $instance = $this->getMenuInstance();
    if ($instance) {
      $this->menuName = GroupContentMenuInterface::MENU_PREFIX . $instance->id();
    }
    return $this->menuName;
  }

  /**
   * The menu link tree manipulators to apply.
   *
   * @see Drupal\Core\Menu\MenuLinkTreeInterface::transform()
   */
  protected function getMenuManipulators(): array {
    return [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
  }

}
