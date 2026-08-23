<?php

declare(strict_types=1);

namespace Drupal\barcodes\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Hook implementations used to provide help.
 */
final class BarcodesHelpHooks {
  use StringTranslationTrait;

  /**
   * Constructs a new BarcodesHelpHooks service.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      // Main module help for the barcodes module.
      case 'help.page.barcodes':
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Barcodes module provides a Field Formatter for various field types, a Block plugin, and a Twig Filter to display various field types as rendered Barcodes. Supports using tokens for barcode values. To find out more about these features and how to use this module, please read the <a href=":url">Barcodes module documentation</a>.', [':url' => 'https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/barcodes']) . '</p>';
        return $output;
    }
    return NULL;
  }

}
