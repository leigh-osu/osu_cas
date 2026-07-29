<?php

namespace Drupal\eck_bundle_permissions;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions of the eck_bundle_permissions module.
 */
class EckBundlePermissionsGenerator implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new FieldUiPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Returns an array of ECK bundle permissions.
   *
   * @return array
   *   Permission labels, keyed by permission names.
   */
  public function entityPermissions(): array {
    $permissions = [];
    /** @var \Drupal\eck\Entity\EckEntityType[] $entity_types */
    $entity_types = $this->entityTypeManager
      ->getStorage('eck_entity_type')
      ->loadMultiple();

    foreach ($entity_types as $entity_type) {
      $bundles = $this->entityTypeManager
        ->getStorage(sprintf('%s_type', $entity_type->id()))
        ->loadMultiple();

      foreach ($bundles as $bundle) {
        // This permission is generated on behalf of a bundle, therefore
        // add the bundle as a config dependency.
        $dependencies = [
          $bundle->getConfigDependencyKey() => [$bundle->getConfigDependencyName()],
        ];

        // This permission is already checked in EckEntityAccessControlHandler,
        // but not assignable through the interface.
        $permissions[sprintf('create %s entities of bundle %s', $entity_type->id(), $bundle->id())] = [
          'title' => $this->t('Create new %typeName %bundle entities', [
            '%typeName' => $entity_type->label(),
            '%bundle' => $bundle->label(),
          ]),
          'dependencies' => $dependencies,
        ];

        $permissions[sprintf('edit any %s entities of bundle %s', $entity_type->id(), $bundle->id())] = [
          'title' => $this->t('Edit any %entityType entities of bundle %bundle', [
            '%entityType' => $entity_type->label(),
            '%bundle' => $bundle->label(),
          ]),
          'dependencies' => $dependencies,
        ];
        $permissions[sprintf('delete any %s entities of bundle %s', $entity_type->id(), $bundle->id())] = [
          'title' => $this->t('Delete any %entityType entities of bundle %bundle', [
            '%entityType' => $entity_type->label(),
            '%bundle' => $bundle->label(),
          ]),
          'dependencies' => $dependencies,
        ];
        $permissions[sprintf('view any %s entities of bundle %s', $entity_type->id(), $bundle->id())] = [
          'title' => $this->t('View any %entityType entities of bundle %bundle', [
            '%entityType' => $entity_type->label(),
            '%bundle' => $bundle->label(),
          ]),
          'dependencies' => $dependencies,
        ];

        if ($entity_type->hasAuthorField()) {
          $permissions[sprintf('edit own %s entities of bundle %s', $entity_type->id(), $bundle->id())] = [
            'title' => $this->t('Edit own %entityType entities of bundle %bundle', [
              '%entityType' => $entity_type->label(),
              '%bundle' => $bundle->label(),
            ]),
            'dependencies' => $dependencies,
          ];
          $permissions[sprintf('delete own %s entities of bundle %s', $entity_type->id(), $bundle->id())] = [
            'title' => $this->t('Delete own %entityType entities of bundle %bundle', [
              '%entityType' => $entity_type->label(),
              '%bundle' => $bundle->label(),
            ]),
            'dependencies' => $dependencies,
          ];
          $permissions[sprintf('view own %s entities of bundle %s', $entity_type->id(), $bundle->id())] = [
            'title' => $this->t('View own %entityType entities of bundle %bundle', [
              '%entityType' => $entity_type->label(),
              '%bundle' => $bundle->label(),
            ]),
            'dependencies' => $dependencies,
          ];
        }
      }
    }

    return $permissions;
  }

}
