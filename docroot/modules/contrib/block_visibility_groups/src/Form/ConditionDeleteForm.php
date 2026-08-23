<?php

namespace Drupal\block_visibility_groups\Form;

use Drupal\block_visibility_groups\ConditionRedirectTrait;
use Drupal\block_visibility_groups\Entity\BlockVisibilityGroup;
use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides a form for deleting an condition.
 */
class ConditionDeleteForm extends ConfirmFormBase {

  use ConditionRedirectTrait;

  /**
   * The block_visibility_group entity this selection condition belongs to.
   *
   * @var \Drupal\block_visibility_groups\Entity\BlockVisibilityGroup
   */
  protected BlockVisibilityGroup $block_visibility_group;

  /**
   * The condition used by this form.
   *
   * @var \Drupal\Core\Condition\ConditionInterface
   */
  protected ConditionInterface $condition;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'block_visibility_group_manager_condition_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the condition %name?', ['%name' => $this->condition->getPluginDefinition()['label']]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return $this->block_visibility_group->toUrl('edit-form');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?BlockVisibilityGroup $block_visibility_group = NULL, ?string $condition_id = NULL, string $redirect = 'edit'): array {
    $this->block_visibility_group = $block_visibility_group;
    $this->setRedirectValue($form, $redirect);
    $this->condition = $block_visibility_group->getCondition($condition_id);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->block_visibility_group->removeCondition($this->condition->getConfiguration()['uuid']);
    $this->block_visibility_group->save();
    $this->messenger()->addMessage($this->t('The condition %name has been removed.', ['%name' => $this->condition->getPluginDefinition()['label']]));
    $this->setConditionRedirect($form_state);
  }

}
