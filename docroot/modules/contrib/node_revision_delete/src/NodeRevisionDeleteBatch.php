<?php

namespace Drupal\node_revision_delete;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeTypeInterface;

/**
 * Batch controller to process node revision delete.
 *
 * Class NodeRevisionDeleteBatch declaration.
 */
class NodeRevisionDeleteBatch implements NodeRevisionDeleteBatchInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Number of nodes to process in a batch step.
   */
  protected const NODES_PER_BATCH_PROCESS = 500;

  /**
   * The Messenger Service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The Entity Type Manager Service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Queue Service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queue;

  /**
   * The Logger Factory Service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * The Node Revision Delete Service.
   *
   * @var \Drupal\node_revision_delete\NodeRevisionDeleteInterface
   */
  protected NodeRevisionDeleteInterface $nodeRevisionDelete;

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * Constructor of the Node Revision Delete Batch service.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   The queue service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger Factory.
   * @param \Drupal\node_revision_delete\NodeRevisionDeleteInterface $node_revision_delete
   *   The node revision delete.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entity_type_manager,
    QueueFactory $queue,
    LoggerChannelFactoryInterface $logger_factory,
    NodeRevisionDeleteInterface $node_revision_delete,
    Connection $database,
    protected ConfigFactoryInterface $configFactory,
  ) {
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
    $this->queue = $queue;
    $this->logger = $logger_factory;
    $this->nodeRevisionDelete = $node_revision_delete;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function queueBatch(array $node_types = []): void {
    $batch = (new BatchBuilder())
      ->setTitle($this->t('Creating queue items'))
      ->setInitMessage($this->t('Starting to add nodes.'))
      ->setProgressMessage($this->t('Processing node type @current out of @total (@percentage%). Estimated time: @estimate.'))
      ->setErrorMessage($this->t('Error adding nodes.'))
      ->setFinishCallback([$this, 'finishQueue']);

    if (empty($node_types)) {
      // Clear the queues and reset the semaphore table.
      $this->queue->get('node_revision_delete')->deleteQueue();
      $this->queue->get('node_revision_delete_requeue')->deleteQueue();
      $this->database->truncate(NodeRevisionDeleteInterface::QUEUE_SEMAPHORE_TABLE)->execute();
      $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    }
    foreach ($node_types as $node_type) {
      if ($this->nodeRevisionDelete->contentTypeHasEnabledPlugins($node_type->id())) {
        // Create a queue for all nodes in this content type.
        $batch->addOperation([$this, 'queue'], [$node_type]);
      }
    }

    batch_set($batch->toArray());
  }

  /**
   * {@inheritdoc}
   */
  public function queue(NodeTypeInterface $node_type, &$context): void {
    if (empty($context['results']['total'])) {
      $context['results']['total'] = 0;
    }

    $last_nid = $context['sandbox'][$node_type->id()]['last_nid'] ?? 0;

    // Get all node IDs for this node type.
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', $node_type->id())
      ->condition('nid', $last_nid, '>')
      ->accessCheck(FALSE)
      ->range(0, static::NODES_PER_BATCH_PROCESS)
      ->sort('nid')
      ->execute();

    if (empty($nids)) {
      $context['finished'] = TRUE;
      return;
    }

    // Adding the node to the queue.
    foreach ($nids as $nid) {
      if ($this->nodeRevisionDelete->createQueueItem($nid)->queued()) {
        $context['results']['total']++;
      }
      $context['sandbox'][$node_type->id()]['count'] = ($context['sandbox'][$node_type->id()]['count'] ?? 0) + 1;
    }

    $context['sandbox'][$node_type->id()]['last_nid'] = $nid;

    $total_count = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', $node_type->id())
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $context['finished'] = $total_count > 0 ? ($context['sandbox'][$node_type->id()]['count'] ?? 0) / $total_count : TRUE;

    // Adding a message for the actual node being added.
    $message = $this->formatPlural($context['sandbox'][$node_type->id()]['count'], '@count node of @node_type_label [@node_type_id] has been processed.', '@count nodes of @node_type_label [@node_type_id] have been processed', [
      '@node_type_id' => $node_type->id(),
      '@node_type_label' => $node_type->label(),
    ]);
    $context['message'] = $message;
  }

  /**
   * {@inheritdoc}
   */
  public function finishQueue(bool $success, array $results, array $operations): void {
    $messenger = $this->messenger;
    $logger = $this->logger->get('node_revision_delete');

    if ($success) {
      if (!empty($results['total'])) {
        $success_message = $this->formatPlural(
          $results['total'],
          'One node has been added to the queue.',
          '@count nodes have been added to the queue.',
        );

        $logger->notice($success_message);
        $messenger->addMessage($success_message);
      }
      else {
        $warning_message = $this->t('No nodes have been added to the queue. Possible reasons are: All the nodes are in the queue, or you do not have any nodes on your site, or all content types have their node revision delete settings disabled.');
        $logger->notice($warning_message);
        $messenger->addWarning($warning_message);
      }
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $error_message = $this->t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]);
      $logger->error($error_message);
      $messenger->addError($error_message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function previousRevisionDeletionBatch(int $nid, int $currently_deleted_revision_id, ?string $langcode = NULL): bool {
    // Defining the batch builder.
    $batch = (new BatchBuilder())
      ->setTitle($this->t('Deleting revisions'))
      ->setInitMessage($this->t('Starting to delete revisions.'))
      ->setProgressMessage($this->t('Estimated time: @estimate.'))
      ->setErrorMessage($this->t('Error deleting revisions.'))
      ->setFinishCallback([$this, 'finishPreviousRevisions']);

    // Get list of revision IDs older than current revision.
    $vids = $this->nodeRevisionDelete->getPreviousRevisionIds($nid, $currently_deleted_revision_id, $langcode);

    if (empty($vids)) {
      return FALSE;
    }

    $batch->addOperation([$this, 'deletePreviousRevision'], [$nid, $currently_deleted_revision_id, $langcode]);
    batch_set($batch->toArray());
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deletePreviousRevision(int $nid, int $original_revision_id, string $langcode, array &$context): void {
    $current_vid = $context['sandbox']['vid'] ?? $original_revision_id;
    $revision_ids_to_delete = $this->nodeRevisionDelete->getPreviousRevisionIds($nid, $current_vid, $langcode);

    // Nothing left to do.
    if (empty($revision_ids_to_delete)) {
      $context['finished'] = TRUE;
      return;
    }

    if (!isset($context['results']['revisions_deleted'])) {
      $context['results']['revisions_deleted'] = 0;
      $context['results']['revisions_processed'] = 0;
    }

    if (empty($context['sandbox'])) {
      $context['sandbox']['total'] = count($revision_ids_to_delete);
    }

    // Delete multiple revisions at once upto the threshold.
    $bulk_threshold = (int) $this->configFactory->get('node_revision_delete.settings')->get('bulk_delete_threshold') ?: 50;
    $revision_ids_to_delete_count = count($revision_ids_to_delete);
    if ($revision_ids_to_delete_count > $bulk_threshold) {
      $revision_ids_to_delete = array_slice($revision_ids_to_delete, 0, $bulk_threshold, TRUE);
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $revisions = $storage->loadMultipleRevisions($revision_ids_to_delete);
    if (empty($revisions)) {
      $context['finished'] = TRUE;
      return;
    }

    if (!isset($context['sandbox']['active_vids'])) {
      /** @var \Drupal\node\NodeInterface $revision */
      $revision = reset($revisions);
      $context['sandbox']['active_vids'] = $this->nodeRevisionDelete->getActiveVidsPerLanguage($revision);
    }

    foreach ($revisions as $vid => $revision) {
      $active_revision_langcode = array_search($vid, $context['sandbox']['active_vids'], TRUE);
      if ($revision->isDefaultRevision()) {
        $context['results']['skipped'][] = $this->t('Revision @vid [nid: @nid] cannot be deleted as it is the default revision.', ['@vid' => $vid, '@nid' => $revision->id()]);
      }
      elseif ($active_revision_langcode !== FALSE) {
        $context['results']['skipped'][] = $this->t('Revision @vid [nid: @nid] cannot be deleted as it is the active revision for @langcode language.', ['@vid' => $vid, '@nid' => $revision->id(), '@langcode' => $active_revision_langcode]);
      }
      else {
        $context['results']['revisions_deleted']++;
        $storage->deleteRevision($vid);
      }
      $context['results']['revisions_processed']++;
      $context['sandbox']['vid'] = $vid;
    }

    // Progress is derived from the ratio of remaining VIDs (from the current
    // query) to the original total. This avoids tracking a cumulative counter
    // across iterations, which can exceed the total when skipped revisions
    // shift the query window and expose VIDs not in the original count.
    $context['finished'] = 1 - ($revision_ids_to_delete_count / $context['sandbox']['total']);
    $message = $this->formatPlural($context['results']['revisions_deleted'], 'Deleted @count revision of %title [@nid].', 'Deleted @count revisions of %title [@nid].', [
      '@nid' => $revision->id(),
      '%title' => $revision->label(),
    ]);
    $context['message'] = $message;
  }

  /**
   * {@inheritdoc}
   */
  public function finishPreviousRevisions(bool $success, array $results, array $operations): void {
    $messenger = $this->messenger;
    $logger = $this->logger->get('node_revision_delete');

    if ($success) {
      $success_message = $this->formatPlural(
        $results['revisions_deleted'],
        'One prior revision has been deleted.',
        '@count prior revisions have been deleted.',
      );

      $logger->notice($success_message);
      $messenger->addMessage($success_message);
      foreach ($results['skipped'] ?? [] as $message) {
        $messenger->addMessage($message);
      }
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $error_message = $this->t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]);
      $logger->error($error_message);
      $messenger->addError($error_message);
    }
  }

}
