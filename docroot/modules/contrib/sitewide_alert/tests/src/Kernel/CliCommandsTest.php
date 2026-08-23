<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Kernel;

use Drupal\sitewide_alert\CliCommands;

/**
 * Tests the CliCommands service.
 *
 * @group sitewide_alert
 * @coversDefaultClass \Drupal\sitewide_alert\CliCommands
 */
final class CliCommandsTest extends SitewideAlertKernelTestBase {

  /**
   * The CLI commands service.
   *
   * @var \Drupal\sitewide_alert\CliCommands
   */
  private CliCommands $cli;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->cli = $this->container->get('sitewide_alert.cli_commands');
  }

  /**
   * Tests create with minimal arguments.
   *
   * @covers ::create
   */
  public function testCreateMinimal(): void {
    $this->cli->create('Test Alert', 'Test message content', []);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('sitewide_alert');
    $alerts = $storage->loadByProperties(['name' => 'Test Alert']);

    $this->assertCount(1, $alerts);
    $alert = reset($alerts);
    $this->assertEquals('Test Alert', $alert->getName());
    $this->assertEquals('Test message content', $alert->get('message')->value);
    $this->assertTrue($alert->isPublished());
  }

  /**
   * Tests create with style option.
   *
   * @covers ::create
   */
  public function testCreateWithStyleOption(): void {
    $this->cli->create('Styled Alert', 'A message', ['style' => 'primary']);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('sitewide_alert');
    $alerts = $storage->loadByProperties(['name' => 'Styled Alert']);

    $alert = reset($alerts);
    $this->assertEquals('primary', $alert->getStyle());
  }

  /**
   * Tests create with dismissible option.
   *
   * @covers ::create
   */
  public function testCreateWithDismissibleOption(): void {
    $this->cli->create('Dismissible Alert', 'A message', ['dismissible' => TRUE]);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('sitewide_alert');
    $alerts = $storage->loadByProperties(['name' => 'Dismissible Alert']);

    $alert = reset($alerts);
    $this->assertTrue($alert->isDismissible());
  }

  /**
   * Tests enable enables a disabled alert.
   *
   * @covers ::enable
   */
  public function testEnableEnablesDisabledAlert(): void {
    $alert = $this->createSiteWideAlert([
      'name' => 'Disabled Alert',
      'status' => 0,
    ]);

    $this->assertFalse($alert->isPublished());

    $count = $this->cli->enable('Disabled Alert');
    $this->assertEquals(1, $count);

    $reloaded = $this->container->get('entity_type.manager')
      ->getStorage('sitewide_alert')
      ->loadUnchanged($alert->id());
    $this->assertTrue($reloaded->isPublished());
  }

  /**
   * Tests disable without label disables all alerts.
   *
   * @covers ::disable
   */
  public function testDisableWithoutLabelDisablesAll(): void {
    $this->createSiteWideAlert(['name' => 'Alert One', 'status' => 1]);
    $this->createSiteWideAlert(['name' => 'Alert Two', 'status' => 1]);

    $count = $this->cli->disable(NULL);
    $this->assertEquals(2, $count);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('sitewide_alert');
    $alerts = $storage->loadByProperties(['status' => 1]);
    $this->assertCount(0, $alerts);
  }

  /**
   * Tests delete removes matching alert.
   *
   * @covers ::delete
   */
  public function testDeleteRemovesAlert(): void {
    $this->createSiteWideAlert(['name' => 'To Delete']);

    $count = $this->cli->delete('To Delete');
    $this->assertEquals(1, $count);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('sitewide_alert');
    $alerts = $storage->loadByProperties(['name' => 'To Delete']);
    $this->assertCount(0, $alerts);
  }

}
