<?php

declare(strict_types=1);

namespace Drupal\Tests\preview_link\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\preview_link\Entity\PreviewLink;
use Drupal\preview_link\Entity\PreviewLinkInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test preview link access.
 */
#[Group('preview_link')]
#[RunTestsInSeparateProcesses]
final class PreviewLinkAccessTest extends PreviewLinkBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'filter'];

  protected NodeInterface $node;
  protected PreviewLinkInterface $previewLink;
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
    $this->previewLink = PreviewLink::create()->addEntity($this->node);
    $this->previewLink->save();
  }

  /**
   * Test the preview access service.
   */
  #[DataProvider('previewAccessDeniedDataProvider')]
  public function testPreviewAccessDenied($entity_type_id, $entity_id, $token, $expected_result): void {
    $entity = $this->container->get('entity_type.manager')->getStorage($entity_type_id)->load($entity_id);
    $access = $this->container->get('access_check.preview_link')->access($entity, $token);
    $this->assertEquals($expected_result, $access->isAllowed());
  }

  /**
   * Data provider for testPreviewAccess().
   */
  public static function previewAccessDeniedDataProvider(): array {
    return [
      'empty token' => ['node', 1, '', FALSE],
      'invalid token' => ['node', 1, 'invalid 123', FALSE],
      'invalid entity id' => ['node', 99, 'correct-token', FALSE],
    ];
  }

  /**
   * Ensure access is allowed with a valid token.
   */
  public function testPreviewAccessAllowed(): void {
    $access = $this->container->get('access_check.preview_link')->access($this->node, $this->previewLink->getToken());
    $this->assertEquals(TRUE, $access->isAllowed());
  }

}
