<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\SourceTree;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\ui_patterns\Traits\TestContentCreationTrait;
use Drupal\ui_patterns_field\Field\SourceValueList;

/**
 * Base class providing a node with a ui_patterns_source field and languages.
 */
abstract class TranslationBase extends KernelTestBase {

  use TestContentCreationTrait;

  /**
   * The test source tree data.
   *
   * @var array|array[]
   */
  protected array $testSourceTreeData = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'language',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'ui_patterns',
    'ui_patterns_field',
  ];

  /**
   * The test node.
   */
  protected NodeInterface $node;

  /**
   * Returns the typed source field list of an entity translation.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity translation holding the field.
   *
   * @return \Drupal\ui_patterns_field\Field\SourceValueList
   *   The source field list.
   */
  protected static function sourceList(FieldableEntityInterface $entity): SourceValueList {
    $list = $entity->get('field_source');
    self::assertInstanceOf(SourceValueList::class, $list);
    return $list;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('node', 'node_access');
    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig([
      'system',
      'filter',
      'ui_patterns',
    ]);
    $type = NodeType::create([
      'name' => 'page',
      'type' => 'page',
    ]);
    $type->save();
    $this->createEntityField('node', 'page', 'field_source', 'ui_patterns_source');
    $this->node = Node::create([
      'type' => 'page',
      'title' => 'Test',
    ]);
    $this->node->setTitle('english');
    $this->node->save();

    $this->testSourceTreeData = [
      0 => [
        'source_id' => 'component',
        'node_id' => 'node-1',
        'source' => [
          'component' => [
            'component_id' => 'olivero:teaser',
            'variant_id' => NULL,
            'props' => [
              'attributes' => [
                'source_id' => 'attributes',
                'node_id' => 'node-10',
                'source' => [
                  'value' => 'Default Value',
                ],
              ],
            ],
            'slots' => [
              'content' => [
                'sources' => [
                  0 => [
                    'source_id' => 'wysiwyg',
                    'node_id' => 'node-2',
                    'source' => [
                      'value' => [
                        'value' => 'english',
                        'format' => 'plain_text',
                      ],
                    ],
                    '_weight' => '0',
                    '_remove' => [
                      'dropdown_actions' => [],
                    ],
                  ],
                ],
                'add_more_button' => '',
              ],
              'image' => ['add_more_button' => ''],
              'meta' => ['add_more_button' => ''],
              'prefix' => ['add_more_button' => ''],
              'title' => ['add_more_button' => ''],
            ],
          ],
        ],
      ],
      1 => [
        'source_id' => 'component',
        'node_id' => 'node-3',
        'source' => [
          'component' => [
            'component_id' => 'olivero:teaser',
            'variant_id' => NULL,
            'props' => [
              'attributes' => [
                'source_id' => 'attributes',
                'node_id' => 'node-5',
                'source' => [
                  'value' => "class='XX'",
                ],
              ],
            ],
            'slots' => [
              'content' => [
                'sources' => [
                  0 => [
                    'source_id' => 'component',
                    'node_id' => 'node-4',
                    'source' => [
                      'component' => [
                        'component_id' => 'olivero:teaser',
                        'slots' => [
                          'content' => [
                            'sources' => [
                              0 => [
                                'source_id' => 'component',
                                'node_id' => 'node-6',
                                'source' => [
                                  'component' => [
                                    'component_id' => 'olivero:teaser',
                                    'slots' => [
                                      'content' => [
                                        'sources' => [
                                          0 => [
                                            'source_id' => 'wysiwyg',
                                            'node_id' => 'node-7',
                                            'source' => [
                                              'value' => [
                                                'value' => 'deep',
                                                'format' => 'plain_text',
                                              ],
                                            ],
                                            '_weight' => '0',
                                            '_remove' => [
                                              'dropdown_actions' => [],
                                            ],
                                          ],
                                        ],
                                        'add_more_button' => '',
                                      ],
                                      'image' => ['add_more_button' => ''],
                                      'meta' => ['add_more_button' => ''],
                                      'prefix' => ['add_more_button' => ''],
                                      'title' => ['add_more_button' => ''],
                                    ],
                                    'props' => [
                                      'attributes' => [
                                        'source_id' => 'attributes',
                                        'source' => [
                                          'value' => '',
                                        ],
                                      ],
                                    ],
                                  ],
                                ],
                                '_weight' => '0',
                                '_remove' => [
                                  'dropdown_actions' => [],
                                ],
                              ],
                            ],
                            'add_more_button' => '',
                          ],
                          'image' => ['add_more_button' => ''],
                          'meta' => ['add_more_button' => ''],
                          'prefix' => ['add_more_button' => ''],
                          'title' => ['add_more_button' => ''],
                        ],
                        'props' => [
                          'attributes' => [
                            'source_id' => 'attributes',
                            'source' => [
                              'value' => "class='x'",
                            ],
                          ],
                        ],
                      ],
                    ],
                    '_weight' => '0',
                    '_remove' => [
                      'dropdown_actions' => [],
                    ],
                  ],
                ],
                'add_more_button' => '',
              ],
              'image' => ['add_more_button' => ''],
              'meta' => ['add_more_button' => ''],
              'prefix' => ['add_more_button' => ''],
              'title' => ['add_more_button' => ''],
            ],
          ],
        ],
      ],
    ];
  }

}
