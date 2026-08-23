<?php

namespace Drupal\block_visibility_groups\Entity;

use Drupal\block_visibility_groups\BlockVisibilityGroupInterface;
use Drupal\Component\Plugin\LazyPluginCollection;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Block Visibility Group entity.
 *
 * @ConfigEntityType(
 *   id = "block_visibility_group",
 *   label = @Translation("Block Visibility Group"),
 *   handlers = {
 *     "list_builder" =
 *     "Drupal\block_visibility_groups\Controller\BlockVisibilityGroupListBuilder",
 *     "form" = {
 *       "add" =
 *       "Drupal\block_visibility_groups\Form\BlockVisibilityGroupForm",
 *       "edit" =
 *       "Drupal\block_visibility_groups\Form\BlockVisibilityGroupForm",
 *       "delete" =
 *       "Drupal\block_visibility_groups\Form\BlockVisibilityGroupDeleteForm"
 *     }
 *   },
 *   config_prefix = "block_visibility_group",
 *   admin_permission = "administer block visibility groups",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "logic",
 *     "conditions",
 *     "allow_other_conditions",
 *   },
 *   links = {
 *     "edit-form" =
 *     "/admin/structure/block/block-visibility-group/{block_visibility_group}/edit",
 *     "delete-form" =
 *     "/admin/structure/block/block-visibility-group/{block_visibility_group}/delete",
 *     "collection" =  "/admin/structure/block/block-visibility-group"
 *   }
 * )
 */
class BlockVisibilityGroup extends ConfigEntityBase implements BlockVisibilityGroupInterface {

  /**
   * The Block Visibility Group ID.
   *
   * @var ?string
   */
  protected ?string $id = NULL;

  /**
   * The Block Visibility Group label.
   *
   * @var ?string
   */
  protected ?string $label = NULL;

  /**
   * Whether other conditions are allowed in the group.
   *
   * @var bool
   */
  protected bool $allow_other_conditions = FALSE;

  /**
   * Whether other conditions are allowed in the group.
   *
   * @return bool
   *   True if conditions are allowed.
   */
  public function isAllowOtherConditions(): bool {
    return $this->allow_other_conditions;
  }

  /**
   * Sets whether other conditions should be allowed.
   *
   * @param bool $allow_other_conditions
   *   Whether other conditions should be allowed.
   */
  public function setAllowOtherConditions(bool $allow_other_conditions): void {
    $this->allow_other_conditions = $allow_other_conditions;
  }

  /**
   * The configuration of conditions.
   *
   * @var array
   */
  protected array $conditions = [];

  /**
   * Tracks the logic used to compute, either 'and' or 'or'.
   *
   * @var string
   */
  protected string $logic = 'and';

  /**
   * Gets logic used to compute, either 'and' or 'or'.
   *
   * @return string
   *   Either 'and' or 'or'.
   */
  public function getLogic(): string {
    return $this->logic;
  }

  /**
   * Sets logic used to compute, either 'and' or 'or'.
   *
   * @param string $logic
   *   Either 'and' or 'or'.
   */
  public function setLogic(string $logic): void {
    $this->logic = $logic;
  }

  /**
   * The plugin collection that holds the conditions.
   *
   * @var \Drupal\Component\Plugin\LazyPluginCollection|null
   */
  protected ?LazyPluginCollection $conditionCollection = NULL;

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    return [
      'conditions' => $this->getConditions(),
    ];
  }

  /**
   * Returns the conditions.
   *
   * @return \Drupal\Core\Condition\ConditionPluginCollection
   *   An array of configured condition plugins.
   */
  public function getConditions(): ConditionPluginCollection {
    if (!isset($this->conditionCollection)) {
      $this->conditionCollection = new ConditionPluginCollection(\Drupal::service('plugin.manager.condition'), $this->get('conditions'));
    }
    return $this->conditionCollection;
  }

  /**
   * Returns a condition by its ID.
   *
   * @param string $condition_id
   *   The id of the condition to return.
   *
   * @return \Drupal\Core\Condition\ConditionInterface
   *   The condition plugin.
   */
  public function getCondition(string $condition_id): ConditionInterface {
    return $this->getConditions()->get($condition_id);
  }

  /**
   * Adds a condition to the group.
   *
   * @param array $configuration
   *   A configuration condition to add to the entity.
   *
   * @return string
   *   The condition uuid.
   */
  public function addCondition(array $configuration): string {
    $configuration['uuid'] = $this->uuidGenerator()->generate();
    $this->getConditions()
      ->addInstanceId($configuration['uuid'], $configuration);
    return $configuration['uuid'];
  }

  /**
   * Remove a condition from the group.
   *
   * @param string $condition_id
   *   The condition ID to remove.
   *
   * @return $this
   */
  public function removeCondition(string $condition_id): static {
    $this->getConditions()->removeInstanceId($condition_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $tags = parent::getCacheTags();
    foreach ($this->getConditions() as $condition) {
      $tags = Cache::mergeTags($tags, $condition->getCacheTags());
    }
    return Cache::mergeTags($tags, ['block_visibility_group:' . $this->id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $contexts = parent::getCacheContexts();
    foreach ($this->getConditions() as $condition) {
      $contexts = Cache::mergeContexts($contexts, $condition->getCacheContexts());
    }
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    $age = parent::getCacheMaxAge();
    foreach ($this->getConditions() as $condition) {
      $age = Cache::mergeMaxAges($age, $condition->getCacheMaxAge());
    }
    return $age;
  }

}
