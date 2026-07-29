<?php

namespace Drupal\node_revision_delete\Plugin\NodeRevisionDelete;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\node_revision_delete\Attribute\NodeRevisionDelete;
use Drupal\node_revision_delete\Plugin\NodeRevisionDeleteBase;
use Drupal\node_revision_delete\Plugin\NodeRevisionDeleteQueryInterface;
use Drupal\node_revision_delete\Plugin\NodeRevisionDeleteTimeBasedInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Determines whether to delete revisions newer than the active revision.
 */
#[NodeRevisionDelete(
  id: 'drafts',
  label: new TranslatableMarkup('Delete revisions newer than the current revision.'),
)]
class Drafts extends NodeRevisionDeleteBase implements NodeRevisionDeleteQueryInterface, NodeRevisionDeleteTimeBasedInterface {

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
    $plugin->time = $container->get('datetime.time');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionsToDelete(QueryInterface $query, int $active_vid, NodeInterface $node): array {
    $age = strtotime('-' . $this->configuration['age'] . ' months', $this->time->getCurrentTime());

    return $this->getVidsFromQuery($query
      ->condition('vid', $active_vid, '>')
      ->condition('changed', $age, '<'));
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionsToProtect(QueryInterface $query, int $active_vid, NodeInterface $node): array {
    $age = strtotime('-' . $this->configuration['age'] . ' months', $this->time->getCurrentTime());

    return $this->getVidsFromQuery($query
      ->condition('vid', $active_vid, '>')
      ->condition('changed', $age, '>='));
  }

  /**
   * {@inheritdoc}
   */
  public function getDelay(NodeInterface $revision): int {
    $changed = $revision->getChangedTime();
    $age = strtotime('+' . $this->configuration['age'] . ' months', $changed);
    return max(0, $age - $this->time->getCurrentTime());
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
    @trigger_error(__METHOD__ . '() is deprecated in node_revision_delete:2.1.0 and is removed from node_revision_delete:3.0.0. Use getRevisionsToDelete() and getRevisionsToProtect() instead. See https://www.drupal.org/node/3581259', E_USER_DEPRECATED);
    $revision_statuses = [];

    foreach ($revision_ids as $vid) {
      $revision_id = $vid;
      /** @var \Drupal\node\NodeInterface $revision */
      $revision = $this->entityTypeManager->getStorage('node')->loadRevision($revision_id);
      $can_delete = NULL;

      $age = strtotime('-' . $this->configuration['age'] . ' months', $this->time->getCurrentTime());

      // The timestamp of the created revision is stored in the changed field.
      $creation_time = $revision->getChangedTime();

      // Explicitly keep revisions for the configured minimum age. We only have
      // an opinion on revisions created before the active revision.
      if ($revision_id > $active_vid && $creation_time >= $age) {
        $can_delete = FALSE;
      }
      elseif ($revision_id > $active_vid && $creation_time < $age) {
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
    for ($i = 1; $i <= 24; $i++) {
      $options[$i] = $this->formatPlural($i, '@count month', '@count months');
    }
    $form['age'] = [
      '#type' => 'select',
      '#title' => $this->t('The minimum amount of months a new revision must be kept for'),
      '#description' => $this->t('After this time, revisions newer than the active revision (like draft revisions) can be deleted. The minimum age of revisions is always respected, regardless of other settings. Only revisions created after the active revision will be deleted.'),
      '#options' => $options,
      '#default_value' => $this->configuration['age'] ?? 0,
    ];
    return $form;
  }

}
