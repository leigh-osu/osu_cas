<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_field\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;
use Drupal\Tests\ui_patterns\Kernel\SourceTree\TranslationBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests content translation scenarios of the ui_patterns_source field.
 *
 * Covers the two supported modes end to end, including rendering:
 * - symmetric (synchronized_translation = TRUE, default): one shared
 *   structure, language-specific leaf values;
 * - asymmetric (synchronized_translation = FALSE): fully independent
 *   trees per language.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns_field')]
#[RunTestsInSeparateProcesses]
final class ContentTranslationScenariosTest extends TranslationBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ui_patterns_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', 'page');
    $display->setComponent('field_source', ['type' => 'ui_patterns_source']);
    $display->save();
  }

  /**
   * Symmetric mode: leaf edits on a translation stay language-specific.
   */
  public function testSymmetricLeafTranslationIsRenderedPerLanguage(): void {
    $this->node->set('field_source', [$this->componentItem('Hello', 'Body EN')]);
    $this->node->save();

    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $values = $german_node->get('field_source')->getValue();
    $values[0]['source']['component']['props']['string']['source']['value'] = 'Hallo';
    $values[0]['source']['component']['slots']['slot']['sources'][0]['source']['value']['value'] = 'Body DE';
    $german_node->set('field_source', $values);
    $german_node->save();

    $reloaded = $this->reloadNode();
    $html_en = $this->renderNode($reloaded->getUntranslated());
    $html_de = $this->renderNode($reloaded->getTranslation('de'));

    self::assertStringContainsString('Hello', $html_en);
    self::assertStringContainsString('Body EN', $html_en);
    self::assertStringNotContainsString('Hallo', $html_en);
    self::assertStringNotContainsString('Body DE', $html_en);

    self::assertStringContainsString('Hallo', $html_de);
    self::assertStringContainsString('Body DE', $html_de);
    self::assertStringNotContainsString('Hello', $html_de);
    self::assertStringNotContainsString('Body EN', $html_de);
  }

  /**
   * Symmetric mode: a structural edit on a translation is shared.
   *
   * A slot item added on the DE translation must show up on the default
   * language as well, while the default keeps its own leaf values.
   */
  public function testSymmetricStructureEditOnTranslationIsShared(): void {
    $this->node->set('field_source', [$this->componentItem('Hello', 'Body EN')]);
    $this->node->save();

    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $values = $german_node->get('field_source')->getValue();
    $values[0]['source']['component']['slots']['slot']['sources'][] = [
      'source_id' => 'wysiwyg',
      'source' => [
        'value' => ['value' => 'Neu', 'format' => 'plain_text'],
      ],
    ];
    $german_node->set('field_source', $values);
    $german_node->save();

    $reloaded = $this->reloadNode();
    $en_values = $reloaded->getUntranslated()->get('field_source')->getValue();
    self::assertCount(
      2,
      $en_values[0]['source']['component']['slots']['slot']['sources'],
      'The default language adopts the slot item added on the translation.',
    );

    $html_en = $this->renderNode($reloaded->getUntranslated());
    self::assertStringContainsString('Hello', $html_en);
    self::assertStringContainsString('Body EN', $html_en);
    self::assertStringContainsString('Neu', $html_en);
  }

  /**
   * Asymmetric mode: each language renders its own independent tree.
   */
  public function testAsymmetricTranslationsRenderIndependently(): void {
    $field = FieldConfig::loadByName('node', 'page', 'field_source');
    self::assertInstanceOf(FieldConfig::class, $field);
    $field->setSetting('synchronized_translation', FALSE);
    $field->save();

    $this->node->set('field_source', [$this->componentItem('Hello', 'Body EN')]);
    $this->node->save();

    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    // Structurally different tree: two deltas, own leaves.
    $german_node->set('field_source', [
      $this->componentItem('Unabhaengig', 'Erster Aufbau'),
      $this->componentItem('Zweite', 'Zweiter Aufbau'),
    ]);
    $german_node->save();

    $reloaded = $this->reloadNode();
    $en_raw = $reloaded->getUntranslated()->get('field_source')->getValue();

    $html_en = $this->renderNode($reloaded->getUntranslated());
    $html_de = $this->renderNode($reloaded->getTranslation('de'));

    self::assertStringContainsString('Hello', $html_en);
    self::assertStringContainsString('Body EN', $html_en);
    self::assertStringNotContainsString('Unabhaengig', $html_en);

    self::assertStringContainsString('Unabhaengig', $html_de);
    self::assertStringContainsString('Zweite', $html_de);
    self::assertStringNotContainsString('Hello', $html_de);

    // Saving the DE translation again must not touch the EN row.
    $german_reloaded = $reloaded->getTranslation('de');
    $de_values = $german_reloaded->get('field_source')->getValue();
    $de_values[0]['source']['component']['props']['string']['source']['value'] = 'Geaendert';
    $german_reloaded->set('field_source', $de_values);
    $german_reloaded->save();

    $reloaded = $this->reloadNode();
    self::assertSame(
      $en_raw,
      $reloaded->getUntranslated()->get('field_source')->getValue(),
      'The default language values are untouched by asymmetric edits.',
    );
  }

  /**
   * Builds a field item rendering the test component.
   *
   * @param string $prop_text
   *   Value of the translatable "string" prop.
   * @param string $slot_text
   *   Text of the single wysiwyg source in the "slot" slot.
   *
   * @return array
   *   The field item value.
   */
  private function componentItem(string $prop_text, string $slot_text): array {
    return [
      'source_id' => 'component',
      'source' => [
        'component' => [
          'component_id' => 'ui_patterns_test:test-component',
          'variant_id' => NULL,
          'props' => [
            'string' => [
              'source_id' => 'textfield',
              'source' => ['value' => $prop_text],
            ],
          ],
          'slots' => [
            'slot' => [
              'sources' => [
                [
                  'source_id' => 'wysiwyg',
                  'source' => [
                    'value' => ['value' => $slot_text, 'format' => 'plain_text'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Reloads the test node bypassing the static cache.
   *
   * @return \Drupal\node\NodeInterface
   *   The reloaded node.
   */
  private function reloadNode(): NodeInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    self::assertInstanceOf(NodeInterface::class, $reloaded);
    return $reloaded;
  }

  /**
   * Renders the source field of a node translation to HTML.
   *
   * The field is rendered directly: through the node view builder, both
   * translations would hit the same entity render-cache entry.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node translation to render.
   *
   * @return string
   *   The rendered HTML.
   */
  private function renderNode(NodeInterface $node): string {
    $build = $node->get('field_source')->view('default');
    return (string) \Drupal::service('renderer')->renderInIsolation($build);
  }

}
