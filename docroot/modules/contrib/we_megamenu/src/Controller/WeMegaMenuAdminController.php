<?php

namespace Drupal\we_megamenu\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\we_megamenu\WeMegaMenuBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\State;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Database\Connection;
use Drupal\system\Entity\Menu;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller routines for block example routes.
 */
class WeMegaMenuAdminController extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Request Stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state store.
   *
   * @var Drupal\Core\State\State
   */
  protected $state;

  /**
   * Drupal\Core\Render\RendererInterface definition.
   *
   * @var Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs the WeMegaMenuAdminController.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request Stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\State\State $state
   *   The state manager.
   * @param Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, RequestStack $requestStack, EntityTypeManagerInterface $entityTypeManager, State $state, RendererInterface $renderer, Connection $database) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->requestStack = $requestStack;
    $this->entityTypeManager = $entityTypeManager;
    $this->state = $state;
    $this->renderer = $renderer;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('state'),
      $container->get('renderer'),
      $container->get('database')
    );
  }

  /**
   * A method to clear cache based on menu tags.
   *
   * @param string $menu_name
   *   The name of the menu.
   */
  public function invalidateMenusCache($menu_name) {
    Cache::invalidateTags(['config:system.menu.' . $menu_name]);
  }

  /**
   * Returns the page title for megamenu config page.
   *
   * @param string $menu_name
   *   The name of menu.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title of matching config page.
   */
  public function getConfigPageTitle($menu_name) {
    $menu = Menu::load($menu_name);
    return $this->t('Mega Menu: @menu', ['@menu' => $menu->label()]);
  }

  /**
   * A function build page backend.
   *
   * @param string $menu_name
   *   Public function configWeMegaMenu menu_name.
   *
   * @return array[markup]
   *   Public function configWeMegaMenu string.
   */
  public function configWeMegaMenu($menu_name) {
    // $tree = WeMegaMenuBuilder::getMenuTreeOrder($menu_name).
    $build = [];
    $build['we_megamenu'] = [
      '#theme' => 'we_megamenu_backend',
      '#menu_name' => $menu_name,
      // '#items' => $tree,
      '#blocks' => WeMegaMenuBuilder::getAllBlocks(),
      '#block_theme' => $this->configFactory->get('system.theme')->get('default'),
    ];

    $build['we_megamenu']['#attached']['library'][] = 'we_megamenu/form.we-mega-menu-backend';

    // Check if using HTTPS for paths.
    $opts = ['absolute' => TRUE];
    if (
      (isset($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], 'https://')) ||
      (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
      )) {
      $opts['https'] = TRUE;
    }
    $abs_url_save_config = Url::fromRoute('we_megamenu.admin.save', [], $opts)->toString();
    $abs_url_reset_config = Url::fromRoute('we_megamenu.admin.reset', [], $opts)->toString();
    $abs_url_icons_config = Url::fromRoute('we_megamenu.geticons', [], $opts)->toString();

    $build['#attached']['drupalSettings']['WeMegaMenu']['saveConfigWeMegaMenuURL'] = $abs_url_save_config;
    $build['#attached']['drupalSettings']['WeMegaMenu']['resetConfigWeMegaMenuURL'] = $abs_url_reset_config;
    $build['#attached']['drupalSettings']['WeMegaMenu']['iconsWeMegaMenuURL'] = $abs_url_icons_config;
    return $build;
  }

  /**
   * A function ajax save menu config.
   */
  public function saveConfigWeMegaMenu() {
    $action = $this->requestStack->getCurrentRequest()->request->get('action');
    if (isset($action) && $action == 'save') {
      $data_config = $this->requestStack->getCurrentRequest()->request->get('data_config');
      $theme = $this->requestStack->getCurrentRequest()->request->get('theme');
      $menu_name = Xss::filter($this->requestStack->getCurrentRequest()->request->get('menu_name'));
      WeMegaMenuBuilder::saveConfig($menu_name, $theme, $data_config);
      $this->invalidateMenusCache($menu_name);
    }
    exit;
  }

  /**
   * A function reset menu config.
   */
  public function resetConfigWeMegaMenu() {
    $action = $this->requestStack->getCurrentRequest()->request->get('action');
    $menu_name = $this->requestStack->getCurrentRequest()->request->get('menu_name');
    $theme = $this->requestStack->getCurrentRequest()->request->get('theme');
    if (isset($action) && $action == 'reset' && isset($menu_name) && isset($theme)) {
      $theme_array = WeMegaMenuBuilder::renderWeMegaMenuBlock($menu_name, $theme);
      $markup = $this->renderer->render($theme_array);
      echo $markup;
      $this->invalidateMenusCache(Xss::filter($menu_name));
      exit;
    }

    if (isset($action) && $action == 'reset-to-default' && isset($menu_name) && isset($theme)) {
      $query = $this->database->delete('we_megamenu');
      $query->condition('menu_name', $menu_name);
      $query->condition('theme', $theme);
      $query->execute();
      WeMegaMenuBuilder::initMegamenu($menu_name, $theme);
      $theme_array = WeMegaMenuBuilder::renderWeMegaMenuBlock($menu_name, $theme);
      $markup = $this->renderer->render($theme_array);
      echo $markup;
      $this->invalidateMenusCache(Xss::filter($menu_name));
      exit;
    }
    exit;
  }

  /**
   * A function set style backend.
   */
  public function styleOfBackendWeMegaMenu() {
    $type = $this->requestStack->getCurrentRequest()->request->get('type');
    $menu_name = $this->requestStack->getCurrentRequest()->request->get('menu_name');
    if (isset($type)) {
      $this->state->set('we_megamenu_backend_style', $type);
      $this->invalidateMenusCache(Xss::filter($menu_name));
    }
    exit;
  }

  /**
   * Render block from post variable ajax.
   */
  public function renderBlock() {
    $bid = $this->requestStack->getCurrentRequest()->request->get('bid');
    $section = $this->requestStack->getCurrentRequest()->request->get('section');
    $postTitle = $this->requestStack->getCurrentRequest()->request->get('title');
    $title = TRUE;
    if ($postTitle == 0) {
      $title = FALSE;
    }

    if (isset($bid) && isset($section) && !empty($bid)) {
      echo WeMegaMenuBuilder::renderBlock($bid, $title, isset($section));
    }
    else {
      echo '';
    }
    exit;
  }

  /**
   * Render page list menu backend.
   */
  public function listWeMegaMenus() {
    $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple();
    $rows = [];
    foreach ($menus as $menu) {
      $row = [
        'menu-name' => $menu->id(),
        'menu-title' => $menu->label(),
      ];

      $dropbuttons = [
        '#type' => 'operations',
        '#links' => [
          'config' => [
            'url' => new Url('we_megamenu.admin.configure', ['menu_name' => $menu->id()]),
            'title' => 'Config',
          ],
          'edit' => [
            'url' => new Url('entity.menu.edit_form', ['menu' => $menu->id()]),
            'title' => 'Edit links',
          ],
        ],
      ];
      $row['menu-operations'] = ['data' => $dropbuttons];
      $rows[] = $row;
    }
    $header = [
      'menu-machine-name' => $this->t('Machine Name'),
      'menu-name' => $this->t('Menu Name'),
      'menu-options' => $this->t('Options'),
    ];

    return [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No Mega Menu block available. <a href="@link">Add Menu</a>.', ['@link' => Url::fromRoute('entity.menu.add_form')->toString()]),
      '#attributes' => ['id' => 'we_megamenu'],
    ];
  }

  /**
   * Render list icon font awesome.
   */
  public function getIcons() {
    $file = DRUPAL_ROOT . '/' . $this->moduleHandler->getModule('we_megamenu')->getPath() . '/assets/resources/icon.wemegamenu';
    $fh = fopen($file, 'r');
    $result = [];
    while ($line = fgets($fh)) {
      $result[] = trim($line);
    }
    fclose($fh);
    echo json_encode($result);
    exit;
  }

}
