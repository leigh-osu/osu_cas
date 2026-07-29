<?php

namespace Drupal\domain_config_entity_ui\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\domain_config_entity_ui\DomainAwareSwapRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the Domain Config Entity UI submodule.
 *
 * Lets the user pick which config entity types this submodule should
 * cover. Options come from DomainAwareSwapRegistry::getSwaps(): every
 * config entity type whose default storage_class is core's
 * ConfigEntityStorage is auto-discovered, and contrib modules can
 * register additional entries via
 * hook_domain_config_entity_ui_swaps_alter(). The user's choice is
 * stored in domain_config_entity_ui.settings.covered_entity_types and
 * consumed at entity type discovery time by
 * DomainConfigEntityUiEntityTypeHooks::entityTypeAlter().
 *
 * Saving flips the entity type definitions cache via
 * DomainConfigEntityUiSettingsSubscriber so the new selection takes
 * effect on the next request without a manual `drush cr`.
 */
class SettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DomainAwareSwapRegistry $swapRegistry,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get(DomainAwareSwapRegistry::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_config_entity_ui_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['domain_config_entity_ui.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $options = [];
    foreach ($this->swapRegistry->getSwaps() as $entity_type_id => $_) {
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        // The entity type's providing module is not enabled — hide the
        // option rather than offer something the user could check but
        // never have any effect.
        continue;
      }
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $options[$entity_type_id] = $this->t('@label (@id)', [
        '@label' => $entity_type->getLabel(),
        '@id' => $entity_type_id,
      ]);
    }

    $form['warning'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--warning']],
      '#weight' => -100,
      'message' => [
        '#markup' => $this->t("<strong>Enable only if you know what you are doing.</strong> Only %canonical has been validated for per-domain overrides; every other entity type below is auto-discovered and may behave unexpectedly when overridden (text formats, languages, encryption keys, …). Test on a non-production environment before enabling.", [
          '%canonical' => 'block',
        ]),
      ],
    ];

    $form['covered_entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Config entity types with per-domain support'),
      '#description' => $this->t("Select the config entity types whose admin pages should expose the per-domain configuration toggle. Every config entity type whose default storage handler is core's ConfigEntityStorage is listed; types with custom storage handlers (image styles, roles, menus, …) only appear if a contrib module registers a sibling DomainAware* subclass via hook_domain_config_entity_ui_swaps_alter()."),
      '#options' => $options,
      '#config_target' => new ConfigTarget(
        'domain_config_entity_ui.settings',
        'covered_entity_types',
        fromConfig: static fn(array $stored): array => array_combine($stored, $stored),
        toConfig: static fn(array $values): array => array_values(array_filter($values)),
      ),
    ];
    if (empty($options)) {
      $form['covered_entity_types']['#description'] = $this->t('No covered entity type definitions are currently registered. Install at least one module that provides a covered entity type (block, …).');
    }

    return parent::buildForm($form, $form_state);
  }

}
