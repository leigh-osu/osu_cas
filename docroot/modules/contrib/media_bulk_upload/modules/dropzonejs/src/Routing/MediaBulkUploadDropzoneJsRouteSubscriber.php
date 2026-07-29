<?php

namespace Drupal\media_bulk_upload_dropzonejs\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\media_bulk_upload_dropzonejs\Form\MediaBulkUploadDropzoneJsForm;
use Symfony\Component\Routing\RouteCollection;

/**
 * Media Bulk Upload route subscriber.
 */
class MediaBulkUploadDropzoneJsRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('media_bulk_upload.upload_form');
    $route->setDefault('_form', MediaBulkUploadDropzoneJsForm::class);
  }

}
