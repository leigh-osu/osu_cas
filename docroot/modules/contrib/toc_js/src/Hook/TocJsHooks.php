<?php

namespace Drupal\toc_js\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeTypeInterface;
use Drupal\toc_js\Service\TocJsService;

/**
 * Hook implementations for toc_js.
 */
class TocJsHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new TocJsHooks instance.
   *
   * @param \Drupal\toc_js\Service\TocJsService $tocJsService
   *   The TOC JS service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected TocJsService $tocJsService,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(
    $route_name,
    RouteMatchInterface $route_match,
  ) {
    switch ($route_name) {
      case 'help.page.toc_js':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Provide a table of content for a full node with toc.js') . '</p>';
        return $output;

      default:
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    return [
      'toc_js' => [
        'variables' => [
          'title' => NULL,
          'tag' => NULL,
          'title_attributes' => NULL,
          'attributes' => NULL,
          'entity' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter().
   */
  #[Hook('theme_suggestions_toc_js_alter')]
  public function themeSuggestionsTocJsAlter(
    array &$suggestions,
    array $variables,
  ) {
    if (isset($variables['entity']) && ($entity = $variables['entity']) instanceof EntityInterface) {
      $suggestions[] = 'toc_js__' . $entity->getEntityTypeId();
      $suggestions[] = 'toc_js__' . $entity->getEntityTypeId() . '__' . $entity->bundle();
      $suggestions[] = 'toc_js__' . $entity->getEntityTypeId() . '__' . $entity->bundle() . '__' . $entity->id();
    }
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo() {
    $extra = [];

    /** @var \Drupal\node\Entity\NodeType $bundle */
    foreach (NodeType::loadMultiple() as $bundle) {
      if ($bundle->getThirdPartySetting('toc_js', 'toc_js_active', FALSE)) {
        $extra['node'][$bundle->Id()]['display']['toc_js'] = [
          'label' => $this->t('Toc'),
          'description' => $this->t('Table of content of node generated with Toc.js'),
          'weight' => 100,
          'visible' => FALSE,
        ];
      }
    }

    return $extra;
  }

  /**
   * Implements hook_ENTITY_TYPE_view() for node entities.
   */
  #[Hook('node_view')]
  public function nodeView(
    array &$build,
    EntityInterface $entity,
    EntityViewDisplayInterface $display,
    $view_mode,
  ) {
    if ($display->getComponent('toc_js')) {
      /** @var \Drupal\node\NodeTypeInterface $node_type */
      $node_type = $entity->__get('type')->entity;
      $toc_override = $node_type->getThirdPartySetting('toc_js_per_node', 'override', FALSE);

      // Support toc_js per-node feature.
      if ($entity->hasField('toc_js_active') && $toc_override) {
        if (empty($entity->toc_js_active->value)) {
          return;
        }
      }

      $toc_js_settings = $node_type->getThirdPartySettings('toc_js');
      if (empty($toc_js_settings['toc_js_active'])) {
        return;
      }

      $toc_id = 'toc-js-' . $entity->getEntityTypeId() . '-' . $entity->id();
      $build['toc_js'] = $this->tocJsService->buildToc($toc_id, $node_type->getThirdPartySettings('toc_js'));
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for node_type_form.
   */
  #[Hook('form_node_type_form_alter')]
  public function formNodeTypeFormAlter(
    &$form,
    FormStateInterface $form_state,
  ) {
    /** @var \Drupal\node\NodeTypeInterface $type */
    $type = $form_state->getFormObject()->getEntity();

    $form['toc_js'] = [
      '#type' => 'details',
      '#group' => isset($form['additional_settings']) ? 'additional_settings' : 'advanced',
      '#title' => $this->t('Table of contents'),
      '#access' => ($this->currentUser->hasPermission('administer toc_js') || $this->currentUser->hasPermission('administer nodes')),
    ];

    $form['toc_js']['toc_js_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Table of contents'),
      '#default_value' => $type->getThirdPartySetting('toc_js', 'toc_js_active', FALSE),
    ];

    $values = [];
    $settings = $this->tocJsService->defaultSettings();
    foreach ($settings as $name => $default) {
      $values[$name] = $type->getThirdPartySetting('toc_js', $name, $default);
    }

    $states = [
      'visible' => [
        ':input[name="toc_js_active"]' => ['checked' => TRUE],
      ],
    ];

    $this->tocJsService->getTocForm($form['toc_js'], $values, NULL, $states);

    $form['#entity_builders'][] = [
      self::class,
      'formNodeTypeFormBuilder',
    ];
  }

  /**
   * Entity builder for the node type form with TOC option.
   */
  public static function formNodeTypeFormBuilder(
    $entity_type,
    NodeTypeInterface $type,
    &$form,
    FormStateInterface $form_state,
  ) {
    $type->setThirdPartySetting('toc_js', 'toc_js_active', $form_state->getValue('toc_js_active'));
    /** @var \Drupal\toc_js\Service\TocJsService $tocJsService */
    $tocJsService = \Drupal::service('toc_js.service');
    $settings = array_keys($tocJsService->defaultSettings());
    foreach ($settings as $name) {
      $type->setThirdPartySetting('toc_js', $name, $form_state->getValue($name));
    }
  }

}
