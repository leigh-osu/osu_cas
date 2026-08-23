<?php

declare(strict_types=1);

namespace Drupal\entityreference_filter_search_api\Plugin\views\filter;

use Drupal\entityreference_filter\Plugin\views\filter\EntityReferenceFilterViewResult;
use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;
use Drupal\views\Attribute\ViewsFilter;

/**
 * Filter by entity id using items got from entity reference view (Search API).
 *
 * @see \Drupal\entityreference_filter\Plugin\views\filter\EntityReferenceFilterViewResult
 */
#[ViewsFilter("entityreference_filter_view_result_search_api")]
class EntityReferenceFilterViewResultSearchApi extends EntityReferenceFilterViewResult {
  use SearchApiFilterTrait;

}
