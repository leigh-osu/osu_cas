<?php

declare(strict_types=1);

namespace Drupal\barcodes\Template;

use Com\Tecnick\Barcode\Barcode as BarcodeGenerator;
use Drupal\Core\Utility\Token;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides a "barcode" Twig filter for formatting text as a barcode.
 *
 * @package Drupal\barcodes\Template
 */
class Barcode extends AbstractExtension {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs a Barcode Twig extension.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(Token $token) {
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return [
      new TwigFilter(
        'barcode',
        [$this, 'filterBarcode'],
        ['is_safe' => ['html']]
      ),
    ];
  }

  /**
   * Barcode filter.
   *
   * @param string $value
   *   The string that should be formatted as a barcode.
   * @param string $type
   *   The barcode type.
   * @param string $color
   *   The barcode color.
   * @param int $height
   *   The barcode height in pixels.
   * @param int $width
   *   The barcode width in pixels.
   * @param int $padding_top
   *   The barcode top padding in pixels.
   * @param int $padding_right
   *   The barcode right padding in pixels.
   * @param int $padding_bottom
   *   The barcode bottom padding in pixels.
   * @param int $padding_left
   *   The barcode left padding in pixels.
   * @param string $format
   *   The barcode format. One of: 'svg', 'png', 'htmldiv', 'unicode', 'binary'.
   *
   * @return string
   *   The barcode markup.
   *
   * @throws \Com\Tecnick\Barcode\Exception
   */
  public function filterBarcode(string $value, string $type = 'QRCODE', string $color = '#000000', int $height = 100, int $width = 100, int $padding_top = 0, int $padding_right = 0, int $padding_bottom = 0, int $padding_left = 0, string $format = 'svg'): string {

    $generator = new BarcodeGenerator();
    $value = $this->token->replace($value);

    $barcode = $generator->getBarcodeObj(
      $type,
      $value,
      $width,
      $height,
      $color,
      [$padding_top, $padding_right, $padding_bottom, $padding_left]
    );

    switch (strtolower($format)) {
      case 'svg':
        return $barcode->getSvgCode();

      case 'png':
        return "<img alt=\"Embedded Image\" src=\"data:image/png;base64," . base64_encode($barcode->getPngData()) . "\" />";

      case 'htmldiv':
        return $barcode->getHtmlDiv();

      case 'unicode':
        return "<pre style=\"font-family:monospace;line-height:0.61em;font-size:6px;\">" . $barcode->getGrid(json_decode('"\u00A0"'), json_decode('"\u2584"')) . "</pre>";

      case 'binary':
        return "<pre style=\"font-family:monospace;\">" . $barcode->getGrid() . "</pre>";

      default:
        return '';

    }

  }

}
