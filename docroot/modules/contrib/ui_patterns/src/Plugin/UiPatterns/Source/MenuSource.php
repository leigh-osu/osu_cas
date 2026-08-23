<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'menu',
  label: new TranslatableMarkup('Menu'),
  description: new TranslatableMarkup('Provides a generic Menu source..'),
  prop_types: ['links']
)]
class MenuSource extends SourcePluginBase {

  use MenuTreeSourceTrait;

  /**
   * The menu ID.
   *
   * @var string
   */
  protected $menuId;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $plugin = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $plugin->menuLinkTree = $container->get('menu.link_tree');
    $plugin->entityTypeManager = $container->get('entity_type.manager');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'menu' => NULL,
      'level' => 1,
      'depth' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $menu_id = $this->getSetting('menu');
    if (!$menu_id) {
      return [];
    }
    $this->menuId = $menu_id;
    return $this->buildMenuTree($menu_id, (int) $this->getSetting('level'), (int) $this->getSetting('depth'));
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['menu'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu'),
      '#options' => ['' => '(None)'] + $this->getMenuList(),
      '#default_value' => $this->getSetting('menu'),
    ];
    $options = range(0, $this->menuLinkTree()->maxDepth());
    unset($options[0]);
    $form['level'] = [
      '#type' => 'select',
      '#title' => $this->t('Initial visibility level'),
      '#default_value' => $this->getSetting('level'),
      '#options' => $options,
    ];
    $options[0] = $this->t('Unlimited');
    $form['depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of levels to display'),
      '#default_value' => $this->getSetting('depth'),
      '#options' => $options,
      '#description' => $this->t(
        'This maximum number includes the initial level and the final display is dependant of the component template.'
      ),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterComponent(array $element): array {
    if (!$this->menuId) {
      return $element;
    }
    return $this->addMenuCacheTags($element, [$this->menuId]);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $menu_id = $this->getSetting('menu');
    if (!$menu_id) {
      return $dependencies;
    }
    $this->menuConfigDependencies($dependencies, [$menu_id]);
    return $dependencies;
  }

}
