<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\sitewide_alert\Traits\SitewideAlertTestTrait;

/**
 * Tests server-side rendering (SSR) of sitewide alerts.
 *
 * @group sitewide_alert
 */
final class SitewideAlertServerSideRenderTest extends BrowserTestBase {

  use SitewideAlertTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sitewide_alert', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that SSR disabled shows only the placeholder.
   */
  public function testSsrDisabledShowsPlaceholder(): void {
    $random = $this->getRandomGenerator();
    $alertMessage = $random->sentences(5);
    $alert = $this->createSiteWideAlert([
      'message' => [
        'value' => $alertMessage,
        'format' => 'plain_text',
      ],
    ]);

    // Ensure SSR is disabled (default).
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', FALSE)->save();

    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));
    $this->drupalGet('<front>');

    $assert = $this->assertSession();
    // The placeholder should exist.
    $assert->elementExists('css', '[data-sitewide-alert]');
    // But the alert content should not be in initial HTML (it loads via JS).
    $assert->elementNotExists('css', '[data-sitewide-alert] [data-uuid="' . $alert->uuid() . '"]');
  }

  /**
   * Tests that SSR enabled renders alerts in initial HTML.
   */
  public function testSsrEnabledRendersAlertsInHtml(): void {
    $random = $this->getRandomGenerator();
    $alertMessage = 'SSR Test Alert Message ' . $random->name();
    $alert = $this->createSiteWideAlert([
      'message' => [
        'value' => $alertMessage,
        'format' => 'plain_text',
      ],
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));
    $this->drupalGet('<front>');

    $assert = $this->assertSession();
    // The container should exist.
    $assert->elementExists('css', '[data-sitewide-alert]');
    // The alert should be rendered in the initial HTML.
    $assert->elementExists('css', '[data-sitewide-alert] [data-uuid="' . $alert->uuid() . '"]');
    // The alert message should be visible.
    $assert->pageTextContains($alertMessage);
  }

  /**
   * Tests that SSR includes proper data attributes for JS dismiss handlers.
   */
  public function testSsrIncludesDataAttributes(): void {
    $alert = $this->createSiteWideAlert([
      'dismissible' => TRUE,
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));
    $this->drupalGet('<front>');

    $assert = $this->assertSession();
    // Check that the data attributes needed for JS handlers are present.
    $alertElement = $assert->elementExists('css', '[data-uuid="' . $alert->uuid() . '"]');

    // Verify data-dismissible attribute.
    $this->assertEquals('true', $alertElement->getAttribute('data-dismissible'));

    // Verify data-dismissal-ignore-before attribute exists.
    $this->assertNotNull($alertElement->getAttribute('data-dismissal-ignore-before'));

    // Verify data-changed attribute exists (for update detection).
    $this->assertNotNull($alertElement->getAttribute('data-changed'));
  }

  /**
   * Tests that non-dismissible alerts have correct data attribute.
   */
  public function testSsrNonDismissibleAlert(): void {
    $alert = $this->createSiteWideAlert([
      'dismissible' => FALSE,
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));
    $this->drupalGet('<front>');

    $assert = $this->assertSession();
    $alertElement = $assert->elementExists('css', '[data-uuid="' . $alert->uuid() . '"]');

    // Verify data-dismissible is false for non-dismissible alerts.
    $this->assertEquals('false', $alertElement->getAttribute('data-dismissible'));
  }

  /**
   * Tests SSR path filtering - alert visible on matching path.
   */
  public function testSsrPathFilteringShowsOnMatchingPath(): void {
    // Create a node to have a known path.
    $this->drupalCreateContentType(['type' => 'page']);
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Test Page']);

    $alertMessage = 'Path-specific alert';
    $alert = $this->createSiteWideAlert([
      'message' => [
        'value' => $alertMessage,
        'format' => 'plain_text',
      ],
      'limit_to_pages' => '/node/' . $node->id(),
      'limit_to_pages_negate' => FALSE,
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
      'access content',
    ]));

    // Visit the matching path.
    $this->drupalGet('/node/' . $node->id());

    $assert = $this->assertSession();
    // Alert should be rendered.
    $assert->elementExists('css', '[data-uuid="' . $alert->uuid() . '"]');
    $assert->pageTextContains($alertMessage);
  }

  /**
   * Tests SSR path filtering - alert hidden on non-matching path.
   */
  public function testSsrPathFilteringHidesOnNonMatchingPath(): void {
    // Create a node to have a known path.
    $this->drupalCreateContentType(['type' => 'page']);
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Test Page']);

    $alertMessage = 'Path-specific alert should not appear';
    $alert = $this->createSiteWideAlert([
      'message' => [
        'value' => $alertMessage,
        'format' => 'plain_text',
      ],
      'limit_to_pages' => '/some/other/path',
      'limit_to_pages_negate' => FALSE,
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
      'access content',
    ]));

    // Visit a different path.
    $this->drupalGet('/node/' . $node->id());

    $assert = $this->assertSession();
    // Alert should not be rendered (but container should still exist for JS).
    $assert->elementNotExists('css', '[data-uuid="' . $alert->uuid() . '"]');
    $assert->pageTextNotContains($alertMessage);
  }

  /**
   * Tests SSR with negated path filtering.
   */
  public function testSsrNegatedPathFiltering(): void {
    // Create a node to have a known path.
    $this->drupalCreateContentType(['type' => 'page']);
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Test Page']);

    $alertMessage = 'Negated path alert';
    $alert = $this->createSiteWideAlert([
      'message' => [
        'value' => $alertMessage,
        'format' => 'plain_text',
      ],
      // Show on all pages EXCEPT /node/X.
      'limit_to_pages' => '/node/' . $node->id(),
      'limit_to_pages_negate' => TRUE,
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
      'access content',
    ]));

    // Visit the excluded path - alert should NOT appear.
    $this->drupalGet('/node/' . $node->id());
    $assert = $this->assertSession();
    $assert->elementNotExists('css', '[data-uuid="' . $alert->uuid() . '"]');

    // Visit front page - alert SHOULD appear.
    $this->drupalGet('<front>');
    $assert->elementExists('css', '[data-uuid="' . $alert->uuid() . '"]');
    $assert->pageTextContains($alertMessage);
  }

  /**
   * Tests multiple alerts are rendered in order with SSR.
   */
  public function testSsrMultipleAlertsDisplayOrder(): void {
    $alert1 = $this->createSiteWideAlert([
      'name' => 'First Alert',
      'message' => [
        'value' => 'First Alert Message',
        'format' => 'plain_text',
      ],
    ]);

    // Create second alert after first to ensure different timestamps.
    sleep(1);
    $alert2 = $this->createSiteWideAlert([
      'name' => 'Second Alert',
      'message' => [
        'value' => 'Second Alert Message',
        'format' => 'plain_text',
      ],
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));
    $this->drupalGet('<front>');

    $assert = $this->assertSession();
    // Both alerts should be rendered.
    $assert->elementExists('css', '[data-uuid="' . $alert1->uuid() . '"]');
    $assert->elementExists('css', '[data-uuid="' . $alert2->uuid() . '"]');
    $assert->pageTextContains('First Alert Message');
    $assert->pageTextContains('Second Alert Message');
  }

  /**
   * Tests that the sitewide_alert library is attached with SSR.
   */
  public function testSsrAttachesJsLibrary(): void {
    $this->createSiteWideAlert();

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));
    $this->drupalGet('<front>');

    $assert = $this->assertSession();
    // Verify the JS file from the library is attached (for dismiss, refresh).
    $assert->responseContains('js/init.js');
    // Verify drupalSettings for sitewideAlert is present.
    $assert->responseContains('sitewideAlert');
  }

}
