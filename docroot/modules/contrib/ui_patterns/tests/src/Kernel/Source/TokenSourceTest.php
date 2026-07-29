<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\TokenSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test TokenSource.
 *
 * @internal
 */
#[CoversClass(TokenSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class TokenSourceTest extends SourcePluginsTestBase {

  /**
   * Test TokenSource Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('token_');
  }

  /**
   * A <script> typed into a token template for a slot is escaped.
   *
   * The slot branch escapes the admin template before token substitution,
   * so admin-typed tags don't survive (token placeholders, having no
   * special characters, do).
   */
  public function testXssInTokenTemplateForSlotIsStripped(): void {
    $this->runSourcePluginTest([
      'skip_schema_check' => TRUE,
      'component' => [
        'component_id' => 'ui_patterns_test:test-component',
        'slots' => [
          'slot' => [
            'sources' => [
              [
                'source_id' => 'token',
                'source' => [
                  'value' => 'Hello<script>alert("xss")</script>World',
                ],
              ],
            ],
          ],
        ],
      ],
      'output' => [
        'slots' => [
          'slot' => [
            [
              'rendered_value' => '<script',
              'assert' => 'assertStringNotContainsString',
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * An <iframe> typed into a token template for a slot is stripped.
   */
  public function testIframeInTokenTemplateForSlotIsStripped(): void {
    $this->runSourcePluginTest([
      'skip_schema_check' => TRUE,
      'component' => [
        'component_id' => 'ui_patterns_test:test-component',
        'slots' => [
          'slot' => [
            'sources' => [
              [
                'source_id' => 'token',
                'source' => [
                  'value' => '<iframe src="https://evil.example.org"></iframe>',
                ],
              ],
            ],
          ],
        ],
      ],
      'output' => [
        'slots' => [
          'slot' => [
            [
              'rendered_value' => '<iframe',
              'assert' => 'assertStringNotContainsString',
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * A <script> returned by a token into a slot is stripped by core.
   *
   * Defense in depth: the slot value is emitted as plain-string #markup,
   * so Drupal core runs Xss::filterAdmin over it — <script>, <iframe>,
   * <style> and event handlers go. Residual risk: <a href> / <img> from
   * a token still render (admin allow-list).
   */
  public function testXssInTokenValueForSlotIsStrippedByCore(): void {
    $this->runSourcePluginTest([
      'skip_schema_check' => TRUE,
      'component' => [
        'component_id' => 'ui_patterns_test:test-component',
        'slots' => [
          'slot' => [
            'sources' => [
              [
                'source_id' => 'token',
                'source' => [
                  // Template has no admin-typed HTML; the payload comes
                  // from the token value (the node title below).
                  'value' => 'before [node:title] after',
                ],
              ],
            ],
          ],
        ],
      ],
      'entity' => [
        'title' => [
          // A node title (a plain-text field) carrying an injected <script>.
          'value' => '<script>alert("token-xss")</script>',
        ],
      ],
      'output' => [
        'slots' => [
          'slot' => [
            [
              'rendered_value' => '<script',
              'assert' => 'assertStringNotContainsString',
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * A <script> in a token value targeting a string prop is escaped.
   *
   * The source returns the raw substituted string, untrusted: the rendered
   * component shows it as literal text, never as a live element.
   */
  public function testXssInTokenValueForStringPropIsEscaped(): void {
    $captured = NULL;
    $this->runSourcePluginTest([
      'skip_schema_check' => TRUE,
      'component' => [
        'component_id' => 'ui_patterns_test:test-component',
        'props' => [
          'string' => [
            'source_id' => 'token',
            'source' => [
              'value' => '<script>alert("xss")</script>',
            ],
          ],
        ],
      ],
      'output' => [
        'props' => [
          'string' => [
            'closure' => function ($output) use (&$captured) {
              $this->assertSame('<script>alert("xss")</script>', $output);
              $captured = $output;
            },
          ],
        ],
      ],
    ]);
    $build = [
      '#type' => 'component',
      '#component' => 'ui_patterns_test:test-component',
      '#props' => ['string' => $captured],
    ];
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringNotContainsString('<script>alert', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $html);
  }

}
