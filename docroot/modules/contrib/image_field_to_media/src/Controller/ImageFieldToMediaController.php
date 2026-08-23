<?php

namespace Drupal\image_field_to_media\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Path\PathValidator;

/**
 * Check if the "Image" media type exists and informs a user if it doesn't.
 *
 * In profiles like "minimal" are absent the configurations of the "Image" media
 * type. This cause the error message: "Field field_media_image is unknown."
 * To prevent this non-informative message we check if the "Image" media type
 * exists and if no, then we display the message that a user have to create the
 * "Image" media type. Also, if the "Image" media type does not have the
 * "field_media_image" field we display the message that a user have to create
 * this field. If there are no problems, the user is redirected to the settings
 * form of the Image media field. In case of problem a user is redirected back
 * to the "Manage fields" page.
 * See https://www.drupal.org/project/image_field_to_media/issues/32743
 */
class ImageFieldToMediaController extends ControllerBase {

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info.
   * @param \Symfony\Component\HttpFoundation\Request $currentRequest
   *   The current request.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   The current route match.
   * @param \Drupal\Core\Path\PathValidator $pathValidator
   *   The path validator.
   */
  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected Request $currentRequest,
    protected CurrentRouteMatch $currentRouteMatch,
    protected PathValidator $pathValidator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('current_route_match'),
      $container->get('path.validator')
    );
  }

  /**
   * Builds the response.
   */
  public function validate(): RedirectResponse {
    // Check if the "Image" media type exists. And check if the "Image" media
    // type has the "field_media_image" field.
    if (!$this->imageMediaTypeExists()) {
      $message = $this->t('Media type with machine name %mediaTypeName does not exist. You have to create it first.', ['%mediaTypeName' => 'image']);
      $this->messenger()->addError($message);
      return $this->redirectBackToManageFieldsPage();
    }
    elseif (!$this->fieldMediaImageExists()) {
      $message = $this->t('The field with the machine name %fieldName does not exist in the media type %mediaTypeName. You have to create it.', [
        '%fieldName' => 'field_media_image',
        '%mediaTypeName' => 'image',
      ]);
      $this->messenger()->addError($message);
      return $this->redirectBackToManageFieldsPage();
    }
    else {
      // Redirect to the settings form of the Image media field.
      $field_config = $this->currentRouteMatch->getParameter('field_config');
      return $this->redirect('image_field_to_media.field_settings_form', ['field_config' => $field_config]);
    }

  }

  /**
   * Check if the "Image" media type exists.
   */
  private function imageMediaTypeExists(): bool {
    $mediaBundlesInfo = $this->entityTypeBundleInfo->getBundleInfo('media');
    return array_key_exists('image', $mediaBundlesInfo);
  }

  /**
   * Check if the "Image" media type has the "field_media_image" field.
   */
  private function fieldMediaImageExists(): bool {
    $imageMediaFields = $this->entityFieldManager->getFieldDefinitions('media', 'image');
    return array_key_exists('field_media_image', $imageMediaFields);
  }

  /**
   * Redirect back to the "Manage fields" page (previous page).
   */
  private function redirectBackToManageFieldsPage(): RedirectResponse {
    $previousUrl = $this->currentRequest->headers->get('referer');
    $fakeRequest = $this->currentRequest::create($previousUrl);
    $urlObject = $this->pathValidator->getUrlIfValid($fakeRequest->getRequestUri());
    return $this->redirect($urlObject->getRouteName(), $urlObject->getRouteParameters());
  }

}
