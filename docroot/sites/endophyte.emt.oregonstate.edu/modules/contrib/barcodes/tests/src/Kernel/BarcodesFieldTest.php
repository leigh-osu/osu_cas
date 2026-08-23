<?php

declare(strict_types=1);

namespace Drupal\Tests\barcodes\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests Barcode formatter for all supported field types.
 *
 * @group barcodes
 */
class BarcodesFieldTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'barcodes',
    'experimental_fields',
    'node',
    'bigint',
    'datetime',
    'datetime_range',
    'field',
    'link',
    'telephone',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'field']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    // Create the article content type.
    $node_type = NodeType::create([
      'type' => 'article',
    ]);
    $node_type->save();
  }

  /**
   * Provides test data for the core field types supported by Barcodes.
   *
   * The Barcodes FieldFormatter plugin declares support for the following
   * field types:
   *   bigint, email, integer, link, string, telephone, text, text_long,
   *   text_with_summary, uuid.
   *
   * @return array<string, array<int, string>>
   *   An array of test case data, where each array element contains an array
   *   of four items corresponding to the four input parameters needed to
   *   create the field:
   *   - type: The plugin ID of the field type.
   *   - name: An arbitrary name for the field.
   *   - widget: The plugin ID of the widget to use.
   *   - formatter: The plugin ID of the formatter to use.
   *   - value: A sample value of the correct datatype.
   *   - expected: The barcode markup expected for the above sample value.
   */
  public static function coreFieldProvider(): array {
    $expected_output = '<div class="barcode barcode-aztec">' . "\n" . '  <div class="code"><img alt="Embedded Image" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkAQMAAABKLAcXAAAABlBMVEX///8AAABVwtN+AAAAAXRSTlMAQObYZgAAAAlwSFlzAAAOxAAADsQBlSsOGwAAAI9JREFUOI21lLERACEIBJkxMLQkS9PSLInQwBn+7vm3AjBQl0Dm4FBEmk2pKsNMJYZstzUQ65ZGZsuTHhwZJNX+taOoQAOfFgpJIWoY73aqphAUsQ9oxqcvgA4vFDJY+TTyfHBPGt0qxVBxR6K3he5JIEzVNaimkI8sQpP5Yojvuo38L4gneKZPCpjelXB6AOuayBhk072LAAAAAElFTkSuQmCC" /></div>' . "\n" . '  </div>' . "\n";
    return [
      "Field 'bigint'" => [
        'bigint', 'bigint_field', 'bigint', 'bigint_item_default',
        17317871, $expected_output,
      ],
      "Field 'email'" => [
        'email', 'email_field', 'email_default', 'basic_string',
        '17317871', $expected_output,
      ],
      "Field 'integer'" => [
        'integer', 'integer_field', 'number', 'number_integer',
        17317871, $expected_output,
      ],
      "Field 'link'" => [
        'link', 'link_field', 'link_default', 'link',
        'https://www.drupal.org/node/3821',
        '<div class="barcode barcode-aztec">' . "\n" . '  <div class="code"><img alt="Embedded Image" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkAQMAAABKLAcXAAAABlBMVEX///8AAABVwtN+AAAAAXRSTlMAQObYZgAAAAlwSFlzAAAOxAAADsQBlSsOGwAAAPpJREFUOI2l1LGNhDAQBdBvETicDu4aOa3bcoBkJALaArkR08GEBIh/490twAZnj+BLM/4GBMJWQM6kol16AC+ILMwXOoTDn47kNOLnvvSIAwuQyF07JPCZO9XP2b61S3ng9UuN45/TB1p4OcryzWyVhQTWM6+W0i6xqVdbXZrC/kSem115TLzQIfWk9UXTGWpfmgW/5G1XTTzrvd8UEWtbNQ551w6JLa/OkObVUtplJ3x2hgdibV09Uyg9UqT1nTUOtfPNsr4ATuVImU8UEYqozeB6ZC0/XRFOQ03pkD+GC5Yyv/8vNyX1rRSRaaytaxdhQ1+C+NngPf0DFvi+RKS4STIAAAAASUVORK5CYII=" /></div>' . "\n" . '  </div>' . "\n",
      ],
      "Field 'link' with internal: URI scheme" => [
        'link', 'link_field', 'link_default', 'link',
        'entity:node/3821',
        '<div class="barcode barcode-aztec">' . "\n" . '  <div class="code"><img alt="Embedded Image" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkAQMAAABKLAcXAAAABlBMVEX///8AAABVwtN+AAAAAXRSTlMAQObYZgAAAAlwSFlzAAAOxAAADsQBlSsOGwAAALlJREFUOI2t1EEOhCAMBdBPZsGSG+HFiDLhYnCjLlkYOq1oMutKExPfpqH9KHj4AccZV1nVXYdohBMHmQX4iggnbwlmdejJaA+FCy2Sb3LGxl866GNXd3wXXTszCjKrVDhDtkuajaBP0a5r5LnHULFLmpqJVdXLzmTzWdO0qkfcPbWrVdj+51sjbn3mkK8crKrSdd6JZJfccYQxvz27uEm3+HiRZpqOT6QXGthIt1/oheQPpVt75l2hH2Q/eQ82T6ZAAAAAAElFTkSuQmCC" /></div>' . "\n" . '  </div>' . "\n",
      ],
      "Field 'string'" => [
        'string', 'string_field', 'string_textfield', 'string',
        '17317871', $expected_output,
      ],
      "Field 'telephone'" => [
        'telephone', 'telephone_field', 'telephone_default', 'basic_string',
        '17317871', $expected_output,
      ],
      "Field 'text'" => [
        'text', 'text_field', 'text_textfield', 'text_default',
        '17317871', $expected_output,
      ],
      "Field 'text_long')" => [
        'text_long', 'text_long_field', 'text_textarea', 'text_default',
        '17317871', $expected_output,
      ],
      "Field 'text_with_summary'" => [
        'text_with_summary', 'text_with_summary_field', 'text_textarea_with_summary', 'text_default',
        '17317871', $expected_output,
      ],
      "Field 'uuid'" => [
        'uuid', 'uuid_field', 'no_ui', 'string',
        '17317871', $expected_output,
      ],
    ];
  }

  /**
   * Provides test data for additional fields types not supported by Barcodes.
   *
   * This method provides test data for "experimental" field types. That is,
   * types that aren't currently supported by the Barcodes FieldFormatter. These
   * types are:
   *   created, decimal, string_long, timestamp, uri.
   *
   * Note that the Barcodes FieldFormatter has to be modified via a hook in
   * order to operate on these experimental types. That hook is implemented
   * in the "experimental_fields" test module. Additional fields may be
   * added to this test if they are also added in that test module.
   *
   * @return array<string, array<int, string>>
   *   An array of test case data, where each array element contains an array
   *   of four items corresponding to the four input parameters needed to
   *   create the field:
   *   - type: The plugin ID of the field type.
   *   - name: An arbitrary name for the field.
   *   - widget: The plugin ID of the widget to use.
   *   - formatter: The plugin ID of the formatter to use.
   *   - value: A sample value of the correct datatype.
   *   - expected: The barcode markup expected for the above sample value.
   */
  public static function experimentalFieldProvider(): array {
    $expected_output = '<div class="barcode barcode-aztec">' . "\n" . '  <div class="code"><img alt="Embedded Image" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkAQMAAABKLAcXAAAABlBMVEX///8AAABVwtN+AAAAAXRSTlMAQObYZgAAAAlwSFlzAAAOxAAADsQBlSsOGwAAAI9JREFUOI21lLERACEIBJkxMLQkS9PSLInQwBn+7vm3AjBQl0Dm4FBEmk2pKsNMJYZstzUQ65ZGZsuTHhwZJNX+taOoQAOfFgpJIWoY73aqphAUsQ9oxqcvgA4vFDJY+TTyfHBPGt0qxVBxR6K3he5JIEzVNaimkI8sQpP5Yojvuo38L4gneKZPCpjelXB6AOuayBhk072LAAAAAElFTkSuQmCC" /></div>' . "\n" . '  </div>' . "\n";
    return [
      "Field 'created' (experimental)" => [
        'created', 'created_field', 'datetime_timestamp', 'timestamp',
        17317871, $expected_output,
      ],
      "Field 'decimal' (experimental)" => [
        'decimal', 'decimal_field', 'number', 'number_decimal',
        17317871, $expected_output,
      ],
      "Field 'string_long' (experimental)" => [
        'string_long', 'string_long_field', 'string_textarea', 'basic_string',
        '17317871', $expected_output,
      ],
      "Field 'timestamp' (experimental)" => [
        'timestamp', 'timestamp_field', 'datetime_timestamp', 'timestamp',
        '17317871', $expected_output,
      ],
      "Field 'uri' (experimental)" => [
        'uri', 'uri_field', 'uri', 'uri_link',
        '17317871', $expected_output,
      ],
    ];
  }

  /**
   * Builds a content type with a field formatted as a barcode.
   *
   * @param string $field_type
   *   The plugin ID of the field type.
   * @param string $field_name
   *   An arbitrary name for the field.
   * @param string $field_widget
   *   The plugin ID of the widget to use.
   * @param string $field_formatter
   *   The plugin ID of the formatter to use.
   * @param mixed $value
   *   A sample value of the correct datatype.
   * @param string $expected
   *   The barcode markup expected for the above sample value.
   *
   * @dataProvider coreFieldProvider
   * @dataProvider experimentalFieldProvider
   */
  public function testFieldTypes(string $field_type, string $field_name, string $field_widget, string $field_formatter, mixed $value, string $expected): void {

    $entity_type = 'node';
    $entity_bundle = 'article';

    $field_definition = FieldStorageConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      'type' => $field_type,
      'cardinality' => 1,
      'settings' => [],
    ]);
    $field_definition->save();
    $field_instance = FieldConfig::create([
      'entity_type' => $entity_type,
      'bundle' => $entity_bundle,
      'field_name' => $field_name,
      'label' => 'String field',
      'settings' => [],
      'default_value' => [],
    ]);
    $field_instance->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    if ($field_widget === 'no_ui') {
      // This field type had no widget.
    }
    else {
      $display_repository->getFormDisplay($entity_type, $entity_bundle)
        // Component is the FieldWidget.
        ->setComponent($field_name, [
          'type' => $field_widget,
          'settings' => [
            'placeholder' => $value,
          ],
        ])
        ->save();
    }
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository->getViewDisplay($entity_type, $entity_bundle)
      // Component is the FieldFormatter.
      ->setComponent($field_name, [
        // This is where we have to use the barcodes formatter and specify all
        // the settings for that formatter.
        'type' => 'barcode',
        'settings' => [
          'type' => 'AZTEC',
          'format' => 'png',
          'width' => 100,
          'height' => 100,
        ],
        'weight' => 1,
      ])
      ->save();

    // Now create a Node and set the field value.
    $node = Node::create([
      'title' => "Node for $field_type field",
      'type' => $entity_bundle,
    ]);
    $node->set($field_name, $value)->save();

    // Finally, we can render the node and verify that the barcode displays
    // the field value properly. The purpose of this test case is not to check
    // any constraints on the content, but just to ensure that the content
    // renders properly. Because of that, we use a simple numeric string that
    // works for all supported fields and all supported barcode types.
    $build = $node->get($field_name)->view([
      'type' => 'barcode',
      'settings' => [
        'type' => 'AZTEC',
        'format' => 'png',
      ],
    ]);
    \Drupal::service('renderer')->renderRoot($build[0]);
    $this->assertEquals($expected, (string) $build[0]['#markup']);

  }

}
