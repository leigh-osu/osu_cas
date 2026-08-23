<?php

namespace Drupal\group_content_menu\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Group Menu' condition.
 *
 * @Condition(
 *   id = "group_content_menu",
 *   label = @Translation("Group content menu"),
 *   context_definitions = {
 *     "group" = @ContextDefinition("entity:group", required = FALSE, label = @Translation("Group"))
 *   }
 * )
 */
final class GroupContentMenu extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['type' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $options = [
      '' => $this->t('None'),
    ];

    foreach ($this->entityTypeManager->getStorage('group_content_menu_type')->loadMultiple() as $menuType) {
      $options[$menuType->id()] = $menuType->label();
    }
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Group content menu type'),
      '#description' => $this->t('When the group in the context has an instance of this menu type the condition will evaluate to TRUE.'),
      '#default_value' => $this->configuration['type'],
      '#options' => $options,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['type'] = $form_state->getValue('type');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): string {
    return (string) $this->t(
      'Group menu type: @type', ['@type' => $this->configuration['type']],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    return (bool) $this->getMenuInstance();
  }

  /**
   * Gets the menu instance for the current group.
   *
   * Copied from GroupMenuBlock replacing getPluginId for getPluginIdWithType.
   *
   * @return \Drupal\group_content_menu\GroupContentMenuInterface|null
   *   The instance of the menu or null if no instance is found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMenuInstance(): ?GroupContentMenuInterface {
    $entity = $this->getContext('group')->getContextData()->getValue();
    // Don't load menu for group entities that are new/unsaved.
    if (!$entity || $entity->isNew()) {
      return NULL;
    }

    $group_relationship_storage = $this->entityTypeManager->getStorage('group_relationship');
    assert($group_relationship_storage instanceof GroupRelationshipStorageInterface);
    $plugin_id = $group_relationship_storage->loadByPluginId($this->getPluginIdWithType());

    if (empty($plugin_id)) {
      return NULL;
    }

    $instances = $group_relationship_storage->loadByGroup($entity, $this->getPluginIdWithType());
    if ($instances) {
      return array_pop($instances)->getEntity();
    }
    return NULL;
  }

  /**
   * Method that build the plugin ID for the group relationship.
   *
   * Since getMenuInstance is a copy of the method from GroupMenuBlock
   * that uses plugin derivatives, this method generates plugin ids
   * adding the derivative part, being the menu type chosen for the
   * visibility, for example "group_content_menu:private".
   */
  protected function getPluginIdWithType() {
    return $this->getPluginId() . ':' . $this->configuration['type'];
  }

}
