<?php

namespace Drupal\Tests\domain_sso\Functional;

use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\Tests\domain\Traits\DomainLoginTestTrait;

/**
 * Tests the domain sso features.
 *
 * @group domain_sso
 *
 * @see https://www.drupal.org/project/domain_extras/issues/3545807
 */
class DomainSsoTest extends DomainTestBase {

  use DomainLoginTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_sso',
  ];

  /**
   * Test domain SSO.
   */
  public function testDomainSso() {

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
    $default = $domains['example_com'];
    $one = $domains['one_example_com'];
    // Set the one domain as the new default domain.
    // It will be used as the SSO handshake issuer.
    $one->set('is_default', TRUE)->save();
    // We will try to log in domain two using SSO.
    $two = $domains['two_example_com'];

    // Create an admin user.
    $admin = $this->drupalCreateUser([
      'access administration pages',
      'administer domains',
    ]);

    // Login as the admin user on domain one.
    $this->drupalLoginOnHost($admin, rtrim($one->getPath(), '/'));

    // Check we are logged in on domain one.
    $url = $one->getPath() . 'admin';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);

    // Check that we are not logged in on domain two.
    $url = $two->getPath() . 'admin';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    // Check that the SSO link is present on domain two admin page.
    $this->assertSession()->linkExists('SSO');

    // Call the SSO URL on domain two.
    $url = $two->getPath() . 'domain-sso';
    $this->drupalGet($url);

    // Check that we have been successfully redirected to domain two admin page.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Administer settings.');
    // Check that the SSO link is not present once connected.
    $this->assertSession()->linkNotExists('SSO');

    // Check that we are still not logged in on the original default domain.
    $url = $default->getPath() . 'admin';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

  }

}
