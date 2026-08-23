<?php

namespace Drupal\node_revision_delete\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\node_revision_delete\NodeRevisionDeleteBatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form with queue status and a button to queue all nodes.
 */
class QueueForm extends FormBase {

  /**
   * The batch helper service.
   */
  protected NodeRevisionDeleteBatchInterface $nodeRevisionDeleteBatch;

  /**
   * The queue factory.
   */
  protected QueueFactory $queueFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->nodeRevisionDeleteBatch = $container->get('node_revision_delete.batch');
    $instance->queueFactory = $container->get('queue');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'node_revision_delete_queue';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $queue_count = $this->queueFactory->get('node_revision_delete')->numberOfItems();
    $requeue_count = $this->queueFactory->get('node_revision_delete_requeue')->numberOfItems();

    $form['status'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Queue status'),
    ];

    $form['status']['table'] = [
      '#type' => 'table',
      '#header' => [$this->t('Queue'), $this->t('Items')],
      '#rows' => [
        [$this->t('Revision delete'), $queue_count],
        [$this->t('Time-based requeue'), $requeue_count],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Queue all content for revision deletion'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->nodeRevisionDeleteBatch->queueBatch();
  }

}
