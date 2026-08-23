<?php

declare(strict_types=1);

namespace Drupal\Tests\entityreference_filter\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Creates nodes and terms referenced via entityreference fields.
 *
 * Relies on node/taxonomy content creation helpers and the NODE_TYPE_ARTICLE
 * constant, so it is only meant for Functional and FunctionalJavascript test
 * base classes that extend BrowserTestBase or WebDriverTestBase.
 */
trait ContentSetupTrait {

  /**
   * Prepares content for testing in views.
   *
   * Nodes and Terms referenced via entityreference fields.
   */
  protected function contentPrepare() {
    // Vocabulary 1.
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = Vocabulary::create([
      'name' => 'test1',
      'vid'  => 'test1',
    ]);
    $vocabulary->save();

    // Id is 1.
    $term1 = Term::create([
      'name' => '1',
      'vid'  => $vocabulary->id(),
    ]);
    $term1->save();

    $term2 = Term::create([
      'name' => '2',
      'vid'  => $vocabulary->id(),
    ]);
    $term2->save();

    // Vocabulary 2.
    $vocabulary2 = Vocabulary::create([
      'name' => 'test2',
      'vid'  => 'test2',
    ]);
    $vocabulary2->save();

    $term3 = Term::create([
      'name' => '3',
      'vid'  => $vocabulary2->id(),
    ]);
    $term3->save();

    $term4 = Term::create([
      'name' => '4',
      'vid'  => $vocabulary2->id(),
    ]);
    $term4->save();

    $this->drupalCreateContentType([
      'type' => self::NODE_TYPE_ARTICLE,
      'name' => 'Article',
    ]);

    // Create an entity reference field.
    $field_name = 'field_taxonomy_reference';
    $field_storage = FieldStorageConfig::create([
      'field_name'   => $field_name,
      'entity_type'  => 'node',
      'translatable' => FALSE,
      'settings'     => [
        'target_type' => 'taxonomy_term',
      ],
      'type'         => 'entity_reference',
      'cardinality'  => 1,
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type'   => 'node',
      'bundle'        => self::NODE_TYPE_ARTICLE,
      'settings'      => [
        'handler'          => 'default',
        'handler_settings' => [
          // Restrict selection of terms to a single vocabulary.
          'target_bundles' => [
            $vocabulary->id()  => $vocabulary->id(),
            $vocabulary2->id() => $vocabulary2->id(),
          ],
        ],
      ],
    ]);
    $field->save();

    // Create 10 nodes.
    $node_values = [
      'type' => self::NODE_TYPE_ARTICLE,
    ];
    for ($i = 0; $i < 10; $i++) {
      $node_values['taxonomy_reference'] = [];
      $node_values['taxonomy_reference'][] = ['target_id' => $term1->id()];
      $this->drupalCreateNode($node_values);
    }
  }

}
