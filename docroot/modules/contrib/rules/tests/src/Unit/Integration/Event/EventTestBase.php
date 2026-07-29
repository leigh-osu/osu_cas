<?php

declare(strict_types=1);

namespace Drupal\Tests\rules\Unit\Integration\Event;

use Drupal\Tests\rules\Unit\Integration\RulesEntityIntegrationTestBase;
use Drupal\rules\Core\RulesEventManager;

/**
 * Base class for testing Rules Event definitions.
 *
 * @group RulesEvent
 */
abstract class EventTestBase extends RulesEntityIntegrationTestBase {

  /**
   * The Rules event plugin manager.
   *
   * @var \Drupal\rules\Core\RulesEventManager
   */
  protected $eventManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->moduleHandler->getModuleDirectories()
      ->willReturn(['rules' => __DIR__ . '/../../../../../']);
    $this->eventManager = new RulesEventManager($this->moduleHandler->reveal(), $this->entityTypeBundleInfo->reveal());
  }

}
