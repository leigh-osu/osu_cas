<?php

declare (strict_types=1);

namespace Drupal\layout_builder_iframe_modal\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder_iframe_modal\Controller\RebuildController;
use Drupal\layout_builder_iframe_modal\IframeModalHelper;

/**
 * Form-related hook implementations for Layout Builder Iframe Modal.
 */
class FormHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new FormHooks instance.
   *
   * @param \Drupal\layout_builder_iframe_modal\IframeModalHelper $iframeModalHelper
   *   The iframe modal helper service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    protected IframeModalHelper $iframeModalHelper,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    // \Drupal\layout_builder\Form\ConfigureBlockFormBase::doBuildForm sets an
    // ajax handler on the main submit action for the form whenever any element
    // on the form is rebuilt using ajax. This is then cached and a subsequent
    // form submission can be processed as ajax even when we don't want that.
    // This module never wants the submit action to be ajax,
    // so we ensure that. See
    // https://www.drupal.org/project/layout_builder_iframe_modal/issues/3202523
    if (($this->iframeModalHelper->isModalRoute('layout_builder.add_block') && $form_id == 'layout_builder_add_block') || ($this->iframeModalHelper->isModalRoute('layout_builder.update_block') && $form_id == 'layout_builder_update_block')) {
      if (!empty($form['actions']['submit']['#ajax'])) {
        unset($form['actions']['submit']['#ajax']);
      }
    }

    $layoutBuilderForm = str_contains($form_id, 'layout_builder_form') || str_contains($form_id, 'layout_builder_overrides_form');
    if (!$layoutBuilderForm) {
      return;
    }

    /** @var \Drupal\layout_builder\SectionStorageInterface $section_storage */
    $section_storage = $this->routeMatch->getParameter('section_storage');

    if (!$section_storage) {
      return;
    }

    $is_translation = isset($form['layout_builder__translation']['widget']);
    $url = RebuildController::buildRouteUrl($section_storage, $is_translation);
    $url->setOption('attributes', [
      'class' => ['use-ajax', 'button'],
      'data-disable-refocus' => 'true',
    ]);

    $form['actions']['rebuild-layout'] = [
      '#type' => 'link',
      '#weight' => 16,
      '#title' => $this->t('Rebuild layout'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['hidden'],
      ],
    ];
  }

}
