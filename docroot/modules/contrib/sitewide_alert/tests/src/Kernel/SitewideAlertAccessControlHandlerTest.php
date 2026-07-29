<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Kernel;

/**
 * Tests the SitewideAlertAccessControlHandler.
 *
 * @group sitewide_alert
 * @coversDefaultClass \Drupal\sitewide_alert\SitewideAlertAccessControlHandler
 */
final class SitewideAlertAccessControlHandlerTest extends SitewideAlertKernelTestBase {

  /**
   * Tests view published requires permission.
   *
   * @covers ::checkAccess
   */
  public function testViewPublishedRequiresPermission(): void {
    $alert = $this->createSiteWideAlert(['status' => 1]);
    $this->setUpCurrentUser([], ['view published sitewide alert entities']);
    $this->assertTrue($alert->access('view'));
  }

  /**
   * Tests view published is denied without permission.
   *
   * @covers ::checkAccess
   */
  public function testViewPublishedDeniedWithoutPermission(): void {
    $alert = $this->createSiteWideAlert(['status' => 1]);
    $this->setUpCurrentUser([], []);
    $this->assertFalse($alert->access('view'));
  }

  /**
   * Tests view unpublished requires permission.
   *
   * @covers ::checkAccess
   */
  public function testViewUnpublishedRequiresPermission(): void {
    $alert = $this->createSiteWideAlert(['status' => 0]);
    $this->setUpCurrentUser([], ['view unpublished sitewide alert entities']);
    $this->assertTrue($alert->access('view'));
  }

  /**
   * Tests edit requires permission.
   *
   * @covers ::checkAccess
   */
  public function testEditRequiresPermission(): void {
    $alert = $this->createSiteWideAlert();
    $this->setUpCurrentUser([], ['edit sitewide alert entities']);
    $this->assertTrue($alert->access('update'));
  }

  /**
   * Tests delete requires permission.
   *
   * @covers ::checkAccess
   */
  public function testDeleteRequiresPermission(): void {
    $alert = $this->createSiteWideAlert();
    $this->setUpCurrentUser([], ['delete sitewide alert entities']);
    $this->assertTrue($alert->access('delete'));
  }

  /**
   * Tests create requires permission.
   *
   * @covers ::checkCreateAccess
   */
  public function testCreateRequiresPermission(): void {
    $this->setUpCurrentUser([], ['add sitewide alert entities']);
    $accessControlHandler = $this->container->get('entity_type.manager')
      ->getAccessControlHandler('sitewide_alert');
    $this->assertTrue($accessControlHandler->createAccess());
  }

  /**
   * Tests that create access is denied without the add permission.
   *
   * @covers ::checkCreateAccess
   */
  public function testCreateDeniedWithoutPermission(): void {
    $this->setUpCurrentUser([], []);

    $accessControlHandler = $this->container->get('entity_type.manager')
      ->getAccessControlHandler('sitewide_alert');
    $this->assertFalse($accessControlHandler->createAccess());
  }

}
