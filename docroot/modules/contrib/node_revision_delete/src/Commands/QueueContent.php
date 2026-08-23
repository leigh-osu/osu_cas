<?php

namespace Drupal\node_revision_delete\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node_revision_delete\NodeRevisionDeleteBatchInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush command for putting all content in a queue.
 */
class QueueContent extends DrushCommands {

  use StringTranslationTrait;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The NodeRevisionDeleteBatch service.
   *
   * @var \Drupal\node_revision_delete\NodeRevisionDeleteBatchInterface
   */
  protected NodeRevisionDeleteBatchInterface $nodeRevisionDeleteBatch;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\node_revision_delete\NodeRevisionDeleteBatchInterface $node_revision_delete_batch
   *   The node revision delete batch service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, NodeRevisionDeleteBatchInterface $node_revision_delete_batch) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeRevisionDeleteBatch = $node_revision_delete_batch;
  }

  /**
   * Validate for node-revision-delete:queue.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @hook validate node-revision-delete:queue
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function validateQueueContent(CommandData $commandData) {
    // Getting the content types.
    $content_types = $commandData->input()->getOption('type');
    if (!empty($content_types)) {
      $content_types = explode(',', $content_types);

      $content_types_database = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
      // Creating an array with all content types.
      $content_types_list = [];
      foreach ($content_types_database as $content_type) {
        $content_types_list[] = $content_type->id();
      }

      $invalid_content_types = array_diff($content_types, $content_types_list);

      if (count($invalid_content_types)) {
        $names = implode(', ', $invalid_content_types);
        throw new \Exception(dt('Invalid content types names: @names.', ['@names' => $names]));
      }
    }
  }

  /**
   * Creates queue items or all content.
   *
   * This creates queue items for all content which then can be processed
   * during cron.
   *
   * @option type A comma-separated list of content types to process. If not provided, all content types will be processed.
   *
   * @usage drush node-revision-delete:queue
   *   Creates queue items for all content where settings apply.
   * @usage drush node-revision-delete:queue --type=article,page
   *   Creates queue items for mentioned content types.
   *
   * @command node-revision-delete:queue
   *
   * @aliases nrd-q, nrd-queue
   */
  public function queueContent($options = ['type' => '']): void {
    if (!empty($options['type'])) {
      $content_types = explode(',', $options['type']);
      $content_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple($content_types);
    }
    else {
      $content_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    }

    $this->nodeRevisionDeleteBatch->queueBatch($content_types);

    drush_backend_batch_process();
  }

}
