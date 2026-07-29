<?php

declare(strict_types=1);

namespace Drupal\Tests\node_revision_delete\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;
use Drupal\node_revision_delete\NodeRevisionDeleteInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the node revision delete service.
 */
#[Group('node_revision_delete')]
#[RunTestsInSeparateProcesses]
class NodeRevisionDeleteServiceTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'field',
    'filter',
    'language',
    'node',
    'node_revision_delete',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('node_revision_delete', [NodeRevisionDeleteInterface::QUEUE_SEMAPHORE_TABLE]);
    $this->installConfig([
      'system',
      'filter',
      'node',
    ]);

    // Add the Dutch language.
    ConfigurableLanguage::createFromLangcode('nl')->save();

    // Create a node type that allows revisions.
    $this->createContentType(['type' => 'page', 'revision' => TRUE]);
  }

  /**
   * Test getPreviousRevisionIds() with explicit langcode parameter.
   */
  public function testGetPreviousRevisionIdsWithLangcode(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    /** @var \Drupal\node_revision_delete\NodeRevisionDeleteInterface $service */
    $service = $this->container->get('node_revision_delete');

    // Create a node with several English revisions.
    $node = $this->createNode(['type' => 'page', 'title' => 'English v1']);
    for ($i = 2; $i <= 5; $i++) {
      $node->setTitle('English v' . $i);
      $node = $node_storage->createRevision($node);
      $node->save();
    }

    // The node now has 5 English revisions total (1 original + 4 new).
    // VIDs: 1, 2, 3, 4, 5.
    $this->assertRevisionCount(5, $node);
    $latest_en_vid = (int) $node->getRevisionId();

    // Add a Dutch translation and create revisions for it.
    $translation = $node->addTranslation('nl', ['title' => 'Dutch v1']);
    $new_revision = $node_storage->createRevision($translation);
    $new_revision->save();
    for ($i = 2; $i <= 3; $i++) {
      $translation = $node_storage->load($node->id())->getTranslation('nl');
      $translation->setTitle('Dutch v' . $i);
      $new_revision = $node_storage->createRevision($translation);
      $new_revision->save();
    }
    $latest_nl_vid = (int) $new_revision->getRevisionId();

    // Get previous English revisions before the latest English VID.
    $en_revisions = $service->getPreviousRevisionIds((int) $node->id(), $latest_en_vid, 'en');
    // Should return 4 English revisions (VIDs 1-4, all before VID 5).
    $this->assertCount(4, $en_revisions);
    foreach ($node_storage->loadMultipleRevisions($en_revisions) as $revision) {
      $this->assertInstanceOf(NodeInterface::class, $revision);
      $this->assertTrue((bool) $revision->getTranslation('en')->isRevisionTranslationAffected(), 'Revision have a Dutch translation.');
    }

    // Get previous Dutch revisions before the latest Dutch VID.
    $nl_revisions = $service->getPreviousRevisionIds((int) $node->id(), $latest_nl_vid, 'nl');
    // Should return 2 Dutch revisions (VIDs 6-7, since VID 8 is excluded).
    $this->assertCount(2, $nl_revisions);
    foreach ($node_storage->loadMultipleRevisions($nl_revisions) as $revision) {
      $this->assertInstanceOf(NodeInterface::class, $revision);
      $this->assertTrue((bool) $revision->getTranslation('nl')->isRevisionTranslationAffected(), 'Revision have a Dutch translation.');
    }

    // Get previous Dutch revisions before the latest English VID. Since all
    // Dutch revisions were created after the latest English VID, none should
    // be returned.
    $nl_revisions_before_en = $service->getPreviousRevisionIds((int) $node->id(), $latest_en_vid, 'nl');
    $this->assertCount(0, $nl_revisions_before_en);

    // Get previous English revisions before the latest Dutch VID. All 5
    // English revisions are older than the latest Dutch VID.
    $en_revisions_before_nl = $service->getPreviousRevisionIds((int) $node->id(), $latest_nl_vid, 'en');
    $this->assertCount(5, $en_revisions_before_nl);
  }

  /**
   * Test getPreviousRevisions() without langcode falls back to current language.
   */
  public function testGetPreviousRevisionsDefaultLanguage(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    /** @var \Drupal\node_revision_delete\NodeRevisionDeleteInterface $service */
    $service = $this->container->get('node_revision_delete');

    // Create a node with revisions.
    $node = $this->createNode(['type' => 'page']);
    for ($i = 0; $i < 3; $i++) {
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
    }
    $latest_vid = (int) $node->getRevisionId();

    // Without langcode, should use the current language (English in tests).
    $revisions_default = $service->getPreviousRevisionIds((int) $node->id(), $latest_vid);
    $revisions_explicit = $service->getPreviousRevisionIds((int) $node->id(), $latest_vid, 'en');
    $this->assertCount(count($revisions_explicit), $revisions_default);
  }

  /**
   * Asserts that the number of revisions for a node is as expected.
   *
   * @param int $expected
   *   The expected number of revisions.
   * @param \Drupal\node\NodeInterface $node
   *   The node to count revisions for.
   */
  private function assertRevisionCount(int $expected, NodeInterface $node): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $query = $storage->getQuery()->allRevisions()->condition('nid', $node->id())->accessCheck(FALSE);
    $revision_ids = array_keys($query->execute());
    $this->assertCount($expected, $revision_ids, sprintf('Expected %d revisions, but found %d.', $expected, count($revision_ids)));
  }

}
