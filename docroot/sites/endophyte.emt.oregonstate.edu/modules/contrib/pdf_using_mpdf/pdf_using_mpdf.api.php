<?php

/**
 * @file
 * Contains API hooks.
 */

use Drupal\node\NodeInterface;

/**
 * Alter html passed to PDF file before conversion.
 *
 * Implements hook_mpdf_html_alter()
 *
 * @param string $html
 *   Html content.
 * @param \Drupal\node\NodeInterface $node
 *   Processed node.
 */
function pdf_using_mpdf_mpdf_html_alter(&$html, NodeInterface $node) {

  // Append custom HTMl to node type `page`.
  if ($node->getType() == 'page') {
    $div = '<div>';
    $div .= 'This is super cool way to generate a PDF file!';
    $div .= '</div>';

    $html .= $div;
  }
}

/**
 * Alter PDF settings before conversion.
 *
 * Implements hook_mpdf_settings_alter()
 *
 * @param array $settings
 *   MPDF settings.
 * @param \Drupal\node\NodeInterface $node
 *   Processed node.
 */
function pdf_using_mpdf_mpdf_settings_alter(array &$settings, NodeInterface $node) {

  // Add page number to the header for node type `article`.
  if ($node->getType() == 'article') {
    $settings['pdf_header'] = "{PAGENO}\n<hr>";
  }
}
