<?php

declare(strict_types=1);

namespace Drupal\entityreference_filter\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\better_exposed_filters\BetterExposedFiltersHelper;
use Drupal\entityreference_filter\Ajax\EntityReferenceFilterRebuildCommand;
use Drupal\entityreference_filter\Service\FilterArgumentsResolver;
use Drupal\entityreference_filter\Service\FilterDependencyResolver;
use Drupal\entityreference_filter\Service\ReferenceViewOptionsBuilder;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to build dependent entityreference filters.
 */
final class EntityReferenceFilterAjaxController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs an EntityReferenceFilterAjaxController object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage for views.
   * @param \Drupal\views\ViewExecutableFactory $executableFactory
   *   The factory to load a view executable with.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   * @param \Drupal\entityreference_filter\Service\FilterArgumentsResolver $filterArgumentsResolver
   *   The filter arguments resolver.
   * @param \Drupal\entityreference_filter\Service\ReferenceViewOptionsBuilder $referenceViewOptionsBuilder
   *   The reference view options builder.
   * @param \Drupal\entityreference_filter\Service\FilterDependencyResolver $filterDependencyResolver
   *   The filter dependency resolver.
   */
  public function __construct(
    protected EntityStorageInterface $storage,
    protected ViewExecutableFactory $executableFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected FilterArgumentsResolver $filterArgumentsResolver,
    protected ReferenceViewOptionsBuilder $referenceViewOptionsBuilder,
    protected FilterDependencyResolver $filterDependencyResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager')->getStorage('view'),
      $container->get('views.executable'),
      $container->get('module_handler'),
      $container->get(FilterArgumentsResolver::class),
      $container->get(ReferenceViewOptionsBuilder::class),
      $container->get(FilterDependencyResolver::class)
    );
  }

  /**
   * Loads and renders a view via AJAX.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when the form id is missing or not a valid HTML id.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the view was not found.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the view isn't accessible.
   *
   * @see \Drupal\views\Controller\ViewAjaxController::ajaxView()
   */
  public function ajaxFiltersValuesRebuild(Request $request) {
    $view_data = $request->request->all('view');
    $name = $view_data['view_name'] ?? FALSE;
    $display_id = $view_data['view_display_id'] ?? FALSE;
    // The exposed identifier of the filter the user changed; the cascade walks
    // its descendants.
    $changed_filter = (string) $request->request->get('changed_filter', '');

    // Reject form ids that are not valid HTML ids instead of mangling them;
    // the client builds jQuery selectors from this value.
    $form_html_id = (string) $request->request->get('form_id', '');
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $form_html_id)) {
      throw new BadRequestHttpException('Invalid form id.');
    }

    if (!empty($name) && !empty($display_id) && $changed_filter !== '') {
      $response = new AjaxResponse();

      // Load the view to rebuild the filters for.
      if (!$entity = $this->storage->load($name)) {
        throw new NotFoundHttpException();
      }

      $view = $this->executableFactory->get($entity);

      if ($view && $view->access($display_id) && $view->setDisplay($display_id)) {

        $exposed_plugin = $view->display_handler->getPlugin('exposed_form');
        $exposed_plugin_options = $exposed_plugin->options ?? NULL;

        $filters = $view->getHandlers('filter', $display_id);

        // Build the dependency graph from the display configuration and walk
        // the descendants of the changed filter in topological order. The
        // server owns the cascade, so the client never loops.
        $graph_filters = [];
        $identifier_to_key = [];
        foreach ($filters as $key => $filter) {
          $identifier = (string) ($filter['expose']['identifier'] ?? $key);
          $graph_filters[$key] = [
            'reference_arguments' => $filter['reference_arguments'] ?? '',
            'identifier' => $identifier,
          ];
          $identifier_to_key[$identifier] = $key;
        }
        $graph = $this->filterDependencyResolver->buildGraph($graph_filters);
        $changed_key = $identifier_to_key[$changed_filter] ?? $changed_filter;
        $order = $this->filterDependencyResolver->descendantsTopological($graph, $changed_key);

        // Effective values keyed by exposed identifier, recomputed as the
        // cascade descends so each level sees its parents' new values.
        $computed_values = [];

        foreach ($order as $dependent_filter_name) {
          if (!isset($filters[$dependent_filter_name])) {
            continue;
          }

          $reference_display = !empty($filters[$dependent_filter_name]['reference_display']) ?
            $filters[$dependent_filter_name]['reference_display'] : FALSE;

          $reference_arguments = !empty($filters[$dependent_filter_name]['reference_arguments']) ?
            $filters[$dependent_filter_name]['reference_arguments'] : FALSE;

          if ($reference_display && $reference_arguments && str_contains((string) $reference_display, ':')) {
            $args = $this->extractViewArgs($request, $dependent_filter_name, $filters, $computed_values);

            // Shared with the run-time filter plugin; NULL means no such view
            // or no access.
            $built_options = $this->referenceViewOptionsBuilder->buildOptions((string) $reference_display, $args);
            if ($built_options === NULL) {
              throw new NotFoundHttpException();
            }

            $filter_is_required = $filters[$dependent_filter_name]['expose']['required'] ?? FALSE;
            $filter_is_multiple = $filters[$dependent_filter_name]['expose']['multiple'] ?? FALSE;
            $filter_type = $filters[$dependent_filter_name]['type'] ?? 'select';

            $new_options = [];
            // The '- Any -' option, kept first by the union below.
            if (!$filter_is_required && $filter_type === 'select' && !$filter_is_multiple) {
              $new_options['All'] = $this->t('- Any -');
            }
            $new_options += $built_options;

            // Rewrite options with Better Exposed Filters.
            if ($exposed_plugin_options && $this->moduleHandler->moduleExists('better_exposed_filters')) {
              $bef_advanced = $exposed_plugin_options['bef']['filter'][$dependent_filter_name]['advanced'] ?? [];
              $rewrite_to = $bef_advanced['rewrite']['filter_rewrite_values'] ?? NULL;
              if ($rewrite_to) {
                // Do not reorder options during rewriting.
                // Entity reference view is the only source of sorting order.
                $reorder = FALSE;
                $new_options = BetterExposedFiltersHelper::rewriteOptions(
                  $new_options,
                  $rewrite_to,
                  $reorder,
                  !empty($bef_advanced['rewrite']['filter_rewrite_values_key']),
                );
              }
            }
            // Indexed {value, label} pairs, not a map: a JS object would
            // re-sort integer-like keys and push 'All' after the numeric ids.
            $options = [];
            foreach ($new_options as $val => $label) {
              $options[] = ['value' => (string) $val, 'label' => (string) $label];
            }
            $has_values = !empty($built_options);
            $hide_empty_filter = (bool) ($filters[$dependent_filter_name]['hide_empty_filter'] ?? FALSE);

            // Recompute the effective value and feed it to the next level.
            $identifier = $graph_filters[$dependent_filter_name]['identifier'];
            $current = $computed_values[$identifier] ?? ($request->request->all()[$identifier] ?? []);
            $selected = $this->filterDependencyResolver->computeEffectiveValue(
              (array) $current,
              array_keys($built_options),
              (bool) $filter_is_required,
              (bool) $filter_is_multiple,
            );
            $computed_values[$identifier] = $selected;

            // The client matches the form field by its exposed identifier.
            $response->addCommand(new EntityReferenceFilterRebuildCommand(
              $identifier,
              $form_html_id,
              $options,
              $hide_empty_filter,
              $has_values,
              $selected,
            ));
          }
        }

        return $response;
      }

      throw new AccessDeniedHttpException();
    }

    throw new NotFoundHttpException();
  }

  /**
   * Extract and convert filter arguments to the actual values.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   * @param string $dependent_filter_name
   *   Dependent filter name.
   * @param array $filters
   *   Views filter handlers.
   * @param array $computed_values
   *   Effective values recomputed earlier in the cascade, keyed by exposed
   *   identifier. They take precedence over the POSTed values.
   *
   * @return array
   *   Calculated filter arguments.
   */
  protected function extractViewArgs(Request $request, $dependent_filter_name, array $filters, array $computed_values = []) {
    $view_data = $request->request->all('view');
    $parent_view_args = (string) ($view_data['view_args'] ?? '');
    $parent_view_args = $parent_view_args !== '' ? explode('/', $parent_view_args) : [];

    // Arguments can be empty, make sure they are passed on as NULL so that
    // argument validation is not triggered.
    $parent_view_args = array_map(static function ($parent_view_arg) {
      return ($parent_view_arg === '' ? NULL : $parent_view_arg);
    }, $parent_view_args);

    return $this->filterArgumentsResolver->resolve(
      (string) ($filters[$dependent_filter_name]['reference_arguments'] ?? ''),
      $parent_view_args,
      $view_data['view_context_args'] ?? [],
      static function (string $controlling_filter) use ($request, $computed_values) {
        // A value already recomputed by the cascade wins over the POSTed one.
        $value = $computed_values[$controlling_filter]
          ?? ($request->request->all()[$controlling_filter] ?? NULL);
        return !empty($value) ? $value : NULL;
      }
    );
  }

}
