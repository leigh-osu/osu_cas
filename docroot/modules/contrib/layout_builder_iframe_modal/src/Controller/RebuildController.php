<?php

declare(strict_types=1);

namespace Drupal\layout_builder_iframe_modal\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_iframe_modal\Ajax\ScrollToBlockCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a controller to rebuild the layout.
 */
class RebuildController implements ContainerInjectionInterface {

  use LayoutRebuildTrait;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * RebuildController constructor.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository')
    );
  }

  /**
   * Rebuild the current layout and close the dialog.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response.
   */
  public function rebuild(SectionStorageInterface $section_storage): AjaxResponse {
    $this->layoutTempstoreRepository->set($section_storage);
    $response = $this->rebuildLayout($section_storage);
    // Close the modal itself.
    $response->addCommand(new CloseDialogCommand('#drupal-lbim-modal'));
    // Close settings tray (D9).
    $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    // Close settings tray (D10+).
    $response->addCommand(new CloseDialogCommand('#drupal-off-canvas-wrapper'));
    // Close Layout Builder Browser modal, if available.
    $response->addCommand(new CloseDialogCommand('#layout-builder-modal'));
    $response->addCommand(new ScrollToBlockCommand());
    return $response;
  }

  /**
   * Build an Url for this route.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param bool $translated
   *   The rebuild url should be for a layout translation.
   *
   * @return \Drupal\Core\Url
   *   Url for this route.
   */
  public static function buildRouteUrl(SectionStorageInterface $section_storage, bool $translated): Url {
    $route = 'layout_builder_iframe_modal.rebuild';
    if ($translated) {
      $route .= '_translated';
    }
    $route_paramters = [
      'section_storage_type' => $section_storage->getStorageType(),
      'section_storage' => $section_storage->getStorageId(),
    ];

    return Url::fromRoute($route, $route_paramters);
  }

}
