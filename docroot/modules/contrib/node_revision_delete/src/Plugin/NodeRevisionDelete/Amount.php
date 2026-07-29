<?php

namespace Drupal\node_revision_delete\Plugin\NodeRevisionDelete;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\node_revision_delete\Attribute\NodeRevisionDelete;
use Drupal\node_revision_delete\Plugin\NodeRevisionDeleteBase;
use Drupal\node_revision_delete\Plugin\NodeRevisionDeleteQueryInterface;

/**
 * Determines whether to delete a revision based on the amount of revisions.
 */
#[NodeRevisionDelete(
  id: 'amount',
  label: new TranslatableMarkup('Delete revisions when a certain amount of revisions is reached.'),
)]
class Amount extends NodeRevisionDeleteBase implements NodeRevisionDeleteQueryInterface {

  /**
   * {@inheritdoc}
   */
  public function getRevisionsToDelete(QueryInterface $query, int $active_vid, NodeInterface $node): array {
    $amount = ($this->configuration['amount'] ?? 0) ?: 0;
    // We always keep the active revision, so we need to subtract 1 from the
    // amount.
    $amount--;

    // Get all the VIDs except the newest N VIDs before active for this
    // language.
    $query
      ->condition('vid', $active_vid, '<')
      ->sort('vid', 'DESC');

    if ($amount > 0) {
      $query->range($amount, PHP_INT_MAX);
    }

    return $this->getVidsFromQuery($query);
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionsToProtect(QueryInterface $query, int $active_vid, NodeInterface $node): array {
    $amount = ($this->configuration['amount'] ?? 0) ?: 0;
    // We always keep the active revision, so we need to subtract 1 from the
    // amount.
    $amount--;

    if ($amount > 0) {
      // Get the newest N VIDs before active for this language.
      $query
        ->condition('vid', $active_vid, '<')
        ->sort('vid', 'DESC')
        ->range(0, $amount);
    }
    else {
      // If the amount is 0, we don't need to protect anything. Return an empty
      // set.
      $query
        ->condition('vid', 0)
        ->range(0, 0);
    }

    return $this->getVidsFromQuery($query);
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in node_revision_delete:2.1.0 and is removed from
   *   node_revision_delete:3.0.0. Use getRevisionsToDelete() and
   *   getRevisionsToProtect() instead.
   *
   * @see https://www.drupal.org/node/3581259
   */
  public function checkRevisions(array $revision_ids, int $active_vid): array {
    @trigger_error('\Drupal\node_revision_delete\Plugin\NodeRevisionDelete\Amount::checkRevisions() is deprecated in node_revision_delete:2.1.0 and is removed from node_revision_delete:3.0.0. Use getRevisionsToDelete() and getRevisionsToProtect() instead. See https://www.drupal.org/node/3581259', E_USER_DEPRECATED);
    $revision_statuses = [];

    $count = 0;
    foreach ($revision_ids as $vid) {
      $revision_id = $vid;
      $can_delete = NULL;

      $amount = ($this->configuration['amount'] ?? 1) ?: 1;

      // Since we always keep the active revision, we need to subtract 1 from
      // the configured amount.
      if ($amount > 0) {
        --$amount;
      }

      // We only have an opinion on revisions created before the active
      // revision.
      if ($revision_id < $active_vid) {
        $count++;
      }

      // Explicitly keep a minimum amount of revisions. We only have an opinion
      // on revisions created before the active revision.
      if ($revision_id < $active_vid && $count <= $amount) {
        $can_delete = FALSE;
      }
      elseif ($revision_id < $active_vid && $count > $amount) {
        $can_delete = TRUE;
      }

      $revision_statuses[$revision_id] = $can_delete;
    }

    return $revision_statuses;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum number of revisions to keep (per language)'),
      '#description' => $this->t('After the amount is reached, older revisions can be deleted. The minimum amount of revisions is always respected, regardless of other settings. Inactive revisions (like drafts) created after the active revision will not be deleted.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['amount'] ?? 0,
      '#min' => 0,
    ];
    return $form;
  }

}
