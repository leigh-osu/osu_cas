<?php

namespace Drupal\pdf_using_mpdf;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generate permissions dynamically for various content types.
 *
 * @package Drupal\pdf_using_mpdf
 */
class PdfUsingMpdfNodeTypePermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor for GeneratePermissions class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service definition.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Instantiates a new instance of the class.
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Return permissions.
   *
   * @return array|array[]
   *   Permissions array.
   */
  public function accessPermissions() {
    $types = $this->entityTypeManager
      ->getStorage('node_type')
      ->loadMultiple();

    $types = array_map(function ($type) {
      return [
        'id' => $type->id(),
        'label' => $type->label(),
      ];
    }, $types);

    return $this->createPerm($types);
  }

  /**
   * Create node type permissions.
   *
   * @param array $types
   *   Content types.
   *
   * @return array[]
   *   Type permissions array.
   */
  public function createPerm(array $types) {
    $permissions = [];

    foreach ($types as $type) {
      $perm = [
        'generate ' . $type['id'] . ' pdf' => [
          'title' => $this->t('%type_name: Generate PDF using mPDF', ['%type_name' => $type['label']]),
        ],
      ];

      $permissions += $perm;
    }

    // Echo '<pre>';print_r($permissions);die;
    return $permissions;
  }

}
