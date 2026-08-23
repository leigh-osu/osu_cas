<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\ui_patterns\Plugin\UiPatterns\Source\TextfieldWidget;
use Drupal\ui_patterns\SourcePluginBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test WidgetSettings.
 *
 * @internal
 */
#[CoversClass(TextfieldWidget::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class WidgetSettingTest extends SourcePluginsTestBase {

  /**
   * Test merge default settings.
   */
  public function testWidgetDefaultSetting(): void {
    $configuration = SourcePluginBase::buildConfiguration('prop_id', [], [], []);
    /** @var \Drupal\ui_patterns\Plugin\UiPatterns\Source\TextfieldWidget $source */
    $source = $this->sourcePluginManager()->createInstance('textfield', $configuration);
    self::assertNotNull($source);
    self::assertFalse($source->getWidgetSetting('required'));
    self::assertEquals('', $source->getWidgetSetting('title'));
  }

  /**
   * Test widget overwrite from configuration.
   */
  public function testWidgetOverwriteSetting(): void {
    $configuration = SourcePluginBase::buildConfiguration('prop_id', [], [
      'widget_settings' => ['required' => TRUE, 'title' => 'test'],
    ], []);
    /** @var \Drupal\ui_patterns\Plugin\UiPatterns\Source\TextfieldWidget $source */
    $source = $this->sourcePluginManager()->createInstance('textfield', $configuration);
    self::assertNotNull($source);
    self::assertTrue($source->getWidgetSetting('required'));
    self::assertEquals('test', $source->getWidgetSetting('title'));
  }

}
