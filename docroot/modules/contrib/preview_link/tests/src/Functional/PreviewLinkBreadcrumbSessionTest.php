<?php

declare(strict_types=1);

namespace Drupal\Tests\preview_link\Functional;

use Drupal\preview_link\Entity\PreviewLink;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that breadcrumb rendering doesn't crash on canonical routes.
 *
 * When a canonical entity page renders breadcrumbs, the breadcrumb builder
 * creates sub-requests for parent path segments. These sub-requests have no
 * session. If a parent path resolves to another canonical entity route with
 * preview links, the access check calls $request->getSession() on the
 * session-less sub-request, causing a fatal error.
 */
#[Group('preview_link')]
#[RunTestsInSeparateProcesses]
final class PreviewLinkBreadcrumbSessionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'preview_link',
    'node',
    'path',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createContentType(['type' => 'page']);

    // Enable preview links for nodes.
    \Drupal::configFactory()
      ->getEditable('preview_link.settings')
      ->set('enabled_entity_types', [
        'node' => ['page'],
      ])
      ->save();
  }

  /**
   * Tests breadcrumb with path alias hierarchy doesn't crash without session.
   *
   * Reproduces the bug where PathBasedBreadcrumbBuilder creates a sub-request
   * without a session for a parent path that resolves to a canonical entity
   * route with preview links, causing getSession() to fail.
   */
  public function testBreadcrumbPathAliasHierarchyNoSession(): void {
    $this->drupalPlaceBlock('system_breadcrumb_block');

    // Create a parent node with alias /products.
    $parentNode = $this->createNode([
      'title' => 'Products',
      'status' => 1,
      'path' => ['alias' => '/products'],
    ]);

    // Create a child node with alias /products/shoes (parent path in
    // breadcrumb will resolve to /products → parent node canonical).
    $childNode = $this->createNode([
      'title' => 'Shoes',
      'status' => 1,
      'path' => ['alias' => '/products/shoes'],
    ]);

    // Create preview links for both nodes so hasPreviewLinks() returns TRUE
    // for the child node (master route) and the access check proceeds past
    // the early returns to reach $request->getSession().
    PreviewLink::create()->addEntity($parentNode)->save();
    PreviewLink::create()->addEntity($childNode)->save();

    // Visit the child node's canonical page via its alias as anonymous.
    // The breadcrumb builder will check /products (parent path), which
    // resolves to the parent node's canonical route. The access check for
    // _access_preview_link_canonical_rerouter fires on the sub-request
    // (no session), while the master route (child node canonical) also has
    // the requirement. This triggers $request->getSession() on a request
    // without a session, causing a fatal error.
    $this->drupalGet('/products/shoes');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Shoes');
  }

}
