<?php

declare(strict_types=1);

namespace Drupal\layout_builder_reorder\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for move layout builder section.
 */
class MoveLayoutBuilderSectionController extends ControllerBase {

  use LayoutRebuildTrait;

  public function __construct(
    protected LayoutTempstoreRepositoryInterface $layoutTempstoreRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): MoveLayoutBuilderSectionController {
    return new static(
      $container->get('layout_builder.tempstore_repository')
    );
  }

  /**
   * Implements magic method to rearrange sections and rebuild the layout.
   */
  public function __invoke(SectionStorageInterface $section_storage, string $delta, string $new_delta): AjaxResponse {
    // Rearrange sections.
    $sections = $section_storage->getSections();
    if (\array_key_exists($new_delta, $sections)) {
      $temp = $sections[$delta];
      $sections[$delta] = $sections[$new_delta];
      $sections[$new_delta] = $temp;
    }

    // Save new order.
    $section_storage->removeAllSections();
    foreach ($sections as $section) {
      $section_storage->appendSection($section);
    }
    $this->layoutTempstoreRepository->set($section_storage);

    // Rebuild UI.
    return $this->rebuildLayout($section_storage);
  }

}
