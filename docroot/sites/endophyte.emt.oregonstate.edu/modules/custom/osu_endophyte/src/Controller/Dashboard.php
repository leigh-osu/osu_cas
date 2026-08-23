<?php

namespace Drupal\osu_endophyte\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

class Dashboard extends ControllerBase{

  /**
   * Returns a basic site dashboard
   */
  public function content()
  {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'sample')
      ->condition('field_sample_status', 'sample_arrival_pending')
      ->accessCheck(FALSE);
    $sample_arrival_pending_count = $query->count()->execute();

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'sample')
      ->condition('field_sample_status', 'sample_tests_pending')
      ->accessCheck(FALSE);
    $sample_tests_pending_count = $query->count()->execute();

    $date = new DrupalDateTime('7 days ago');
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'sample')
      ->condition('field_sample_status', 'sample_tests_completed')
      ->condition('field_sample_date_completed', $formatted, '>=')
      ->accessCheck(FALSE);
    $sample_tests_completed_count = $query->count()->execute();

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'sample')
      ->condition('field_sample_status', 'sample_tests_cancelled')
      ->condition('field_sample_date_completed', $formatted, '>=')
      ->accessCheck(FALSE);
    $sample_tests_cancelled_count = $query->count()->execute();

    // Load the Twig theme engine so we can use twig_render_template().
//    include_once \Drupal::root() . '/core/themes/engines/twig/twig.engine';
//    $markup = twig_render_template(drupal_get_path('module', 'osu_endophyte') . "/templates/osu-endophyte-test-certificate.html.twig", $variables);
//    $page_build = array();
//    $page_build['sample_status'] =
//      [
////        '#markup' => $this->t('<p>There are @sample_arrival_pending_count pending arrival.</p>', $sample_arrival_pending_count),
//      ];
//
//    $page_build['user_status'] =
//      [
//        '#markup' => $this->t('<p>Hello Users!</p>'),
//      ];
//    return $page_build;
    \Drupal::keyValueExpirable('tempstore.shared.views')
      ->delete('lab_tests');

    return [
      // theme hook name.
      '#theme' => 'osu_endophyte_dashboard',
      // variables.
      '#sample_arrival_pending_count' => $sample_arrival_pending_count,
      '#sample_tests_pending_count' => $sample_tests_pending_count,
      '#sample_tests_completed_count' => $sample_tests_completed_count,
      '#sample_tests_cancelled_count' => $sample_tests_cancelled_count,
    ];
  }

}
