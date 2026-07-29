<?php

namespace Drupal\node_revision_delete\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeTypeInterface;

/**
 * Confirms resetting a node type's revision delete settings to defaults.
 */
class ResetNodeTypeForm extends ConfirmFormBase {

  /**
   * The node type to reset.
   */
  protected NodeTypeInterface $nodeType;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'node_revision_delete_reset_node_type';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to reset the revision delete settings for %type to defaults?', [
      '%type' => $this->nodeType->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('node_revision_delete.admin_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeTypeInterface $node_type = NULL): array {
    $this->nodeType = $node_type;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    foreach (array_keys($this->nodeType->getThirdPartySettings('node_revision_delete')) as $key) {
      $this->nodeType->unsetThirdPartySetting('node_revision_delete', $key);
    }
    $this->nodeType->save();
    $this->messenger()->addStatus($this->t('The revision delete settings for %type have been reset to defaults.', [
      '%type' => $this->nodeType->label(),
    ]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
