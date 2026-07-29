<?php

namespace Drupal\node_revision_delete;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\node_revision_delete\Plugin\NodeRevisionDeletePluginManager;

/**
 * The Node Revision Delete service.
 *
 * @package Drupal\node_revision_delete
 */
class NodeRevisionDelete implements NodeRevisionDeleteInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * The Node Revision Plugin Manager.
   *
   * @var \Drupal\node_revision_delete\Plugin\NodeRevisionDeletePluginManager
   */
  protected NodeRevisionDeletePluginManager $pluginManager;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\node_revision_delete\Plugin\NodeRevisionDeletePluginManager $node_revision_plugin_manager
   *   Node revision plugin manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    Connection $connection,
    NodeRevisionDeletePluginManager $node_revision_plugin_manager,
    QueueFactory $queue_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->connection = $connection;
    $this->pluginManager = $node_revision_plugin_manager;
    $this->queueFactory = $queue_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousRevisionIds(int $nid, int $currently_deleted_revision_id, ?string $langcode = NULL): array {
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Fall back to the current language if no language code is specified.
    if ($langcode === NULL) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }

    // Use an entity query to find all revision IDs older than the specified
    // revision that affected the given language.
    $result = $node_storage->getQuery()
      ->allRevisions()
      ->condition('nid', $nid)
      ->condition('vid', $currently_deleted_revision_id, '<')
      ->condition('langcode', $langcode)
      ->condition('revision_translation_affected', 1)
      ->sort('vid', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    return array_map('intval', array_keys($result));
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousRevisions(int $nid, int $currently_deleted_revision_id, ?string $langcode = NULL): array {
    @trigger_error(__METHOD__ . '() is deprecated in node_revision_delete:2.1.0 and is removed from node_revision_delete:3.0.0. Use getPreviousRevisionIds() instead. See https://www.drupal.org/node/3584874', E_USER_DEPRECATED);
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Fall back to the current language if no language code is specified.
    if ($langcode === NULL) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }

    $vids = $this->getPreviousRevisionIds($nid, $currently_deleted_revision_id, $langcode);

    if (empty($vids)) {
      return [];
    }

    // Load all matching revisions and return their translations.
    $revisions = [];
    /** @var \Drupal\node\NodeInterface $revision */
    foreach ($node_storage->loadMultipleRevisions($vids) as $revision) {
      if ($revision->hasTranslation($langcode)) {
        $revisions[] = $revision->getTranslation($langcode);
      }
    }

    return $revisions;
  }

  /**
   * {@inheritdoc}
   */
  public function nodeExistsInQueue(int $nid): bool {
    return (bool) $this->connection->select(static::QUEUE_SEMAPHORE_TABLE, 'm')
      ->condition('nid', $nid)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function createQueueItem(int $nid): QueueItemCreationResult {
    if ($this->nodeExistsInQueue($nid)) {
      return QueueItemCreationResult::AlreadyExists;
    }

    // If we are inside a database transaction, defer the queue insert to a
    // post-transaction callback. This avoids deadlocks caused by long-running
    // transactions holding locks on the queue and map tables, and ensures the
    // queue item is only created when the transaction actually commits.
    if ($this->connection->inTransaction()) {
      $this->connection->transactionManager()->addPostTransactionCallback(function (bool $success) use ($nid) {
        if ($success && !$this->nodeExistsInQueue($nid)) {
          $this->doCreateQueueItem($nid);
        }
      });
      return QueueItemCreationResult::Deferred;
    }

    return $this->doCreateQueueItem($nid);
  }

  /**
   * Performs the actual queue item creation and map table insert.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return \Drupal\node_revision_delete\QueueItemCreationResult
   *   Created or Failed.
   */
  protected function doCreateQueueItem(int $nid): QueueItemCreationResult {
    // Try to insert the node into the queue map table. If the insert fails,
    // then we've already queued this node. This way the table acts as a
    // semaphore table and prevents duplicate queue items from being created.
    try {
      $this->connection->insert(static::QUEUE_SEMAPHORE_TABLE)
        ->fields(['nid'])
        ->values(['nid' => $nid])
        ->execute();
    }
    catch (DatabaseExceptionWrapper) {
      return QueueItemCreationResult::AlreadyExists;
    }

    $item_id = $this->queueFactory->get('node_revision_delete')->createItem($nid);
    if (!$item_id) {
      $this->removeNodeFromQueueMap($nid);
      return QueueItemCreationResult::Failed;
    }

    return QueueItemCreationResult::Created;
  }

  /**
   * {@inheritdoc}
   */
  public function removeNodeFromQueueMap(int $nid): void {
    $this->connection->delete(static::QUEUE_SEMAPHORE_TABLE)
      ->condition('nid', $nid)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function contentTypeHasEnabledPlugins(string $content_type_id): bool {
    $settings = $this->pluginManager->getSettingsNodeType($content_type_id);
    if (isset($settings['plugin'])) {
      foreach ($settings['plugin'] as $plugin_settings) {
        $status = (bool) ($plugin_settings['status'] ?? FALSE);
        if ($status) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveVidsPerLanguage(NodeInterface $node): array {
    $active_vids = [];
    $storage = $this->entityTypeManager->getStorage('node');
    // Build revision lists and active VIDs per language.
    foreach ($node->getTranslationLanguages() as $langcode => $language) {
      // Get active VID for this language: latest revision that was the default
      // and is translation-affected.
      $active_result = $storage->getQuery()
        ->allRevisions()
        ->condition('nid', $node->id())
        ->condition('langcode', $langcode)
        ->condition('revision_translation_affected', 1)
        ->condition('revision_default', 1)
        ->sort('vid', 'DESC')
        ->range(0, 1)
        ->accessCheck(FALSE)
        ->execute();
      if ($active_result) {
        $active_vids[$langcode] = (int) key($active_result);
      }
    }
    return $active_vids;
  }

}
