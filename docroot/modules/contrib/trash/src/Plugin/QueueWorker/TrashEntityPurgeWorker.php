<?php

declare(strict_types=1);

namespace Drupal\trash\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\trash\TrashManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A queue worker for purging the trash bin.
 */
#[QueueWorker(
  id: 'trash_entity_purge',
  title: new TranslatableMarkup('Trash Entity Purge Worker'),
  cron: ['time' => 60]
)]
class TrashEntityPurgeWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TrashManagerInterface $trashManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected TimeInterface $time,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('trash.manager'),
      $container->get('logger.factory'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    try {
      // Trash may have been disabled for this entity type since the item was
      // queued. The 'deleted' field storage would already be gone in that
      // case, and entities matching the queued IDs are live content again,
      // so drop the item instead of hard-deleting them.
      if (!$this->trashManager->isEntityTypeEnabled($data['entity_type_id'])) {
        return;
      }

      $storage = $this->entityTypeManager->getStorage($data['entity_type_id']);
      $entity_definition = $this->entityTypeManager->getDefinition($data['entity_type_id']);
      $id_key = $entity_definition->getKey('id');

      // The cutoff timestamp the queue item was built with. Only entities
      // deleted before this may be purged. Legacy items queued without this
      // key purge nothing: the next cron run re-enqueues their entities with
      // a real cutoff.
      $cutoff = $data['cutoff'] ?? 0;

      // Querying once again to validate the entities still exist.
      $ids = $storage
        ->getQuery()
        ->condition($id_key, $data['batch'], 'IN')
        ->addMetaData('trash', 'inactive')
        ->accessCheck(FALSE)
        ->execute();

      if ($ids) {
        $purged_count = 0;
        $this->trashManager->executeInTrashContext('inactive', function () use ($storage, $ids, $cutoff, &$purged_count) {
          $entities = $storage->loadMultiple($ids);
          // An entity that was restored and trashed again after the item
          // was queued has a fresh timestamp and keeps its full retention
          // window, so only purge entities deleted before the cutoff.
          $entities = array_filter($entities, fn (ContentEntityInterface $entity) => (int) $entity->get('deleted')->value < $cutoff);
          if ($entities) {
            $storage->delete($entities);
            $purged_count = count($entities);
          }
        });

        // Report only the entities that were acted on; the cutoff check
        // above may skip any number of queued IDs.
        if ($purged_count > 0) {
          $message = $this->formatPlural($purged_count, 'Successfully purged @count @item', 'Successfully purged @count @items', [
            '@count' => $purged_count,
            '@item' => $entity_definition->getSingularLabel(),
            '@items' => $entity_definition->getPluralLabel(),
          ]);

          $this->loggerFactory->get('trash')->info($message);
        }
      }
    }
    catch (\Exception $e) {
      // Re-throw so the cron queue runner keeps the item and retries it. A
      // swallowed exception here lets a transient failure (deadlock, lock
      // timeout) drop those entities from the purge queue for good.
      $this->loggerFactory->get('trash')->error("An error prevented purging local items. Error message: {$e->getMessage()}");
      throw $e;
    }
  }

}
