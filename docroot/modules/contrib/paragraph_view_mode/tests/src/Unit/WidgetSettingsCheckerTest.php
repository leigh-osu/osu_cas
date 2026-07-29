<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_view_mode\Unit;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\paragraph_view_mode\Checker\WidgetSettingsChecker;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the widget settings checker.
 *
 * @coversDefaultClass \Drupal\paragraph_view_mode\Checker\WidgetSettingsChecker
 * @group paragraph_view_mode
 */
class WidgetSettingsCheckerTest extends UnitTestCase {

  /**
   * Tests the hasFormModeBindEnabled method.
   *
   * @param array|null $settings
   *   The settings.
   * @param bool $expected
   *   The expected result.
   *
   * @covers ::hasFormModeBindEnabled
   *
   * @dataProvider formModeBindTestScenarios
   */
  public function testHasFormModeBindEnabled(null|array $settings, bool $expected) {
    $paragraph = $this->getParagraphMock();
    $displayRepository = $this->getEntityDisplayRepositoryMock($settings);

    $checker = new WidgetSettingsChecker($displayRepository);

    $this->assertEquals(
      $expected,
      $checker->hasFormModeBindEnabled('test', $paragraph)
    );
  }

  /**
   * Tests the hasApplyToPreviewEnabled method.
   *
   * @param array|null $settings
   *   The settings.
   * @param bool $expected
   *   The expected result.
   *
   * @covers ::hasApplyToPreviewEnabled
   *
   * @dataProvider applyToPreviewTestScenarios
   */
  public function testHasApplyToPreviewEnabled(null|array $settings, bool $expected) {
    $paragraph = $this->getParagraphMock();

    $displayRepository = $this->getEntityDisplayRepositoryMock($settings);

    $checker = new WidgetSettingsChecker($displayRepository);

    $this->assertEquals(
      $expected,
      $checker->hasApplyToPreviewEnabled($paragraph)
    );
  }

  /**
   * Provides the test scenarios for the hasFormModeBindEnabled method.
   *
   * @return array
   *   The test scenarios.
   */
  public static function formModeBindTestScenarios(): array {
    return [
      'form_mode_bind_enabled' => [
        'settings' => [
          'settings' => [
            'form_mode_bind' => TRUE,
          ],
        ],
        'expected' => TRUE,
      ],
      'form_mode_bind_disabled' => [
        'settings' => [
          'settings' => [
            'form_mode_bind' => FALSE,
          ],
        ],
        'expected' => FALSE,
      ],
      'form_mode_bind_not_set' => [
        'settings' => NULL,
        'expected' => FALSE,
      ],
    ];
  }

  /**
   * Provides the test scenarios for the hasApplyToPreviewEnabled method.
   *
   * @return array
   *   The test scenarios.
   */
  public static function applyToPreviewTestScenarios(): array {
    return [
      'apply_to_preview_enabled' => [
        'settings' => [
          'settings' => [
            'apply_to_preview' => TRUE,
          ],
        ],
        'expected' => TRUE,
      ],
      'apply_to_preview_disabled' => [
        'settings' => [
          'settings' => [
            'apply_to_preview' => FALSE,
          ],
        ],
        'expected' => FALSE,
      ],
      'apply_to_preview_not_set' => [
        'settings' => NULL,
        'expected' => FALSE,
      ],
    ];
  }

  /**
   * Gets the form display mock.
   *
   * @param array|null $settings
   *   The settings.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The form display mock.
   */
  private function getFormDisplayMock(null|array $settings): EntityFormDisplayInterface {
    $formDisplay = $this->createMock(EntityFormDisplayInterface::class);
    $formDisplay->method('getComponent')->willReturn($settings);

    return $formDisplay;
  }

  /**
   * Gets the paragraph mock.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The paragraph mock.
   */
  public function getParagraphMock(): ParagraphInterface {
    $paragraph = $this->createMock(ParagraphInterface::class);
    $paragraph
      ->method('getEntityTypeId')
      ->willReturn('paragraph');
    $paragraph
      ->method('bundle')
      ->willReturn('test');

    return $paragraph;
  }

  /**
   * Gets the entity display repository mock.
   *
   * @param array|null $settings
   *   The settings.
   *
   * @return \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   *   The entity display repository mock.
   */
  public function getEntityDisplayRepositoryMock(null|array $settings): EntityDisplayRepositoryInterface {
    $displayRepository = $this->createMock(
      EntityDisplayRepositoryInterface::class
    );
    $displayRepository->method('getFormDisplay')->willReturn(
      $this->getFormDisplayMock($settings)
    );
    return $displayRepository;
  }

}
