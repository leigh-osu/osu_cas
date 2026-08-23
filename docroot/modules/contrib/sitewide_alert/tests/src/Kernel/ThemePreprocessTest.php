<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Kernel;

/**
 * Tests theme preprocess and suggestion functions from sitewide_alert.module.
 *
 * @group sitewide_alert
 */
final class ThemePreprocessTest extends SitewideAlertKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpCurrentUser([], ['view published sitewide alert entities']);
  }

  /**
   * Builds the render element variables for a given alert.
   *
   * @param \Drupal\sitewide_alert\Entity\SitewideAlertInterface $alert
   *   The alert entity.
   *
   * @return array
   *   The elements array suitable for preprocess/suggestions functions.
   */
  protected function buildElements($alert): array {
    $viewBuilder = $this->container->get('entity_type.manager')
      ->getViewBuilder('sitewide_alert');
    $build = $viewBuilder->view($alert);

    return [
      '#sitewide_alert' => $alert,
      '#view_mode' => 'full',
    ] + $build;
  }

  /**
   * Tests theme suggestions for a dismissible alert.
   */
  public function testSuggestionsDismissible(): void {
    $alert = $this->createSiteWideAlert([
      'style' => 'primary',
      'dismissible' => TRUE,
    ]);

    $variables = ['elements' => $this->buildElements($alert)];
    $suggestions = sitewide_alert_theme_suggestions_sitewide_alert($variables);

    $this->assertContains('sitewide_alert__primary', $suggestions);
    $this->assertContains('sitewide_alert__dismissible', $suggestions);
    $this->assertContains('sitewide_alert__primary__dismissible', $suggestions);
  }

  /**
   * Tests theme suggestions for a non-dismissible alert.
   */
  public function testSuggestionsNotDismissible(): void {
    $alert = $this->createSiteWideAlert([
      'style' => 'primary',
      'dismissible' => FALSE,
    ]);

    $variables = ['elements' => $this->buildElements($alert)];
    $suggestions = sitewide_alert_theme_suggestions_sitewide_alert($variables);

    $this->assertContains('sitewide_alert__notdismissible', $suggestions);
    $this->assertContains('sitewide_alert__primary__notdismissible', $suggestions);
    $this->assertNotContains('sitewide_alert__dismissible', $suggestions);
  }

  /**
   * Tests preprocess sets correct CSS classes.
   */
  public function testPreprocessCssClasses(): void {
    $alert = $this->createSiteWideAlert([
      'style' => 'primary',
    ]);

    $variables = [
      'elements' => $this->buildElements($alert),
      'attributes' => [],
    ];
    template_preprocess_sitewide_alert($variables);

    $this->assertContains('sitewide-alert', $variables['attributes']['class']);
    $this->assertContains('alert', $variables['attributes']['class']);
    $this->assertContains('alert-primary', $variables['attributes']['class']);
  }

  /**
   * Tests preprocess sets correct data attributes.
   */
  public function testPreprocessDataAttributes(): void {
    $alert = $this->createSiteWideAlert([
      'dismissible' => TRUE,
      'dismissible_ignore_before_time' => 1234567890,
    ]);

    $variables = [
      'elements' => $this->buildElements($alert),
      'attributes' => [],
    ];
    template_preprocess_sitewide_alert($variables);

    $this->assertArrayHasKey('data-uuid', $variables['attributes']);
    $this->assertEquals($alert->uuid(), $variables['attributes']['data-uuid']);

    $this->assertArrayHasKey('data-changed', $variables['attributes']);

    $this->assertArrayHasKey('data-dismissible', $variables['attributes']);
    $this->assertEquals('true', $variables['attributes']['data-dismissible']);

    $this->assertArrayHasKey('data-dismissal-ignore-before', $variables['attributes']);
    $this->assertEquals(1234567890, $variables['attributes']['data-dismissal-ignore-before']);
  }

}
