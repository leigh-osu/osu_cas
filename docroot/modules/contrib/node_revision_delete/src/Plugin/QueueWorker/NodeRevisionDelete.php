<?php

namespace Drupal\node_revision_delete\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\node_revision_delete\NodeRevisionDeleteInterface;
use Drupal\node_revision_delete\Plugin\NodeRevisionDeleteTimeBasedInterface;
use Drupal\node_revision_delete\Plugin\NodeRevisionDeletePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node_revision_delete\Plugin\NodeRevisionDeleteQueryInterface;

/**
 * Queue worker that deletes old node revisions based on configured plugins.
 *
 * Each queue item is a node ID. When processed, this worker loads the node,
 * collects all enabled NodeRevisionDelete plugins for the node's bundle, and
 * lets each plugin determine which revisions should be deleted or protected.
 * Active (current) revisions and revisions protected by any plugin are never
 * deleted. When the number of deletable revisions exceeds the configured bulk
 * threshold, the node is requeued for further processing.
 */
#[QueueWorker(
    id: 'node_revision_delete',
    title: new TranslatableMarkup('Node revision delete'),
    cron: ['time' => 30]
)]
class NodeRevisionDelete extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The node revision delete plugin manager.
   */
  protected NodeRevisionDeletePluginManager $pluginManager;

  /**
   * The logger factory service.
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The node revision delete service.
   */
  protected NodeRevisionDeleteInterface $nodeRevisionDelete;

  /**
   * The queue factory.
   */
  protected QueueFactory $queueFactory;

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->pluginManager = $container->get('plugin.manager.node_revision_delete');
    $instance->logger = $container->get('logger.factory');
    $instance->configFactory = $container->get('config.factory');
    $instance->nodeRevisionDelete = $container->get('node_revision_delete');
    $instance->queueFactory = $container->get('queue');
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $nid = (int) $data;
    $this->doProcessItem($nid);
    // Finished processing the node, allow it to be queued again.
    $this->nodeRevisionDelete->removeNodeFromQueueMap($nid);
  }

  /**
   * Deletes old node revisions based on configured plugins.
   *
   * @param int $nid
   *   The node ID to process.
   */
  public function doProcessItem(int $nid): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $node = $storage->load($nid);

    // When the node can no longer be found there is nothing to do here.
    if (!$node instanceof NodeInterface) {
      return;
    }

    $nid = (int) $node->id();
    $active_vid = (int) $node->getRevisionId();

    $collect_revisions_per_language = FALSE;
    // Collect enabled plugin instances.
    $plugin_definitions = $this->pluginManager->getDefinitions();
    $plugins = [];
    foreach ($plugin_definitions as $plugin_id => $plugin_definition) {
      $settings = $this->pluginManager->getSettings($plugin_id, $node->bundle());
      if (!empty($settings) && $settings['status']) {
        $plugins[$plugin_id] = $this->pluginManager->getPlugin($plugin_id, $settings['settings']);
        if (!$plugins[$plugin_id] instanceof NodeRevisionDeleteQueryInterface) {
          $collect_revisions_per_language = TRUE;
        }
      }
    }

    // Initialize data structures.
    $revisions_per_language = $protected = $deletable = $time_protected = $non_time_protected = $time_protected_by_plugin = [];
    $active_vids = $this->nodeRevisionDelete->getActiveVidsPerLanguage($node);

    // If supporting deprecated plugins.
    if ($collect_revisions_per_language) {
      // Build revision lists and active VIDs per language.
      foreach ($node->getTranslationLanguages() as $langcode => $language) {
        // Get all VIDs for this language where the translation was affected.
        $result = $storage->getQuery()
          ->allRevisions()
          ->condition('nid', $nid)
          ->condition('langcode', $langcode)
          ->condition('revision_translation_affected', 1)
          ->sort('vid', 'DESC')
          ->accessCheck(FALSE)
          ->execute();
        $revisions_per_language[$langcode] = array_map('intval', array_keys($result));
      }
    }

    // Collect delete and protect sets from all plugins across all languages.
    $base_query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->allRevisions()
      ->condition('nid', $nid)
      ->condition('revision_translation_affected', 1)
      ->accessCheck(FALSE);

    foreach ($plugins as $plugin_id => $plugin) {
      $time_based_plugin = $plugin instanceof NodeRevisionDeleteTimeBasedInterface;
      foreach ($active_vids as $langcode => $lang_active_vid) {
        if ($plugin instanceof NodeRevisionDeleteQueryInterface) {
          $to_delete = $this->useQueryToGetRevisionsToDelete($plugin, clone $base_query, $lang_active_vid, $langcode, $node);
          // It is the responsibility of the plugin to ensure its
          // getRevisionsToDelete() does not return revisions in its own
          // protected set.
          if (count($plugins) > 1 || $time_based_plugin) {
            $to_protect = $this->useQueryToGetRevisionsToProtect($plugin, clone $base_query, $lang_active_vid, $langcode, $node);
          }
          else {
            $to_protect = [];
          }
        }
        else {
          // BC fallback: call legacy checkRevisions() with VID list.
          // @phpstan-ignore method.deprecated
          $results = $plugin->checkRevisions($revisions_per_language[$langcode], $lang_active_vid);
          $to_delete = array_keys(array_filter($results, fn($v) => $v === TRUE));
          $to_protect = array_keys(array_filter($results, fn($v) => $v === FALSE));
        }
        foreach ($to_protect as $vid) {
          $protected[$vid] = TRUE;
        }
        foreach ($to_delete as $vid) {
          $deletable[$vid][] = $plugin_id;
        }
        if ($time_based_plugin) {
          $time_protected = array_merge($time_protected, $to_protect);
          $time_protected_by_plugin[$plugin_id][$langcode] = $to_protect;
        }
        else {
          $non_time_protected = array_merge($non_time_protected, $to_protect);
        }
      }
    }

    // Build the final list of VIDs to delete, excluding active and protected
    // revisions.
    $vids_responsible_plugins_map = [];
    foreach ($deletable as $vid => $responsible_plugins) {
      // Never delete active revisions.
      if ($vid === $active_vid || in_array($vid, $active_vids, TRUE)) {
        continue;
      }
      // Never delete a revision if any plugin wants to protect it.
      if (isset($protected[$vid])) {
        continue;
      }
      $vids_responsible_plugins_map[$vid] = array_unique($responsible_plugins);
    }

    // Delete revisions, if there are any.
    if (!empty($vids_responsible_plugins_map)) {
      // When more than the bulk threshold, requeue the node after deleting the
      // oldest revisions.
      $bulk_threshold = (int) $this->configFactory->get('node_revision_delete.settings')
        ->get('bulk_delete_threshold') ?: 50;
      $revision_count = count($vids_responsible_plugins_map);
      $requeue = $revision_count > $bulk_threshold;
      if ($requeue) {
        // Delete the oldest revisions first.
        ksort($vids_responsible_plugins_map);
        $vids_responsible_plugins_map = array_slice($vids_responsible_plugins_map, 0, $bulk_threshold, TRUE);
      }

      // @todo Add update function to set verbose_log default value.
      $this->deleteRevisions($vids_responsible_plugins_map, $storage, (bool) $this->configFactory->get('node_revision_delete.settings')->get('verbose_log'));

      if ($requeue) {
        throw new RequeueException(sprintf('Node %d has been requeued for deletion, more revisions to delete (count: %d).', $nid, $revision_count - $bulk_threshold));
      }
    }

    if (!empty($time_protected)) {
      // If there are time-based plugins, and a revision is only protected by
      // it, then we need to requeue. The revision will eventually become
      // unprotected.
      $exclusively_time_protected = array_diff($time_protected, $non_time_protected);
      if ($exclusively_time_protected !== []) {
        // Ask each time-based plugin for its delay based on the highest VID
        // it protects that is not also protected by a non-time-based plugin.
        $delays = [];
        foreach ($time_protected_by_plugin as $plugin_id => $langcode_plugin_vids) {
          $plugin = $plugins[$plugin_id];
          assert($plugin instanceof NodeRevisionDeleteTimeBasedInterface);
          foreach ($langcode_plugin_vids as $langcode => $plugin_vids) {
            $exclusive_vids = array_intersect($plugin_vids, $exclusively_time_protected);
            if (!empty($exclusive_vids)) {
              $lowest_vid = min($exclusive_vids);
              /** @var \Drupal\node\NodeInterface $revision */
              $revision = $storage->loadRevision($lowest_vid);
              $revision = $revision?->getTranslation($langcode);
              if ($revision) {
                $delays[] = $plugin->getDelay($revision);
              }
            }
          }
        }

        // It should not be possible for delays to be empty, but if they are,
        // then skip the requeue.
        if (!empty($delays)) {
          $this->queueFactory->get('node_revision_delete_requeue')->createItem([
            'nid' => $nid,
            'requeue_time' => $this->time->getCurrentTime() + min($delays),
          ]);
        }
      }
    }
  }

  /**
   * Uses a query to get revisions to delete.
   *
   * @param \Drupal\node_revision_delete\Plugin\NodeRevisionDeleteQueryInterface $plugin
   *   The plugin to use.
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to use.
   * @param int $active_vid
   *   The active revision ID.
   * @param string $langcode
   *   The language code.
   * @param \Drupal\node\NodeInterface $node
   *   The node entity in the default revision.
   *
   * @return int[]
   *   The revision IDs to delete.
   */
  private function useQueryToGetRevisionsToDelete(NodeRevisionDeleteQueryInterface $plugin, QueryInterface $query, int $active_vid, string $langcode, NodeInterface $node): array {
    $query->condition('langcode', $langcode);
    return $plugin->getRevisionsToDelete($query, $active_vid, $node);
  }

  /**
   * Uses a query to get revisions to protect.
   *
   * @param \Drupal\node_revision_delete\Plugin\NodeRevisionDeleteQueryInterface $plugin
   *   The plugin to use.
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to use.
   * @param int $active_vid
   *   The active revision ID.
   * @param string $langcode
   *   The language code.
   * @param \Drupal\node\NodeInterface $node
   *   The node entity in the default revision.
   *
   * @return int[]
   *   The revision IDs to protect.
   */
  private function useQueryToGetRevisionsToProtect(NodeRevisionDeleteQueryInterface $plugin, QueryInterface $query, int $active_vid, string $langcode, NodeInterface $node): array {
    $query->condition('langcode', $langcode);
    return $plugin->getRevisionsToProtect($query, $active_vid, $node);
  }

  /**
   * Deletes revisions using bulk loading.
   *
   * @param array $vid_responsible_plugins_map
   *   Map of VID to responsible plugin IDs.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The node storage.
   * @param bool $verbose_log
   *   Whether to log verbosely.
   */
  protected function deleteRevisions(array $vid_responsible_plugins_map, EntityStorageInterface $storage, bool $verbose_log): void {
    assert($storage instanceof RevisionableStorageInterface);
    // Load all revisions in bulk to populate the entity static cache.
    foreach ($storage->loadMultipleRevisions(array_keys($vid_responsible_plugins_map)) as $vid => $revision) {
      $storage->deleteRevision($vid);
      if ($verbose_log) {
        $this->logger->get('node_revision_delete')->info('Deleted revision @revision_id for node @node_id. (responsible plugins: @responsible_plugins)', [
          '@revision_id' => $vid,
          '@node_id' => $revision->id(),
          '@responsible_plugins' => implode(', ', $vid_responsible_plugins_map[$vid] ?? []),
        ]);
      }
    }
  }

}
