<?php

namespace Drupal\Tests\domain_sso_admin_toolbar\Functional;

use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\Tests\domain\Traits\DomainLoginTestTrait;

/**
 * Tests the domain SSO admin toolbar features.
 *
 * @group domain_sso_admin_toolbar
 */
class DomainSsoAdminToolbarTest extends DomainTestBase {

  use DomainLoginTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_sso',
    'domain_sso_admin_toolbar',
    'toolbar',
    'admin_toolbar',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test that the domain switcher appears in the toolbar.
   */
  public function testDomainSwitcherInToolbar() {
    // Create three domains programmatically.
    $this->domainCreateTestDomains(3);

    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $default_domain = $storage->loadDefaultDomain();
    // Set the default domain as not default.
    // Required to avoid session cookie sharing with subdomains.
    $default_domain->set('is_default', FALSE)->save();

    // Get the domains.
    $domains = $this->getDomains();
    $one = $domains['one_example_com'];
    // Set the one domain as the new default domain.
    $one->set('is_default', TRUE)->save();

    // Create an admin user with toolbar access.
    $admin = $this->drupalCreateUser([
      'access toolbar',
      'use domain sso admin toolbar',
      'access administration pages',
      'administer domains',
    ]);

    // Login as the admin user on domain one.
    $this->drupalLoginOnHost($admin, rtrim($one->getPath(), '/'));

    // Visit the admin page on domain one.
    $url = $one->getPath() . 'admin';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);

    // Check that the toolbar is present.
    $this->assertSession()->elementExists('css', '#toolbar-administration');

    // Check that the domain switcher item is present.
    $this->assertSession()->linkExists('Domains');

    // Check that all domains are listed in the toolbar.
    foreach ($domains as $domain) {
      $this->assertSession()->pageTextContains($domain->label());
    }

