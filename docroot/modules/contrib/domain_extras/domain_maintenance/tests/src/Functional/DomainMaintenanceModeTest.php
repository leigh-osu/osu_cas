<?php

namespace Drupal\Tests\domain_maintenance\Functional;

use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\Tests\domain\Traits\DomainLoginTestTrait;

/**
 * Tests the domain maintenance mode module.
 *
 * @group domain_sso
 *
 * @see https://www.drupal.org/project/domain_extras/issues/3545220
 */
class DomainMaintenanceModeTest extends DomainTestBase {

  use DomainLoginTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'domain',
    'domain_config',
    'domain_config_ui',
    'domain_maintenance',
  ];

  /**
   * Test domain SSO.
   */
  public function testDomainMaintenanceMode() {

    $page = $this->getSession()->getPage();

    // Create three domains programmatically.
    $this->domainCreateTestDomains(3);

    // Get the domains.
    $domains = $this->getDomains();
    $default = $domains['example_com'];
    $one = $domains['one_example_com'];
    $two = $domains['two_example_com'];

    // Create an admin user.
    $admin = $this->drupalCreateUser([
      'access administration pages',
      'administer site configuration',
      'administer software updates',
      'access site in maintenance mode',
      'administer domains',
      'administer domain config ui',
    ]);

    // Login as the admin user on domain one.
    $this->drupalLoginOnHost($admin, rtrim($one->getPath(), '/'));

    // Go to the maintenance page on domain one.
    $maintenance_url = $one->getPath() . 'admin/config/development/maintenance';
    $this->drupalGet($maintenance_url);

    // Enable domain configuration.
    $enable_link = $page->findLink('Enable domain configuration');
    $this->assertNotNull($enable_link, 'Enable domain configuration link found.');
    $enable_link->click();

    // Verify that domain configuration is enabled.
    $disable_link = $page->findLink('Remove domain configuration');
    $this->assertNotNull($disable_link, 'Remove domain configuration link found.');

    // Enable maintenance mode on domain one.
    $edit = [
      'maintenance_mode' => TRUE,
      'maintenance_mode_message' => '@site is currently under maintenance. Thank you for your patience.',
    ];
    $this->submitForm($edit, 'Save configuration');

    // Go to domain one homepage and verify that maintenance mode is enabled.
    $this->drupalGet($one->getPath());
    $this->assertSession()->pageTextContains('Operating in maintenance mode.');
    // Verify that the maintenance message is displayed to anonymous users.
    $page->findLink('Log out')->click();
    $this->drupalResetSession();
    $this->assertSession()->pageTextContains('Drupal is currently under maintenance. Thank you for your patience.');

    // Go to domain two homepage and verify that maintenance mode is not active.
    $this->drupalGet($two->getPath());
    $this->assertSession()->pageTextNotContains('Drupal is currently under maintenance.');

    // Do the same check on the default domain homepage.
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextNotContains('Drupal is currently under maintenance.');

    // Re-login as the admin user on domain one.
    $this->drupalLoginOnHost($admin, rtrim($one->getPath(), '/'));

    // Go to the maintenance page on domain one.
    $this->drupalGet($maintenance_url);

    // Disable maintenance mode on domain one.
    $edit = [
      'maintenance_mode' => FALSE,
    ];
    $this->submitForm($edit, 'Save configuration');

    // Go to domain one homepage and verify that maintenance mode is off.
    $this->drupalGet($one->getPath());
    $this->assertSession()->pageTextNotContains('Operating in maintenance mode.');
    $page->findLink('Log out')->click();
    $this->assertSession()->pageTextNotContains('Drupal is currently under maintenance.');
    $this->assertSession()->pageTextContains('Welcome!');

  }

}
