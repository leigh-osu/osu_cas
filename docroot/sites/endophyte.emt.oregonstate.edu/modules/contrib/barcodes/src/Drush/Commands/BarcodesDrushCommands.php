<?php

declare(strict_types=1);

namespace Drupal\barcodes\Drush\Commands;

use Com\Tecnick\Barcode\Barcode as BarcodeGenerator;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush 12+ commands for the Barcodes module.
 */
final class BarcodesDrushCommands extends DrushCommands {

  /**
   * Generates a barcode with the given options.
   *
   * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
   *
   * @param string $value
   *   The string that should be formatted as a barcode.
   * @param array $options
   *   An array of optional parameters used to generate the barcode. This array
   *   may contain the following keys:
   *   - type: The barcode type.
   *   - color: The barcode color.
   *   - height: The barcode height in pixels.
   *   - width: The barcode width in pixels.
   *   - padding_top: The barcode top padding in pixels.
   *   - padding_right: The barcode right padding in pixels.
   *   - padding_bottom: The barcode bottom padding in pixels.
   *   - padding_left: The barcode left padding in pixels.
   *   - format: The barcode format. One of: 'svg', 'png', 'htmldiv',
   *     'unicode', 'binary'.
   *   - binary: Sends a binary image file to STDOUT.
   *     Works with --format=png only.
   *
   * @return string|null
   *   The barcode markup or NULL if binary output was generated.
   *
   * @throws \Com\Tecnick\Barcode\Exception
   */
  #[CLI\Command(name: 'barcodes:generate', aliases: ['generate-barcode'])]
  #[CLI\Help(description: 'Generates a barcode with the given options.')]
  #[CLI\Argument(name: 'value', description: 'The string that should be formatted as a barcode.')]
  #[CLI\Option(name: 'type', description: 'The barcode type.')]
  #[CLI\Option(name: 'color', description: 'The barcode color.')]
  #[CLI\Option(name: 'height', description: 'The barcode height in pixels.')]
  #[CLI\Option(name: 'width', description: 'The barcode width in pixels.')]
  #[CLI\Option(name: 'padding_top', description: 'The barcode top padding in pixels.')]
  #[CLI\Option(name: 'padding_right', description: 'The barcode right padding in pixels.')]
  #[CLI\Option(name: 'padding_bottom', description: 'The barcode bottom padding in pixels.')]
  #[CLI\Option(name: 'padding_left', description: 'The barcode left padding in pixels.')]
  #[CLI\Option(name: 'format', description: "The barcode format. One of: 'svg', 'png', 'htmldiv', 'unicode', 'binary'.")]
  #[CLI\Option(name: 'binary', description: 'Sends a binary image file to STDOUT (works with --format=png only).')]
  #[CLI\Usage(name: 'drush barcodes:generate "Hello World!"', description: 'Returns markup.')]
  #[CLI\Usage(name: 'drush barcodes:generate 0xABADCAFE --type=qrcode --height=250 --width=250 --format=png', description: 'Returns markup.')]
  #[CLI\Usage(name: 'drush barcodes:generate "Hello World!" --format=png --binary > my-barcode.png ', description: 'Returns binary data for piping.')]
  public function generate(string $value, array $options = ['type' => 'QRCODE', 'color' => '#000000', 'height' => 100, 'width' => 100, 'padding_top' => 0, 'padding_right' => 0, 'padding_bottom' => 0, 'padding_left' => 0, 'format' => 'png', 'binary' => FALSE]): ?string {

    $generator = new BarcodeGenerator();
    $barcode = $generator->getBarcodeObj(
      // Force correct casing on user input.
      strtoupper($options['type']),
      $value,
      (int) $options['width'],
      (int) $options['height'],
      $options['color'],
      [
        (int) $options['padding_top'],
        (int) $options['padding_right'],
        (int) $options['padding_bottom'],
        (int) $options['padding_left'],
      ]
    );

    // Force correct casing on user input.
    switch (strtolower($options['format'])) {
      case 'svg':
        return $barcode->getSvgCode();

      case 'png':
        if ($options['binary']) {
          fwrite(STDOUT, $barcode->getPngData());
          return NULL;
        }
        else {
          return "<img alt=\"Embedded Image\" src=\"data:image/png;base64," . base64_encode($barcode->getPngData()) . "\" />";
        }

      case 'htmldiv':
        return $barcode->getHtmlDiv();

      case 'unicode':
        return "<pre style=\"font-family:monospace;line-height:0.61em;font-size:6px;\">" . $barcode->getGrid(json_decode('"\u00A0"'), json_decode('"\u2584"')) . "</pre>";

      case 'binary':
        return "<pre style=\"font-family:monospace;\">" . $barcode->getGrid() . "</pre>";

      default:
        return 'Unknown output format';

    }
  }

  /**
   * Lists the formats that the Barcodes module can output.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The formats.
   */
  #[CLI\Command(name: 'barcodes:formats', aliases: ['barcodes-formats'])]
  #[CLI\Help(description: 'Lists the formats that the Barcodes module can output.')]
  #[CLI\Usage(name: 'drush barcodes:formats', description: 'A table of formats showing short name (abbreviation) and description.')]
  #[CLI\Usage(name: 'drush barcodes:formats --format=json --fields=short-name', description: 'Available short names, in JSON format.')]
  #[CLI\FieldLabels(labels: ['short-name' => 'Abbreviation', 'description' => 'Description'])]
  #[CLI\DefaultFields(fields: ['short-name', 'description'])]
  public function formats(): RowsOfFields {
    $rows = [];
    foreach (BarcodeGenerator::BARCODETYPES as $key => $value) {
      $rows[] = ['short-name' => $key, 'description' => $value];
    }
    return new RowsOfFields($rows);
  }

}
