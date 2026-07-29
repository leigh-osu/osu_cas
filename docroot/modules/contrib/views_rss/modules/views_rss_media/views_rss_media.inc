<?php

/**
 * @file
 * Preprocess functions for Views RSS: Media Elements module.
 */

use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * Preprocess function for item <media:content> element.
 */
function views_rss_media_preprocess_item_content(&$variables) {
  // No raw value = no preprocessing.
  if (empty($variables['raw']['items'])) {
    return;
  }

  $variables['elements'] = [];
  $file = [];
  foreach ($variables['raw']['items'] as $item) {
    // File fields.
    if (!empty($item['rendered']['#file'])) {
      $file = $item['rendered']['#file'];
    }
    // Image fields.
    if (!empty($item['rendered']['#item']->entity)) {
      $file = $item['rendered']['#item']->entity;
    }

    // Start building RSS element.
    $element = [
      'key' => 'media:content',
      'attributes' => [],
    ];

    // File entity found.
    if ($file instanceof FileInterface) {
      $mime_type = $file->getMimeType();
      // Image style is defined, need to link to resized version.
      if ($image_style_name = $item['rendered']['#image_style']) {
        $image_style = ImageStyle::load($image_style_name);
        $image_style_uri = $image_style->buildUri($file->getFileUri());
        $url = $image_style->buildUrl($file->getFileUri());

        // If the derivative doesn't exist yet, we won't be able to get its size
        // to add it to the 'length' attribute, so we need to create it first.
        $check_file_size = TRUE;
        if (!file_exists($image_style_uri)) {
          $check_file_size = $image_style->createDerivative($image_style_uri, $image_style_uri);
        }

        $element['attributes'] = [
          'url' => $url,
          'type' => $mime_type,
        ];
        if ($check_file_size) {
          $element['attributes']['fileSize'] = filesize($image_style_uri);
        }
      }
      // Normal image size.
      else {
        $element['attributes'] = [
          'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
          'fileSize' => $file->getSize(),
          'type' => $mime_type,
        ];
      }

      [$medium] = explode('/', $mime_type);
      $element['attributes']['medium'] = $medium;
    }
    // No file entity found, but something still was assigned to be displayed
    // as enclosure, so we just put its value in the url attribute.
    elseif (!empty($item['rendered']['#markup'])) {
      $element['attributes']['url'] = $item['rendered']['#markup'];
    }

    $variables['elements'][] = $element;
  }
}

/**
 * Preprocess function for item <media:title> and <media:description> elements.
 */
function views_rss_media_preprocess_item_text(&$variables) {
  foreach ($variables['elements'] as $delta => $element) {
    if (!empty($element['value'])) {
      $type = 'plain';
      $value_decoded = htmlspecialchars_decode($element['value'], ENT_QUOTES);
      if ($value_decoded != strip_tags($value_decoded)) {
        $type = 'html';
      }
      $variables['elements'][$delta]['attributes']['type'] = $type;
    }
  }
}

/**
 * Preprocess function for item <enclosure> element.
 */
function views_rss_media_preprocess_item_thumbnail(&$variables) {
  // No raw values = no preprocessing.
  if (empty($variables['raw']['items'])) {
    return;
  }

  $variables['elements'] = [];
  foreach ($variables['raw']['items'] as $item) {
    // Start building RSS element.
    $element = [
      'key' => 'media:thumbnail',
      'attributes' => [],
    ];

    // File fields.
    $file = NULL;
    if (!empty($item['rendered']['#file'])) {
      $file = $item['rendered']['#file'];
    }
    // Image fields.
    elseif (!empty($item['rendered']['#item']->entity)) {
      $file = $item['rendered']['#item']->entity;
    }

    // File entity found.
    if (!empty($file)) {
      // Image style is defined, need to link to resized version.
      if (!empty($item['rendered']['#image_style'])) {
        $image_style = ImageStyle::load($item['rendered']['#image_style']);
        $element['attributes']['url'] = $image_style->buildUrl($file->getFileUri());
      }
      // Normal image size.
      else {
        $element['attributes']['url'] = $file->createFileUrl(FALSE);
      }
    }

    // Media fields.
    elseif (!empty($item['rendered']['#media'])) {
      // Render the output so the URL can be extracted.
      $renderer = \Drupal::service('renderer');
      $output = method_exists($renderer, 'renderInIsolation') ?
        $renderer->renderInIsolation($item['rendered'], FALSE) :
        // @todo use DeprecationHelper when D9 support will be dropped
        // @phpstan-ignore method.deprecated
        $renderer->renderPlain($item['rendered'], FALSE);

      // Extract the URL from the output string.
      preg_match('/src="(.*)" width="(.*)" height="(.*)" alt="([^"]*)"/', $output, $matches);
      if (!empty($matches)) {
        $element['attributes']['url'] = $matches[1];
        $element['attributes']['width'] = $matches[2];
        $element['attributes']['height'] = $matches[3];

        // @todo Work out a better location for this.
        // @code
        // $element['attributes']['title'] = $matches[4];
        // @endcode
      }
    }

    // No file entity found, but something still was assigned to be displayed
    // as enclosure, so we just put its value in the url attribute.
    elseif (!empty($item['rendered']['#markup'])) {
      $element['attributes']['url'] = $item['rendered']['#markup'];
    }

    // Only add the element if the URL was present.
    if (!empty($element['attributes']['url'])) {
      // Convert the URL to an absolute path.
      if (strpos($element['attributes']['url'], '/') === 0) {
        $element['attributes']['url'] = $GLOBALS['base_url'] . $element['attributes']['url'];
      }

      $variables['elements'][] = $element;
    }
  }
}

/**
 * Preprocess function for item <media:category> element.
 *
 * @see views_rss_core_preprocess_item_category()
 */
function views_rss_media_preprocess_item_category(&$variables) {
  // No raw values = no preprocessing.
  if (empty($variables['raw']['items'])) {
    return;
  }

  $variables['elements'] = [];
  foreach ($variables['raw']['items'] as $item) {

    // According to RSS Advisory Board, the category's value should be
    // a slash-delimited string that identifies a hierarchical position
    // in the taxonomy.
    $values = [];
    // Load parent term objects (this includes original term as well).
    if ($parents = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadAllParents($item['raw']->entity->id())) {
      foreach ($parents as $parent) {
        $values[] = $parent->label();
      }
    }

    $element = [
      'key' => 'media:category',
      'value' => implode('/', array_reverse($values)),
      'attributes' => [
        'label' => $item['raw']->entity->label(),
      ],
    ];

    // Drupal uses term URL for domain attribute on main category element
    // (similar to media:category). RSS Best Practices say that domain attribute
    // identifies the category's taxonomy - which suggests either vocabulary
    // name or its URL. We don't have any safe way to know public vocabulary
    // URL, could use its name instead though? @TODO?
    if ($item['raw']->target_id) {
      $element['attributes']['scheme'] = $item['raw']->entity->url('canonical', ['absolute' => TRUE]);
    }

    $variables['elements'][] = $element;
  }
}
