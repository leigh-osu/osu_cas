<?php

namespace Drupal\charts_highcharts_maps\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for map data.
 */
class MapDataController extends ControllerBase {

  /**
   * Method to return JSON from the field name and taxonomy term.
   *
   * @param string $json_field_name
   *   The json_field_name.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy_term.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   It will return JsonResponse.
   */
  public function json(string $json_field_name, TermInterface $taxonomy_term) {
    $json = $taxonomy_term->{$json_field_name}->value ?? '{}';
    return new JsonResponse(Json::decode($json));
  }

  /**
   * Check to ensure the current user has access.
   *
   * @param string $json_field_name
   *   The json_field_name.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy_term.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   It will return AccessResultInterface.
   */
  public function checkAccess(string $json_field_name, TermInterface $taxonomy_term): AccessResultInterface {
    return $taxonomy_term->access('view', $this->currentUser(), TRUE);
  }

}
