<?php

namespace Drupal\node_revision_delete\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node_revision_delete\NodeRevisionDeleteInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker that requeues a node after a delay.
 *
 * Time-based node revision delete plugins that implement
 * NodeRevisionDeleteTimeBasedInterface use this queue worker to requeue nodes
 * if the plugin determines that they will need to be deleted in the future.
 * Creating a new queue item rather than using
 * \Drupal\Core\Queue\DelayedRequeueException ensures that if lots of nodes are
 * being requeued nodes with new revisions can make it into the queue and be
 * processed.
 *
 * @see \Drupal\node_revision_delete\Plugin\NodeRevisionDeleteTimeBasedInterface
 */
#[QueueWorker(
    id: 'node_revision_delete_requeue',
    title: new TranslatableMarkup('Node revision delete requeue'),
    cron: ['time' => 5]
)]
class NodeRevisionDeleteRequeue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
   *
   * @var \Drupal\node_revision_delete\NodeRevisionDeleteInterface
   */
  protected NodeRevisionDeleteInterface $nodeRevisionDelete;

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->time = $container->get('datetime.time');
    $instance->logger = $container->get('logger.factory');
    $instance->configFactory = $container->get('config.factory');
    $instance->nodeRevisionDelete = $container->get('node_revision_delete');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $nid = $data['nid'];
    $requeue_time = $data['requeue_time'];
    if ($requeue_time > $this->time->getCurrentTime()) {
      throw new DelayedRequeueException($requeue_time - $this->time->getCurrentTime());
    }
    if ($this->nodeRevisionDelete->createQueueItem($nid)->queued()) {
      if ($this->configFactory->get('node_revision_delete.settings')->get('verbose_log')) {
        $this->logger->get('node_revision_delete')->info('Node @id was requeued.', ['@id' => $nid]);
      }
    }
  }

}
