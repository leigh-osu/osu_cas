<?php

namespace Drupal\Tests\entitygroupfield\Kernel;

use Drupal\group\PermissionScopeInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\entitygroupfield\Traits\GroupCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Defines an abstract test base for entitygroupfield kernel tests.
 */
abstract class EntityGroupFieldKernelTestBase extends KernelTestBase {

  use UserCreationTrait;
  use GroupCreationTrait;

  /**
   * The group type to run this test on.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupType;

  /**
   * The group admin role.
   *
   * @var \Drupal\group\Entity\GroupRoleInterface
   */
  protected $adminRole;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'group',
    'flexible_permissions',
    'entity',
    'field',
    'options',
    'entitygroupfield',
  ];

  /**
   * The group relation type plugin manager.
   *
   * @var \Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface
   */
  protected $groupContentPluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    if (version_compare(\Drupal::VERSION, '11.0.0', '<')) {
      static::$modules[] = 'variationcache';
    }

    parent::setUp();

    if (version_compare(\Drupal::VERSION, '10.2.0', '<=')) {
      $this->installSchema('system', ['sequences']);
    }

    $this->installEntitySchema('user');
    $this->installEntitySchema('group');
    $this->installEntitySchema(entitygroupfield_get_group_relationship_id());
    $this->installConfig(['group']);

    $this->groupContentPluginManager = $this->container->get('group_relation_type.manager');

    // Create a default group type.
    $this->groupType = $this->createGroupType([
      'id' => 'default',
      'label' => 'Default',
      'creator_membership' => FALSE,
    ]);
    $this->adminRole = $this->createGroupRole([
      'group_type' => $this->groupType->id(),
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'admin' => TRUE,
    ]);
  }

}
