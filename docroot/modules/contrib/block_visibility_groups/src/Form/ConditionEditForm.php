<?php

namespace Drupal\block_visibility_groups\Form;

use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a form for editing an condition.
 */
class ConditionEditForm extends ConditionFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'block_visibility_group_manager_condition_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareCondition($condition_id): ConditionInterface {
    // Load the condition directly from the block_visibility_group entity.
    return $this->block_visibility_group->getCondition($condition_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function submitButtonText(): string {
    return $this->t('Update condition');
  }

  /**
   * {@inheritdoc}
   */
  protected function submitMessageText(): TranslatableMarkup {
    return $this->t('The %label condition has been updated.', ['%label' => $this->condition->getPluginDefinition()['label']]);
  }

}
