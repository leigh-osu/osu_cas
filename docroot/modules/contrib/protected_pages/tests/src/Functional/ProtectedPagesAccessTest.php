<?php

namespace Drupal\Tests\protected_pages\Functional;

use Drupal\Core\Url;
use Drupal\Core\Language\LanguageInterface;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\BrowserTestBase;

/**
 * Provides functional tests for access to protected pages and configuration.
 *
 * @group protected_pages
 */
class ProtectedPagesAccessTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'user',
    'path_alias',
    'protected_pages',
  ];

  /**
   * Default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to 'access protected page password screen'.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * A user with permission to 'bypass pages password protection'.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $bypassUser;

  /**
   * A user with permission to 'administer protected pages configuration'.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The protected pages storage service.
   *
   * @var \Drupal\protected_pages\ProtectedPagesStorage
   */
  protected $protectedPagesStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Initialize the protected pages storage service.
    $this->protectedPagesStorage = \Drupal::service('protected_pages.storage');

    // Create users.
    $this->user = $this->drupalCreateUser(['access protected page password screen']);
    $this->bypassUser = $this->drupalCreateUser(['bypass pages password protection']);
    $this->adminUser = $this->drupalCreateUser(['administer protected pages configuration']);

    // Create a content type and nodes for testing.
    $this->drupalCreateContentType(['type' => 'page']);
    // Node for exact path testing (/node/1).
    $this->drupalCreateNode(['type' => 'page', 'title' => 'Test Node']);
    // Nodes for wildcard testing with aliases.
    $node_page1 = $this->drupalCreateNode(['type' => 'page', 'title' => 'Content Page 1']);
    $node_page2 = $this->drupalCreateNode(['type' => 'page', 'title' => 'Content Sub Page']);
    // Create aliases for wildcard paths.
    $this->createPathAlias('/node/' . $node_page1->id(), '/content/page1');
    $this->createPathAlias('/node/' . $node_page2->id(), '/content/sub/page2');

    // Enable page caching for all tests.
    $config = $this->config('system.performance');
    $config->set('cache.page.max_age', 300);
    $config->save();

    drupal_flush_all_caches();
  }

  /**
   * Create a path alias for a system path.
   */
  protected function createPathAlias(string $path, string $alias, string $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED): PathAlias {
    $pa = PathAlias::create([
      'path' => $path,
      'alias' => $alias,
      'langcode' => $langcode,
    ]);
    $pa->save();

    // Make the alias visible immediately during this request.
    if (\Drupal::hasService('path_alias.manager')) {
      \Drupal::service('path_alias.manager')->cacheClear();
    }

    return $pa;
  }

  /**
   * Tests access to a protected page with an exact path.
   */
  public function testProtectedPageAccess() {
    // Protect the node.
    $page_data = [
      'password' => bin2hex(random_bytes(9)),
      'title' => 'This is a Node Title',
      'path' => '/node/1',
    ];
    $this->protectedPagesStorage->insertProtectedPage($page_data);

    $page_path = '/node/1';

    // View the node as an anonymous user; should get Access Denied.
    $this->drupalGet($page_path);
    $this->assertSession()->statusCodeEquals(403);

    // View again to ensure page caching doesn’t break protection.
    // See https://www.drupal.org/project/protected_pages/issues/2973524.
    $this->drupalGet($page_path);
    $this->assertSession()->statusCodeEquals(403);

    // Login as a user with 'access protected page password screen' permission.
    $this->drupalLogin($this->user);
    $this->drupalGet($page_path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Enter Password');
    // Omit '/web' locally; GitLab CI still needs it due to core path handling.
    // @todo Remove '/web' when CI no longer adds it to destinations.
    $destination = '/web/node/1';
    $this->assertSession()->addressEquals(
      Url::fromUri('internal:/protected-page', [
        'query' => ['destination' => $destination, 'protected_page' => 1],
      ])
    );
    $this->drupalLogout();
  }

  /**
   * Tests access to the protected pages configuration screen.
   */
  public function testProtectedPageConfigurationAccess() {
    // Test access with a user lacking 'administer protected pages
    // configuration'.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/system/protected_pages');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogout();

    // Test access with a user having 'administer protected pages
    // configuration'.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/protected_pages');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalLogout();
  }

  /**
   * Tests wildcard path matching for protected pages.
   */
  public function testWildcardPathProtection() {
    // Create a protected page with a wildcard path.
    $password1 = bin2hex(random_bytes(9));
    $page_data = [
      'path' => '/content/*',
      'password' => $password1,
      'title' => 'Wildcard Protected Path',
      'pid' => 1,
    ];
    $this->protectedPagesStorage->insertProtectedPage($page_data);

    // Test case 1: Normal user accessing a wildcard-protected
    // path (non-entity).
    $this->drupalLogin($this->user);
    $this->drupalGet('/content/page1');
    $this->assertSession()->statusCodeEquals(200);
    // Omit '/web' locally; GitLab CI still needs it due to core path handling.
    // @todo Remove '/web' when CI no longer adds it to destinations.
    $destination = '/web/content/page1';
    $this->assertSession()->addressEquals(
      Url::fromUri('internal:/protected-page', [
        'query' => ['destination' => $destination, 'protected_page' => 1],
      ])
    );

    // Test case 2: Normal user accessing a sub-path matching the wildcard.
    $this->drupalGet('/content/sub/page2');
    $this->assertSession()->statusCodeEquals(200);
    // Omit '/web' locally; GitLab CI still needs it due to core path handling.
    // @todo Remove '/web' when CI no longer adds it to destinations.
    $destination = '/web/content/sub/page2';
    $this->assertSession()->addressEquals(
      Url::fromUri('internal:/protected-page', [
        'query' => ['destination' => $destination, 'protected_page' => 1],
      ])
    );

    // Test case 3: Simulate correct password entry via form submission.
    $this->drupalGet('/content/page1');
    $this->submitForm(['password' => $password1], 'Authenticate');
    // Access granted, route exists.
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalLogout();

    // Test case 4: User with bypass permission.
    $this->drupalLogin($this->bypassUser);
    $this->drupalGet('/content/page1');
    // No redirect, route exists.
    $this->assertSession()->statusCodeEquals(200);

    // Test case 5: Non-matching path.
    $this->drupalGet('/other/page');
    // Non-protected, non-existent route.
    $this->assertSession()->statusCodeEquals(404);

    // Test case 6: Node path with wildcard (entity-based).
    $password2 = bin2hex(random_bytes(9));
    $node_page_data = [
      'path' => '/node/*',
      'password' => $password2,
      'title' => 'Wildcard Node Path',
      'pid' => 2,
    ];
    $this->protectedPagesStorage->insertProtectedPage($node_page_data);
    $this->drupalLogin($this->user);
    $this->drupalGet('/node/1');
    $this->assertSession()->statusCodeEquals(200);
    // Omit '/web' locally; GitLab CI still needs it due to core path handling.
    // @todo Remove '/web' when CI no longer adds it to destinations.
    $destination = '/web/node/1';
    $this->assertSession()->addressEquals(
      Url::fromUri('internal:/protected-page', [
        'query' => ['destination' => $destination, 'protected_page' => 2],
      ])
    );
    // Simulate correct password entry via form submission.
    $this->drupalGet('/node/1');
    $this->submitForm(['password' => $password2], 'Authenticate');
    // Access granted, route exists.
    $this->assertSession()->statusCodeEquals(200);

    // Test case 7: Ensure /protected-page itself is not redirected.
    $this->drupalGet('/node/1');
    // No redirect loop.
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalLogout();
  }

}
