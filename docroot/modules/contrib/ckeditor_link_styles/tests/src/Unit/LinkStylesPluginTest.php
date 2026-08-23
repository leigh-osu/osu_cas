<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor_link_styles\Unit;

use Drupal\ckeditor_link_styles\Plugin\CKEditor5Plugin\LinkStyles;
use Drupal\editor\EditorInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ckeditor_link_styles\Plugin\CKEditor5Plugin\LinkStyles
 * @group ckeditor_link_styles
 */
class LinkStylesPluginTest extends UnitTestCase {

  /**
   * Provides a list of configs to test.
   */
  public static function providerGetDynamicPluginConfig(): array {
    return [
      'default configuration (empty)' => [
        [
          'styles' => [],
        ],
        [],
      ],
      'Simple' => [
        [
          'styles' => [
            ['label' => 'Button', 'element' => '<a class="btn">'],
          ],
        ],
        [
          'link' => [
            'decorators' => [
              [
                'mode' => 'manual',
                'label' => 'Button',
                'attributes' => [
                  'class' => 'btn',
                ],
              ],
            ],
          ],
        ],
      ],
      'Complex' => [
        [
          'styles' => [
            ['label' => 'Primary Button', 'element' => '<a class="btn btn-primary">'],
            ['label' => 'Secondary Button', 'element' => '<a class="btn btn-secondary">'],
          ],
        ],
        [
          'link' => [
            'decorators' => [
              [
                'mode' => 'manual',
                'label' => 'Primary Button',
                'attributes' => [
                  'class' => 'btn btn-primary',
                ],
              ],
              [
                'mode' => 'manual',
                'label' => 'Secondary Button',
                'attributes' => [
                  'class' => 'btn btn-secondary',
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * @covers ::getDynamicPluginConfig
   * @dataProvider providerGetDynamicPluginConfig
   */
  public function testGetDynamicPluginConfig(array $configuration, array $expected_dynamic_config): void {
    $plugin = new LinkStyles($configuration, 'ckeditor_link_styles_linkStyles', NULL);
    $dynamic_plugin_config = $plugin->getDynamicPluginConfig([], $this->prophesize(EditorInterface::class)->reveal());
    $this->assertSame($expected_dynamic_config, $dynamic_plugin_config);
  }

}
