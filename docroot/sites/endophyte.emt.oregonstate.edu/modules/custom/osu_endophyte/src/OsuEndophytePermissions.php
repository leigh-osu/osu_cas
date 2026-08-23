<?php

namespace Drupal\osu_endophyte;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\core\Entity\EntityTypeManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Defines a class for dynamic permissions based on workflows.
 */
class OsuEndophytePermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The workflow manager.
   *
   * @var \Drupal\state_machine\WorkflowManagerInterface
   */
  protected $workflowManager;


  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WorkflowManagerInterface $workflow_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->workflowManager = $workflow_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.workflow')
    );
  }

  /**
   * Returns an array of transition permissions.
   *
   * @return array
   *   The transition permissions.
   */
  public function permissions() {
    $permissions = [];
    $workflows = $this->workflowManager->getDefinitions();
    /* @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    foreach ($workflows as $workflow_id => $workflow) {
      foreach ($workflow['transitions'] as $transition_id => $transition) {
        $permissions['use ' . $transition_id . ' transition in ' . $workflow_id] = [
          'title' => $this->t('Use the %label transition', [
            '%label' => $transition['label'],
          ]),
          'description' => $this->t('Workflow group %label', [
            '%label' => $workflow['label'],
          ]),
        ];
      }
    }

    //add permission for dashboard
    $permissions['access esl dashboard'] = [
      'title' => $this->t('Access the ESL Dashboard'),
    ];

    return $permissions;
  }

}
