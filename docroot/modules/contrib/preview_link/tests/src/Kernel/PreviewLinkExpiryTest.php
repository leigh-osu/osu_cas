<?php

declare(strict_types=1);

namespace Drupal\Tests\preview_link\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\preview_link\Entity\PreviewLink;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Preview link expiry test.
 */
#[Group('preview_link')]
#[RunTestsInSeparateProcesses]
final class PreviewLinkExpiryTest extends PreviewLinkBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'filter'];

  protected NodeInterface $node;
  protected EntityStorageInterface $storage;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(['node', 'filter']);
    $this->createContentType(['type' => 'page']);
    $this->storage = $this->container->get('entity_type.manager')->getStorage('preview_link');
    $this->node = $this->createNode();
  }

  /**
   * Test preview links are automatically expired on cron.
   */
  public function testPreviewLinkExpires(): void {
    $nonExpired = PreviewLink::create()
      ->addEntity($this->node)
      ->setExpiry(new \DateTimeImmutable('+1 week'));
    $nonExpired->save();
    $nonExpiredId = $nonExpired->id();

    PreviewLink::create()
      ->addEntity($this->node)
      ->setExpiry(new \DateTimeImmutable('-1 week'))
      ->save();

    preview_link_cron();
    // Only the non-expired preview link remains.
    $this->assertEquals([$nonExpiredId], array_keys($this->storage->loadMultiple()));
  }

}
