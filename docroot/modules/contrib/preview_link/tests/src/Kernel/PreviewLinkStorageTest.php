<?php

declare(strict_types=1);

namespace Drupal\Tests\preview_link\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\preview_link\Entity\PreviewLink;
use Drupal\preview_link\PreviewLinkStorage;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Preview link session test.
 */
#[Group('preview_link')]
#[RunTestsInSeparateProcesses]
#[CoversClass(PreviewLinkStorage::class)]
final class PreviewLinkStorageTest extends PreviewLinkBase {

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
    $this->node = $this->createNode();
    $this->storage = $this->container->get('entity_type.manager')->getStorage('preview_link');
  }

  /**
   * Ensure preview link creation works.
   */
  public function testCreatePreviewLink(): void {
    $preview_link = PreviewLink::create()->addEntity($this->node);
    $this->assertIsString($preview_link->getToken());

    $preview_link = PreviewLink::create()->addEntity($this->node);
    $preview_link->save();
    $this->assertIsString($preview_link->getToken());
  }

  /**
   * Ensure we can re-generate a token.
   */
  public function testRegenerateToken(): void {
    $preview_link = PreviewLink::create()->addEntity($this->node);
    $preview_link->save();
    $current_token = $preview_link->getToken();
    $current_timestamp = $preview_link->getGeneratedTimestamp();

    // Regenerate and ensure it changed.
    $preview_link->regenerateToken(TRUE);
    $preview_link->save();

    $this->assertNotEquals($current_token, $preview_link->getToken());
    $this->assertNotEquals($current_timestamp, $preview_link->getGeneratedTimestamp());
  }

}
