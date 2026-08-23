<?php

namespace Drupal\toc_js_per_node\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Drupal\toc_js\Plugin\Block\TocJsBlock;

/**
 * Provides a 'Toc.js' per node block.
 *
 * @Block(
 *  id = "toc_js_per_node_block",
 *  category = @Translation("Toc js"),
 *  admin_label = @Translation("Toc.js per node block"),
 * )
 */
class TocJsPerNodeBlock extends TocJsBlock {

  /**
   * The configuration key for the table of contents settings.
   *
   * @var string
   */
  protected const CUSTOM_CONFIGURATION_KEY = 'custom_configuration';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'override_nodetype' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['override_nodetype'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override node type configuration'),
      '#description' => $this->t('Override the table of contents configuration defined in the node type.'),
      '#default_value' => $this->configuration['override_nodetype'],
    ];

    $form[self::CUSTOM_CONFIGURATION_KEY] = [
      '#type' => 'details',
      '#title' => $this->t('Custom configuration'),
      '#description' => $this->t('Custom table of contents configuration specific to this block.'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="settings[override_nodetype]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $this->tocjsService->getTocForm(
      $form[self::CUSTOM_CONFIGURATION_KEY],
      $this->configuration,
      ['settings', self::CUSTOM_CONFIGURATION_KEY]
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTocFormKey(string $name): string|array {
    return [self::CUSTOM_CONFIGURATION_KEY, $name];
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['override_nodetype'] = $form_state->getValue('override_nodetype');
    parent::blockSubmit($form, $form_state);
  }

  /**
   * Get the Toc.js configuration for a specific node type.
   *
   * @param \Drupal\node\NodeTypeInterface $type
   *   The node type to get the configuration from.
   *
   * @return array
   *   The node type Toc.js configuration array.
   */
  protected function getConfigurationFromNodeType(NodeTypeInterface $type):array {
    $configuration = [];
    $configuration['toc_js_active'] = $type->getThirdPartySetting('toc_js', 'toc_js_active', FALSE);
    $settings = array_keys($this->tocjsService->defaultSettings());
    foreach ($settings as $name) {
      $configuration[$name] = $type->getThirdPartySetting('toc_js', $name);
    }
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $entity = $this->tocjsService->getTocEntity();
    if ($entity instanceof NodeInterface) {
      /** @var \Drupal\node\NodeTypeInterface $node_type */
      $node_type = $entity->__get('type')->entity;
      $toc_override = $node_type->getThirdPartySetting('toc_js_per_node', 'override', FALSE);
      $toc_override_default = $node_type->getThirdPartySetting('toc_js_per_node', 'override_default', TRUE);

      // Support toc_js per-node feature.
      if ($entity->hasField('toc_js_active') && $toc_override) {
        // Use default override value if not set.
        if ($entity->toc_js_active->value == NULL) {
          $entity->toc_js_active->value = $toc_override_default;
        }
        if (empty($entity->toc_js_active->value)) {
          return [];
        }
      }

      $toc_js_settings = $node_type->getThirdPartySettings('toc_js');
      if (empty($toc_js_settings['toc_js_active'])) {
        // Toc has been disabled but the extra field hasn't been disabled.
        return [];
      }

      $toc_settings = $this->configuration['override_nodetype']
        ? $this->configuration
        : $this->getConfigurationFromNodeType($node_type);

      return $this->tocjsService->buildToc($this->pluginId, $toc_settings);
    }

    return parent::build();
  }

}