    // Check that the current domain is marked as current.
    $this->assertSession()->pageTextContains($one->label() . ' (current)');
  }

  /**
   * Test that the domain switcher does not appear with only one domain.
   */
  public function testDomainSwitcherHiddenWithOneDomain() {
    // Create only one domain.
    $this->domainCreateTestDomains(1);

    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $domains = $storage->loadMultipleSorted();
    $domain = reset($domains);

    // Create an admin user with toolbar access.
    $admin = $this->drupalCreateUser([
      'access toolbar',
      'use domain sso admin toolbar',
      'access administration pages',
      // Extra permission needed to access the admin page.
      // We get a 403 if the admin page is empty.
      'administer site configuration',
    ]);

    // Login as the admin user.
    $this->drupalLoginOnHost($admin, rtrim($domain->getPath(), '/'));

    // Visit the admin page.
    $url = $domain->getPath() . 'admin';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);

    // Check that the domain switcher is NOT present (only one domain).
    $this->assertSession()->linkNotExists('Domains');
  }

  /**
   * Test that the domain switcher does not appear for anonymous users.
   */
  public function testDomainSwitcherNotVisibleToAnonymous() {
    // Create three domains.
    $this->domainCreateTestDomains(3);

    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $domains = $storage->loadMultipleSorted();
    $domain = reset($domains);

    // Visit as anonymous user.
    $this->drupalGet($domain->getPath());

    // Check that the domain switcher is NOT present for anonymous users.
    $this->assertSession()->linkNotExists('Domains');
  }

  /**
   * Test that the domain switcher does not appear for user without permission.
   */
  public function testDomainSwitcherNotVisibleWithoutPermission() {
    // Create three domains.
    $this->domainCreateTestDomains(3);

    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $domains = $storage->loadMultipleSorted();
    $domain = reset($domains);

    // Create an admin user with toolbar access.
    $admin = $this->drupalCreateUser([
      'access toolbar',
      'access administration pages',
      // Extra permission needed to access the admin page.
      // We get a 403 if the admin page is empty.
      'administer site configuration',
    ]);

    // Login as the admin user.
    $this->drupalLoginOnHost($admin, rtrim($domain->getPath(), '/'));

    // Visit the admin page.
    $url = $domain->getPath() . 'admin';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);

    // Check that the domain switcher is NOT present for anonymous users.
    $this->assertSession()->linkNotExists('Domains');
  }

  /**
   * Test domain switching via the toolbar with SSO.
   */
  public function testDomainSwitchingViaToolbar() {
    // Create three domains programmatically.
    $this->domainCreateTestDomains(3);

    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $default_domain = $storage->loadDefaultDomain();
    // Set the default domain as not default.
    $default_domain->set('is_default', FALSE)->save();

    // Get the domains.
    $domains = $this->getDomains();
    $one = $domains['one_example_com'];
    $two = $domains['two_example_com'];

    // Set domain one as the new default domain (SSO issuer).
    $one->set('is_default', TRUE)->save();

    // Create an admin user.
    $admin = $this->drupalCreateUser([
      'use domain sso admin toolbar',
      'access administration pages',
      'administer domains',
    ]);

    // Login as the admin user on domain one.
    $this->drupalLoginOnHost($admin, rtrim($one->getPath(), '/'));

    // Verify we're logged in on domain one.
    $url = $one->getPath() . 'admin';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);

    // Verify we're NOT logged in on domain two yet.
    $url = $two->getPath() . 'admin';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    // Now switch to domain two using the toolbar switcher.
    $switch_url = $one->getPath() . 'admin/domain-sso-switch/' . $two->id();
    $this->drupalGet($switch_url);

    // After SSO handshake, we should be logged in on domain two.
    // Verify we can access admin pages on domain two now.
    $current_url = $this->getSession()->getCurrentUrl();
    $this->assertStringContainsString($two->getPath() . 'admin', $current_url);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test switching to the current domain shows a message.
   */
  public function testSwitchingToCurrentDomain() {
    // Create two domains.
    $this->domainCreateTestDomains(2);

    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $domains = $storage->loadMultipleSorted();
    $domain = reset($domains);

    // Create an admin user.
    $admin = $this->drupalCreateUser([
      'use domain sso admin toolbar',
      'access administration pages',
    ]);

    // Login as the admin user.
    $this->drupalLoginOnHost($admin, rtrim($domain->getPath(), '/'));

    // Try to switch to the same domain we're already on.
    $switch_url = $domain->getPath() . 'admin/domain-sso-switch/' . $domain->id();
    $this->drupalGet($switch_url);

    // Should see a status message.
    $this->assertSession()->pageTextContains('You are already on the');
    $this->assertSession()->pageTextContains('domain');
  }

  /**
   * Test switching with invalid domain ID shows error.
   */
  public function testSwitchingToInvalidDomain() {
    // Create two domains.
    $this->domainCreateTestDomains(2);

    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $domains = $storage->loadMultipleSorted();
    $domain = reset($domains);

    // Create an admin user.
    $admin = $this->drupalCreateUser([
      'use domain sso admin toolbar',
      'access administration pages',
    ]);

    // Login as the admin user.
    $this->drupalLoginOnHost($admin, rtrim($domain->getPath(), '/'));

    // Try to switch to a non-existent domain.
    $switch_url = $domain->getPath() . 'admin/domain-sso-switch/invalid_domain_id';
    $this->drupalGet($switch_url);

    // Should see an error message.
    $this->assertSession()->pageTextContains('Invalid domain specified');
  }

  /**
   * Test that unauthenticated users cannot access the switch route.
   */
  public function testUnauthenticatedUserCannotSwitch() {
    // Create two domains.
    $this->domainCreateTestDomains(2);

    /** @var \Drupal\domain\DomainStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('domain');
    $domains = $storage->loadMultipleSorted();
    $domain = reset($domains);

    // Try to access the switch route as anonymous.
    $switch_url = $domain->getPath() . 'admin/domain-sso-switch/' . $domain->id();
    $this->drupalGet($switch_url);

    // Should be redirected to login or see access denied.
    // The route requires 'use domain sso admin toolbar' permission.
    $this->assertSession()->statusCodeNotEquals(200);
  }

}
