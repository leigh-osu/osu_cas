<?php

namespace Drupal\Tests\linkpurpose\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Basic test to confirm the module installs OK.
 *
 * @group editoria11y
 */
class InstallTest extends BrowserTestBase {
  /**
   * {@inheritDoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['linkpurpose', 'user'];

  /**
   * Basic test to make sure we can access the configuration page.
   */
  public function testConfigurationPage() {

    $user = $this->setUpAdmin();
    $route = Url::fromRoute("linkpurpose.settings");

    $this->drupalLogin($user);
    $this->drupalGet($route);
    $this->assertSession()->statusCodeEquals("200");
    $this->assertSession()->pageTextContains("Link Purpose");
    $this->drupalLogout();
  }

  /**
   * Define a new administrator user.
   */
  public function setUpAdmin() : AccountInterface {
    return $this->createUser([
      'administer linkpurpose',
    ]);
  }

}
