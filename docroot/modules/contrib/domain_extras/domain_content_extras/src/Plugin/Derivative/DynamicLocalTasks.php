<?php

namespace Drupal\domain_content_extras\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides dynamic local tasks for domains.
 */
class DynamicLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $definitions = [];

    $domains = $this->entityTypeManager
      ->getStorage('domain')
      ->loadMultiple();

    $definitions['all_affiliates'] = $base_plugin_definition + [
      'title' => t('All Affiliates'),
      'route_name' => 'view.affiliated_content.page_1',
      'route_parameters' => ['arg_0' => 'all_affiliates'],
      'parent_id' => 'domain_content_extras.affiliated_content',
    ];

    foreach ($domains as $domain) {
      $definitions[$domain->id()] = $base_plugin_definition + [
        'title' => $domain->toString(),
        'route_name' => 'view.affiliated_content.page_1',
        'route_parameters' => ['arg_0' => $domain->id()],
        'parent_id' => 'domain_content_extras.affiliated_content',
      ];
    }

    return $definitions;
  }

}
