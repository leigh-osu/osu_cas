<?php

namespace Drupal\block_visibility_groups\Plugin\Condition;

use Drupal\block_visibility_groups\GroupEvaluator;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'Condition Group' condition.
 */
#[Condition(
  id: 'condition_group',
  label: new TranslatableMarkup('Condition Group'),
)]
class ConditionGroup extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  use DependencyTrait;

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Executable\ExecutableManagerInterface
   */
  protected ExecutableManagerInterface $manager;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $entityStorage;

  /**
   * The request stack object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The group evaluator.
   *
   * @var \Drupal\block_visibility_groups\GroupEvaluator
   */
  protected GroupEvaluator $groupEvaluator;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityStorageInterface $entity_storage, ExecutableManagerInterface $manager, RequestStack $request_stack, GroupEvaluator $group_evaluator, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->manager = $manager;
    $this->entityStorage = $entity_storage;
    $this->requestStack = $request_stack;
    $this->groupEvaluator = $group_evaluator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('entity_type.manager')->getStorage('block_visibility_group'),
      $container->get('plugin.manager.condition'),
      $container->get('request_stack'),
      $container->get('block_visibility_groups.group_evaluator'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if (empty($this->configuration['block_visibility_group'])) {
      return TRUE;
    }
    /** @var \Drupal\block_visibility_groups\Entity\BlockVisibilityGroup $block_visibility_group */
    $block_visibility_group = $this->entityStorage->load($this->configuration['block_visibility_group']);
    if ($block_visibility_group) {
      return $this->groupEvaluator->evaluateGroup($block_visibility_group);
    }
    // Group doesn't exist.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): TranslatableMarkup {
    if (!empty($this->configuration['block_visibility_group'])) {
      $group = $this->entityStorage->load($this->configuration['block_visibility_group']);
      if ($group) {
        return $this->t('Block Visibility Group: @group', ['@group' => $group->label()]);
      }
    }
    return $this->t('No Block Visibility Group');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $block_visibility_groups = $this->entityStorage->loadMultiple();
    $options = ['' => $this->t('No Block Visibility Group')];
    foreach ($block_visibility_groups as $type) {
      $options[$type->id()] = $type->label();
    }

    $form['block_visibility_group'] = [
      '#title' => $this->t('Block Visibility Groups'),
      '#type' => 'select',
      '#options' => $options,
    ];
    $default = $this->configuration['block_visibility_group'] ?? '';

    if (!$default) {
      $default = $this->requestStack->getCurrentRequest()->query->get('block_visibility_group');
      if ($default) {
        $form['block_visibility_group']['#disabled'] = TRUE;
        $form_state->setTemporaryValue('block_visibility_group_query', $default);
      }
    }
    $form['block_visibility_group']['#default_value'] = $default;
    $form = parent::buildConfigurationForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $user_values = $form_state->getValues();
    foreach ($user_values as $key => $value) {
      $this->configuration[$key] = $value;
    }

    // Remove the configuration if no visibility group was selected.
    if (empty($this->configuration['block_visibility_group'])) {
      $this->configuration = [];
    }

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    if (!empty($this->configuration['block_visibility_group'])) {
      $group = $this->entityStorage->load($this->configuration['block_visibility_group']);
      if ($group) {
        $this->addDependency('config', $group->getConfigDependencyName());
      }
    }
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $tags = parent::getCacheTags();
    if (!empty($this->configuration['block_visibility_group'])) {
      $group = $this->entityStorage->load($this->configuration['block_visibility_group']);
      if ($group) {
        $tags = Cache::mergeTags($tags, $group->getCacheTags());
      }
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $contexts = parent::getCacheContexts();
    if (!empty($this->configuration['block_visibility_group'])) {
      $group = $this->entityStorage->load($this->configuration['block_visibility_group']);
      if ($group) {
        $contexts = Cache::mergeContexts($contexts, $group->getCacheContexts());
      }
    }
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    $age = parent::getCacheMaxAge();
    if (!empty($this->configuration['block_visibility_group'])) {
      $group = $this->entityStorage->load($this->configuration['block_visibility_group']);
      if ($group) {
        $age = Cache::mergeMaxAges($age, $group->getCacheMaxAge());
      }
    }
    return $age;
  }

}
