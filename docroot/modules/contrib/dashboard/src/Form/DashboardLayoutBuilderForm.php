<?php

namespace Drupal\dashboard\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dashboard form.
 *
 * @property \Drupal\dashboard\DashboardInterface $entity
 */
class DashboardLayoutBuilderForm extends EntityForm {

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * Constructs a new DashboardLayoutBuilderForm.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layoutTempstoreRepository
   *   The layout tempstore repository.
   */
  public function __construct(
    protected LayoutTempstoreRepositoryInterface $layoutTempstoreRepository,
  ) {
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
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL) {
    // These classes are needed to ensure previews look like the dashboard
    // itself. We attach the library with the styling too.
    $dashboard_id = $this->entity->id();
    $classes = [
      'dashboard',
      $dashboard_id ? Html::getClass('dashboard--' . $dashboard_id) : NULL,
    ];
    $classes = implode(' ', $classes);
    $form['layout_builder'] = [
      '#type' => 'layout_builder',
      '#section_storage' => $section_storage,
      '#prefix' => "<div class='$classes'>",
      '#suffix' => '</div>',
      '#process' => [[static::class, 'layoutBuilderElementGetKeys']],
      '#attached' => [
        'library' => ['dashboard/dashboard'],
      ],
    ];
    $this->sectionStorage = $section_storage;
    return parent::buildForm($form, $form_state);
  }

  /**
   * Form element #process callback.
   *
   * Save the layout builder element array parents as a property on the top form
   * element so that they can be used to access the element within the whole
   * render array later.
   *
   * @see \Drupal\layout_builder\Controller\LayoutBuilderHtmlEntityFormController
   */
  public static function layoutBuilderElementGetKeys(array $element, FormStateInterface $form_state, &$form) {
    $form['#layout_builder_element_keys'] = $element['#array_parents'];
    $form['#pre_render'][] = [static::class, 'renderLayoutBuilderAfterForm'];
    $form['#post_render'][] = [static::class, 'addRenderedLayoutBuilder'];
    return $element;
  }

  /**
   * Render API #pre_render callback for form containing layout builder element.
   *
   * Because the layout builder element can contain components with forms, it
   * needs to exist outside forms within the DOM, to avoid nested form tags.
   * The layout builder element is rendered to markup here and saved, and later
   * the saved markup will be appended after the form markup.
   *
   * @param array $form
   *   The rendered form.
   *
   * @return array
   *   Renders the layout builder element, if it exists, and adds it to the
   *   form.
   *
   * @see ::addRenderedLayoutBuilder()
   */
  #[TrustedCallback]
  public static function renderLayoutBuilderAfterForm(array $form): array {
    if (isset($form['#layout_builder_element_keys'])) {
      $layout_builder_element = &NestedArray::getValue($form, $form['#layout_builder_element_keys']);
      // Save the rendered layout builder HTML to a non-rendering child key.
      // Since this method is a pre_render callback, it is assumed that it is
      // called while rendering with an active render context, so that the
      // cache metadata and attachments bubble correctly.
      $form['#layout_builder_markup'] = \Drupal::service('renderer')->render($layout_builder_element);
      // Remove the layout builder child element within form array.
      $layout_builder_element = [];
    }
    return $form;
  }

  /**
   * Render API #post_render callback that adds layout builder markup to form.
   *
   * @param string $html
   *   The rendered form.
   * @param array $form
   *   The form render array.
   *
   * @return string
   *   The render string with any layout builder markup added.
   */
  #[TrustedCallback]
  public static function addRenderedLayoutBuilder(string $html, array $form): string {
    if (isset($form['#layout_builder_markup'])) {
      $html .= $form['#layout_builder_markup'];
    }

    return $html;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save dashboard layout');
    $actions['delete']['#access'] = FALSE;
    $actions['#weight'] = -1000;

    $actions['discard_changes'] = [
      '#type' => 'link',
      '#title' => $this->t('Discard changes'),
      '#attributes' => ['class' => ['button']],
      '#url' => $this->sectionStorage->getLayoutBuilderUrl('discard_changes'),
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = $this->sectionStorage->save();
    $this->layoutTempstoreRepository->delete($this->sectionStorage);

    $message_args = ['%label' => $this->entity->label()];
    $message = $return == SAVED_NEW
      ? $this->t('Created new dashboard %label layout.', $message_args)
      : $this->t('Updated dashboard %label layout.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->sectionStorage->getRedirectUrl());

    return $return;
  }

}
