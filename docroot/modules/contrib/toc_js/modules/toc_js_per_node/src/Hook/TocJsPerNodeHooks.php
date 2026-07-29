<?php

namespace Drupal\toc_js_per_node\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeTypeInterface;

/**
 * Hook implementations for toc_js_per_node.
 */
class TocJsPerNodeHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new TocJsPerNodeHooks instance.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
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
      case 'help.page.toc_js_per_node':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Allow to override Toc.js settings per node') . '</p>';
        return $output;

      default:
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
   * Implements hook_form_FORM_ID_alter() for node_type_form.
   */
  #[Hook('form_node_type_form_alter')]
  public function formNodeTypeFormAlter(
    &$form,
    FormStateInterface $form_state,
  ) {
    /** @var \Drupal\node\NodeTypeInterface $type */
    $type = $form_state->getFormObject()->getEntity();

    $form['toc_js']['override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Permit to enable/disable toc per node'),
      '#default_value' => $type->getThirdPartySetting('toc_js_per_node', 'override', FALSE),
      '#states' => [
        'visible' => [
          ':input[name="toc_js_active"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => 100,
    ];

    $form['toc_js']['override_default'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default state for table of contents per node'),
      '#default_value' => (int) $type->getThirdPartySetting('toc_js_per_node', 'override_default', TRUE),
      '#options' => [
        1 => $this->t('Enabled'),
        0 => $this->t('Disabled'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="toc_js_active"]' => ['checked' => TRUE],
          ':input[name="override"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => 100,
    ];

    $form['#entity_builders'][] = [
      self::class,
      'formNodeTypeFormBuilder',
    ];
  }

  /**
   * Entity builder for the node type form with per-node option.
   */
  public static function formNodeTypeFormBuilder(
    $entity_type,
    NodeTypeInterface $type,
    &$form,
    FormStateInterface $form_state,
  ) {
    $type->setThirdPartySetting('toc_js_per_node', 'override', $form_state->getValue('override'));
    $type->setThirdPartySetting('toc_js_per_node', 'override_default', $form_state->getValue('override_default'));
  }

  /**
   * Implements hook_entity_bundle_field_info().
   */
  #[Hook('entity_bundle_field_info')]
  public function entityBundleFieldInfo(
    EntityTypeInterface $entity_type,
    $bundle,
    array $base_field_definitions,
  ) {
    if ($entity_type->id() !== 'node' || !isset($base_field_definitions['toc_js_active'])) {
      return [];
    }

    $node_type = NodeType::load($bundle);
    if (!$node_type
      || !$node_type->getThirdPartySetting('toc_js_per_node', 'override', FALSE)) {
      return [];
    }

    $override_default = (bool) $node_type->getThirdPartySetting('toc_js_per_node', 'override_default', TRUE);

    $base_field = $base_field_definitions['toc_js_active'];
    $current_default = $base_field->getDefaultValueLiteral();

    if ($override_default === (bool) ($current_default[0]['value'] ?? FALSE)) {
      return [];
    }

    $field = clone $base_field;
    $field->setDefaultValue($override_default);

    return ['toc_js_active' => $field];
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type) {
    $fields = [];

    if ($entity_type->id() === 'node') {
      $fields['toc_js_active'] = BaseFieldDefinition::create('boolean')
        ->setLabel($this->t('Toc active'))
        ->setName('toc_js_active')
        ->setRevisionable(TRUE)
        ->setTranslatable(TRUE)
        ->setDescription($this->t('Stores whether the Toc is displayed or not.'))
        ->setDefaultValue(FALSE);
    }

    return $fields;
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node_form.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(
    &$form,
    FormStateInterface &$form_state,
  ) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = $node->type->entity;
    $toc_active = $node_type->getThirdPartySetting('toc_js', 'toc_js_active', FALSE);
    $toc_override = $node_type->getThirdPartySetting('toc_js_per_node', 'override', FALSE);

    if ($toc_active && $toc_override) {
      $toc_active_value = (bool) $node->toc_js_active->value;

      if ($this->currentUser->hasPermission('administer toc_js per node') ||
        $this->currentUser->hasPermission('administer nodes')) {

        $form['toc_js'] = [
          '#type' => 'details',
          '#group' => isset($form['additional_settings']) ? 'additional_settings' : 'advanced',
          '#title' => $this->t('Table of contents'),
          '#weight' => 7,
          '#access' => TRUE,
          '#attached' => [
            'library' => ['toc_js_per_node/summary'],
          ],
        ];

        $form['toc_js']['toc_js_active'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Display a table of contents'),
          '#attributes' => ['title' => $this->t('When unchecked, the table of contents no longer displays.')],
          '#description' => $this->t("Generates an automatic table of contents based on this node's content."),
          '#group' => 'toc_js',
          '#default_value' => $toc_active_value,
        ];
      }

      else {
        $form['toc_js_active'] = [
          '#type' => 'value',
          '#value' => $toc_active_value,
        ];
      }
    }

    else {
      $form['toc_js_active'] = [
        '#type' => 'value',
        '#value' => NULL,
      ];
    }
  }

}
