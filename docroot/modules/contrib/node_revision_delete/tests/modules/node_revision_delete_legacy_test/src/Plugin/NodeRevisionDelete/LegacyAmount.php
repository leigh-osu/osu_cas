<?php

declare(strict_types=1);

namespace Drupal\node_revision_delete_legacy_test\Plugin\NodeRevisionDelete;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node_revision_delete\Attribute\NodeRevisionDelete;
use Drupal\node_revision_delete\Plugin\NodeRevisionDeleteBase;

/**
 * A legacy plugin that only implements NodeRevisionDeleteInterface.
 *
 * This plugin does NOT implement NodeRevisionDeleteQueryInterface and relies
 * entirely on the deprecated checkRevisions() method. It is used to test
 * backward compatibility with the legacy plugin interface.
 */
#[NodeRevisionDelete(
  id: 'legacy_amount',
  label: new TranslatableMarkup('Legacy amount plugin (test only)'),
)]
class LegacyAmount extends NodeRevisionDeleteBase {

  /**
   * {@inheritdoc}
   */
  public function checkRevisions(array $revision_ids, int $active_vid): array {
    $revision_statuses = [];
    $amount = ($this->configuration['amount'] ?? 1) ?: 1;
    // The active revision is always kept, so subtract 1.
    $amount--;

    $count = 0;
    foreach ($revision_ids as $vid) {
      $can_delete = NULL;

      if ($vid < $active_vid) {
        $count++;
      }

      if ($vid < $active_vid && $count <= $amount) {
        $can_delete = FALSE;
      }
      elseif ($vid < $active_vid && $count > $amount) {
        $can_delete = TRUE;
      }

      $revision_statuses[$vid] = $can_delete;
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
      '#required' => TRUE,
      '#default_value' => $this->configuration['amount'] ?? 0,
    ];
    return $form;
  }

}
