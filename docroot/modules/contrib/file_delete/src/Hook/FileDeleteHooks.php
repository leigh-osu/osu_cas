<?php

namespace Drupal\file_delete\Hook;

use Drupal\file_delete\Form\FileDeleteForm;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for file_delete.
 */
class FileDeleteHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the file_delete module.
      case 'help.page.file_delete':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('This module provides the ability to easily delete files within Drupal administration.') . '</p>';
        return $output;

      default:
    }
  }

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public static function entityTypeBuild(array &$entity_types): void {
    $entity_types['file']->setFormClass('delete', FileDeleteForm::class);
  }

}
