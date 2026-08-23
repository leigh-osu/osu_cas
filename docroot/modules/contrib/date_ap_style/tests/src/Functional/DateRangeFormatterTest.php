<?php

declare(strict_types=1);

namespace Drupal\Tests\date_ap_style\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the AP Style date range field formatter rendering.
 */
class DateRangeFormatterTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'field',
    'datetime',
    'datetime_range',
    'date_ap_style',
    'smart_date',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Apply the recipe - it will create the date_test content type
    // and set up the daterange and smartdate fields with AP Style formatter.
    $recipe_path = dirname(__DIR__, 2) . '/fixtures/recipe';
    $recipe = Recipe::createFromDirectory($recipe_path);
    RecipeRunner::processRecipe($recipe);
  }

  /**
   * Helper to update the display settings.
   */
  protected function updateDisplaySettings(array $settings): void {
    // Get the default display and configure the field.
    $display = EntityViewDisplay::load('node.date_test.default');
    if (!$display) {
      $display = EntityViewDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'date_test',
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    // Get existing component or create default one.
    $component = $display->getComponent('field_event_dates');
    if (!$component) {
      $component = [
        'type' => 'daterange_ap_style',
        'label' => 'above',
        'settings' => [
          'separator' => 'to',
          'timezone' => '',
          'time_before_date' => FALSE,
          'always_display_year' => FALSE,
          'use_today' => FALSE,
          'cap_today' => FALSE,
          'display_day' => FALSE,
          'display_time' => FALSE,
          'hide_date' => FALSE,
          'display_noon_and_midnight' => FALSE,
          'capitalize_noon_and_midnight' => FALSE,
          'use_all_day' => FALSE,
          'month_only' => FALSE,
        ],
        'weight' => 1,
        'region' => 'content',
      ];
    }

    // Verify we're actually using the daterange_ap_style formatter.
    $this->assertEquals('daterange_ap_style', $component['type'], 'Should be using daterange_ap_style formatter');

    // Merge in the new settings.
    $component['settings'] = array_merge($component['settings'], $settings);
    $display->setComponent('field_event_dates', $component);
    $display->save();
  }

  /**
   * Tests that date range formatter renders with "to" separator.
   */
  public function testDateRangeFormatterWithToSeparator(): void {
    // Update the formatter to use "to" separator with UTC timezone.
    $this->updateDisplaySettings([
      'separator' => 'to',
      'always_display_year' => TRUE,
      'display_time' => FALSE,
      'timezone' => 'UTC',
    ]);

    // Create a node with a date range using dynamic dates.
    $start = new \DateTime('2024-01-15 12:00:00', new \DateTimeZone('UTC'));
    $end = new \DateTime('2024-01-20 12:00:00', new \DateTimeZone('UTC'));

    $node = Node::create([
      'type' => 'date_test',
      'title' => 'Test Event',
      'field_event_dates' => [
        'value' => $start->format('Y-m-d\TH:i:s'),
        'end_value' => $end->format('Y-m-d\TH:i:s'),
      ],
    ]);
    $node->save();

    // Visit the node page.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);

    // Check that the field label is visible.
    $this->assertSession()->pageTextContains('Event Dates');

    // Check that the date is formatted in AP style (abbreviated month).
    $this->assertSession()->pageTextContains('Jan.');

    // Verify "to" separator is used (not hyphen or en dash).
    $this->assertSession()->pageTextContains(' to ');

    // Check for the date pattern with "to" separator.
    // Should be "Jan. 15 to 20, 2024".
    $this->assertSession()->pageTextMatches('/Jan\.\s+15\s+to\s+20,\s+2024/');
  }

  /**
   * Tests that date range formatter renders with en dash separator.
   */
  public function testDateRangeFormatterWithEnDashSeparator(): void {
    // Update to use en dash separator with UTC timezone.
    $this->updateDisplaySettings([
      'separator' => 'endash',
      'always_display_year' => TRUE,
      'display_time' => FALSE,
      'timezone' => 'UTC',
    ]);

    // Create a node with a date range using dynamic dates.
    $start = new \DateTime('2024-03-10 12:00:00', new \DateTimeZone('UTC'));
    $end = new \DateTime('2024-03-15 12:00:00', new \DateTimeZone('UTC'));

    $node = Node::create([
      'type' => 'date_test',
      'title' => 'Test Event',
      'field_event_dates' => [
        'value' => $start->format('Y-m-d\TH:i:s'),
        'end_value' => $end->format('Y-m-d\TH:i:s'),
      ],
    ]);
    $node->save();

    // Visit the node page.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);

    // Check that the field label is visible.
    $this->assertSession()->pageTextContains('Event Dates');

    // Check that the date is formatted in AP style.
    // March is NOT abbreviated in AP style (full month name).
    $this->assertSession()->pageTextContains('March');

    // Verify en dash HTML entity is used in the response.
    $this->assertSession()->responseContains('&ndash;');
  }

  /**
   * Tests that date range formatter renders with hyphen separator.
   */
  public function testDateRangeFormatterWithHyphenSeparator(): void {
    // Update to use hyphen separator with UTC timezone.
    $this->updateDisplaySettings([
      'separator' => 'hyphen',
      'always_display_year' => TRUE,
      'display_time' => FALSE,
      'timezone' => 'UTC',
    ]);

    // Create a node with a date range using dynamic dates.
    $start = new \DateTime('2024-06-05 12:00:00', new \DateTimeZone('UTC'));
    $end = new \DateTime('2024-06-13 12:00:00', new \DateTimeZone('UTC'));

    $node = Node::create([
      'type' => 'date_test',
      'title' => 'Test Event',
      'field_event_dates' => [
        'value' => $start->format('Y-m-d\TH:i:s'),
        'end_value' => $end->format('Y-m-d\TH:i:s'),
      ],
    ]);
    $node->save();

    // Visit the node page.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);

    // Check that the field label is visible.
    $this->assertSession()->pageTextContains('Event Dates');

    // Check that the date is formatted in AP style.
    // June is NOT abbreviated in AP style (full month name).
    $this->assertSession()->pageTextContains('June');

    // Verify the date range is displayed with both dates.
    // Check for the pattern "June 5-13, 2024" (hyphen separator).
    $this->assertSession()->pageTextMatches('/June\s+5-13,\s+2024/');
  }

  /**
   * Tests that date range formatter works with smartdate field type.
   */
  public function testDateRangeFormatterWithSmartDateField(): void {
    // Create a node with smartdate field using dynamic dates.
    // Smartdate stores as timestamps (value and end_value).
    $start = new \DateTime('2024-08-10 12:00:00', new \DateTimeZone('UTC'));
    $end = new \DateTime('2024-08-15 12:00:00', new \DateTimeZone('UTC'));

    $node = Node::create([
      'type' => 'date_test',
      'title' => 'Smart Date Event',
      'field_smart_date' => [
        'value' => $start->getTimestamp(),
        'end_value' => $end->getTimestamp(),
      ],
    ]);
    $node->save();

    // Visit the node page.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);

    // Check that the field label is visible.
    $this->assertSession()->pageTextContains('Smart Date');

    // Verify the date range pattern "Aug. 10 to 15" with AP style formatting.
    // August is abbreviated as "Aug." and "to" separator is used.
    $this->assertSession()->pageTextMatches('/Aug\.\s+10\s+to\s+15/');
  }

}
