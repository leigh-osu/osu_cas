<?php

declare(strict_types=1);

namespace Drupal\Tests\group_content_menu\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\group_content_menu\Entity\GroupContentMenu;
use Drupal\group_content_menu\Entity\GroupContentMenuType;

/**
 * @coversDefaultClass \Drupal\group_content_menu\Entity\GroupContentMenu
 */
class GroupContentMenuEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'group_content_menu',
    'group',
    'flexible_permissions',
    'user',
    'workspaces',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('group_content_menu');
    GroupContentMenuType::create([
      'id' => 'nav',
      'label' => 'Nav',
    ])->save();
  }

  /**
   * Tests the published status.
   */
  public function testPublished(): void {
    $menu1 = GroupContentMenu::create([
      'bundle' => 'nav',
      'label' => 'Menu1',
    ]);
    $menu1->save();

    // Should be published by default.
    static::assertTrue($menu1->isPublished());

    // Check that this can be changed.
    $menu1->setUnpublished()->save();
    static::assertFalse($menu1->isPublished());
  }

  /**
   * Tests revisions of the menu.
   */
  public function testRevisions(): void {
    $menu1 = GroupContentMenu::create([
      'bundle' => 'nav',
      'label' => 'Menu1',
    ]);
    $menu1->save();
    static::assertTrue($menu1->isDefaultRevision());
    $originalRevisionId = $menu1->getRevisionId();

    $menu1->set('label', 'Menu1 - Changed');
    $menu1->setNewRevision(TRUE);
    $menu1->save();
    $newRevisionId = $menu1->getRevisionId();

    static::assertNotSame($originalRevisionId, $newRevisionId);

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('group_content_menu');
    static::assertEquals('Menu1', $storage->loadRevision($originalRevisionId)->label());
    static::assertEquals('Menu1 - Changed', $storage->loadRevision($newRevisionId)->label());
  }

  /**
   * Test that group_content_menu is supported by workspaces.
   */
  public function testWorkspaceCompatibility(): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('group_content_menu');
    /** @var \Drupal\workspaces\WorkspaceInformationInterface $info_service */
    $infoService = \Drupal::service('workspaces.information');
    static::assertTrue($infoService->isEntityTypeSupported($entityType));
  }

}
