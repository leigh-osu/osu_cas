<?php

declare(strict_types=1);

namespace Drupal\barcodes\Hook;

use Com\Tecnick\Barcode\Barcode as BarcodeGenerator;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations used to render Barcodes.
 */
final class BarcodesRenderHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(array $existing, string $type, string $theme, string $path): array {
    $barcode = [
      'variables' => [
        'type' => NULL,
        'format' => NULL,
        'value' => NULL,
        'width' => NULL,
        'height' => NULL,
        'color' => NULL,
        'padding_top' => NULL,
        'padding_right' => NULL,
        'padding_bottom' => NULL,
        'padding_left' => NULL,
        'show_value' => NULL,
        'extended_value' => NULL,
        'svg' => NULL,
        'png' => NULL,
        'htmldiv' => NULL,
        'unicode' => NULL,
        'binary' => NULL,
        'barcode' => NULL,
      ],
    ];
    $items = [];
    $items['barcode'] = $barcode;
    foreach (BarcodeGenerator::BARCODETYPES as $key => $type) {
      $suffix = str_replace(
        ['+'], ['plus'], strtolower($key)
      );
      $items['barcode__' . $suffix] = $barcode;
    }
    return $items;
  }

}
