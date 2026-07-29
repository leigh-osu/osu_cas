<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_view_mode\Unit;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\paragraph_view_mode\Checker\WidgetSettingsChecker;
use Drupal\paragraph_view_mode\Matcher\DisplayModeMatcher;
use Drupal\paragraph_view_mode\Plugin\Field\FieldType\ParagraphViewModeItem;
use Drupal\paragraph_view_mode\StorageManagerInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test the display mode matcher.
 *
 * @coversDefaultClass \Drupal\paragraph_view_mode\Matcher\DisplayModeMatcher
 * @group paragraph_view_mode
 */
class DisplayModeMatcherTest extends UnitTestCase {

  /**
   * Tests the matchFormForModeAndEntity method.
   *
   * @param array|null $settings
   *   The settings.
   * @param bool $expected
   *   The expected result.
   *
   * @covers ::matchFormForModeAndEntity
   *
   * @dataProvider formModeBindTestScenarios
   */
  public function testMatchFormForModeAndEntity(
    ?array $settings,
    bool $expected,
  ): void {
    $settings_checker = $this->createMock(WidgetSettingsChecker::class);
    $settings_checker
      ->method('hasFormModeBindEnabled')
      ->willReturn($expected);

    $entity = $this->createParagraphMock(ParagraphInterface::class, TRUE, 'test');

    $matcher = new DisplayModeMatcher($settings_checker);

    $this->assertEquals($expected, $matcher->matchFormForModeAndEntity('test', $entity));
  }

  /**
   * Tests the matchViewForModeAndEntity method.
   *
   * @param bool $is_supported_entity
   *   The is supported entity.
   * @param bool $has_field
   *   The has field.
   * @param bool $is_allowed_mode
   *   The is allowed mode.
   * @param bool $force_not_allowed_mode
   *   The force not allowed mode.
   * @param bool $expected
   *   The expected result.
   *
   * @covers ::matchViewForModeAndEntity
   *
   * @dataProvider viewModeBindTestScenarios
   */
  public function testMatchViewForModeAndEntity(
    bool $is_supported_entity,
    bool $has_field,
    bool $is_allowed_mode,
    bool $force_not_allowed_mode,
    bool $expected,
  ): void {
    $settings_checker = $this->createMock(WidgetSettingsChecker::class);
    $settings_checker
      ->method('hasApplyToPreviewEnabled')
      ->willReturn($force_not_allowed_mode);

    $type = $is_supported_entity ? ParagraphInterface::class : FieldableEntityInterface::class;

    $not_allowed_mode = 'preview';
    $allowed_mode = 'any';
    $view_mode = $is_allowed_mode ? $allowed_mode : $not_allowed_mode;

    $entity = $this->createParagraphMock($type, $has_field, $view_mode);

    $matcher = new DisplayModeMatcher($settings_checker);

    $this->assertEquals($expected, $matcher->matchViewForModeAndEntity($view_mode, $entity));
  }

  /**
   * Provides the test scenarios for the matchFormForModeAndEntity method.
   *
   * @return array
   *   The test scenarios.
   */
  public static function formModeBindTestScenarios(): array {
    return [
      'form mode bind enabled' => [
        [],
        TRUE,
      ],
      'form mode bind disabled' => [
        [],
        FALSE,
      ],
    ];
  }

  /**
   * Provides the test scenarios for the matchViewForModeAndEntity method.
   *
   * @return array
   *   The test scenarios.
   */
  public static function viewModeBindTestScenarios(): array {
    return [
      'only paragraph entity is supported, other entities are not allowed - positive' => [
        TRUE,
        TRUE,
        TRUE,
        FALSE,
        TRUE,
      ],
      'only paragraph entity is supported, other entities are not allowed - negative' => [
        FALSE,
        TRUE,
        TRUE,
        FALSE,
        FALSE,
      ],
      'paragraphs without view mode field should always return false' => [
        TRUE,
        FALSE,
        TRUE,
        TRUE,
        FALSE,
      ],
      'paragraph entity having view mode field and with allowed mode should always return true' => [
        TRUE,
        TRUE,
        TRUE,
        FALSE,
        TRUE,
      ],
      'not allowed mode should always return false' => [
        TRUE,
        TRUE,
        FALSE,
        FALSE,
        FALSE,
      ],
      'the mode forced to be allowed is always true for paragraphs having view mode fields' => [
        TRUE,
        TRUE,
        FALSE,
        TRUE,
        TRUE,
      ],
    ];
  }

  /**
   * Creates a paragraph mock.
   *
   * @param string $type
   *   The entity type to create.
   * @param bool $has_field
   *   The value indicating if the field exists.
   * @param string $get_value
   *   The value to return from the get method.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The paragraph mock.
   */
  protected function createParagraphMock(string $type, bool $has_field, string $get_value): MockObject {
    $entity = $this->createMock($type);
    $entity
      ->method('hasField')
      ->with(StorageManagerInterface::FIELD_NAME)
      ->willReturn($has_field);

    $view_mode_field = $this->createMock(ParagraphViewModeItem::class);
    $view_mode_field->value = $get_value;

    $entity
      ->method('get')
      ->with(StorageManagerInterface::FIELD_NAME)
      ->willReturn($view_mode_field);

    return $entity;
  }

}
