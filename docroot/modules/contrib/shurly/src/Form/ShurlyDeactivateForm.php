<?php

namespace Drupal\shurly\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * ShurlyDeactivateForm.
 */
class ShurlyDeactivateForm extends ConfirmFormBase {

  /**
   * Access check for deactivating a short url.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param $rid
   */
  public function access(AccountInterface $account, $rid) {
    if (is_numeric($rid)) {
      $row = \Drupal::database()
        ->query('SELECT uid, source, destination FROM {shurly} WHERE rid = :rid', ['rid' => $rid])
        ->fetchObject();
      // If there's a row, and either the user is an admin, or they've got
      // permission to create and they own this URL, then let them access.
      return AccessResult::allowedIf($account->hasPermission('administer short URLs') || $account->hasPermission('deactivate own URLs') && $row->uid == $account->id());
    }
  }

  /**
   * The ID of the item to delete.
   *
   * @var string
   */
  protected $rid;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shurly_deactivate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to deactivate this short URL?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('shurly.deactivate');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Once this item is deactivated, you will not be able to create another link with the same short URL.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Proceed');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rid = NULL) {
    $this->rid = $rid;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('Short URL has been deactivated'));
    shurly_set_link_active($this->rid, 0);
  }

}
