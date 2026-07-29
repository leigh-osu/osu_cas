<?php

declare(strict_types=1);

namespace Drupal\Tests\preview_link\Kernel;

use Drupal\entity_test\Entity\EntityTestRevPub;
use Drupal\preview_link\Entity\PreviewLink;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Preview link entity test.
 */
#[Group('preview_link')]
#[RunTestsInSeparateProcesses]
#[CoversClass(PreviewLink::class)]
final class PreviewLinkEntityTest extends PreviewLinkBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'preview_link',
    'preview_link_test',
    'entity_test',
  ];

  /**
   * Tests getting entities.
   *
   * @covers ::getEntities
   */
  public function testGetEntities(): void {
    $previewLink = PreviewLink::create();
    $entity = EntityTestRevPub::create();
    $previewLink->entities->appendItem($entity);
    $this->assertCount(1, $previewLink->getEntities());
  }

  /**
   * Tests setting entities.
   *
   * @covers ::setEntities
   */
  public function testSetEntities(): void {
    $previewLink = PreviewLink::create();
    $entity = EntityTestRevPub::create();
    $previewLink->setEntities([$entity]);
    $this->assertCount(1, $previewLink->entities->referencedEntities());
    $previewLink->setEntities([$entity]);
    // Using setter should override previous values, therefore count is same.
    $this->assertCount(1, $previewLink->entities->referencedEntities());
  }

  /**
   * Tests adding a single entity.
   *
   * @covers ::addEntity
   */
  public function testAddEntity(): void {
    $previewLink = PreviewLink::create();
    $entity = EntityTestRevPub::create();
    $this->assertCount(0, $previewLink->entities->referencedEntities());
    $previewLink->addEntity($entity);
    $this->assertCount(1, $previewLink->entities->referencedEntities());
  }

  /**
   * Test default settings.
   *
   * @covers ::entitiesDefaultFieldSettings
   */
  public function testDefaultSettings(): void {
    $definition = \Drupal::entityTypeManager()->getDefinition('preview_link');
    $baseFields = PreviewLink::baseFieldDefinitions($definition);
    $settings = $baseFields['entities']->getSettings();
    $this->assertTrue($settings['exclude_entity_types']);
    $this->assertEquals([], $settings['entity_type_ids']);
    $this->assertEquals([
      'handler' => 'preview_link',
      'handler_settings' => [],
    ], $settings['entity_test_mulrevpub']);
  }

}
