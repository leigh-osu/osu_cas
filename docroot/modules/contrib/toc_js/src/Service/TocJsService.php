<?php

namespace Drupal\toc_js\Service;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;

/**
 * Provides a service for Toc.js configuration forms.
 */
class TocJsService {

  use StringTranslationTrait;

  public function __construct(
    private readonly RouteMatchInterface $currentRouteMatch,
  ) {}

  /**
   * Default settings for Toc.js.
   *
   * @return array
   *   The default settings.
   */
  public function defaultSettings() {
    return [
      'title' => $this->t('Table of contents'),
      'title_tag' => 'div',
      'title_classes' => 'toc-title,h2',
      'selectors' => 'h2,h3',
      'selectors_minimum' => 0,
      'container' => '.node',
      'prefix' => 'toc',
      'list_type' => 'ul',
      'list_classes' => '',
      'li_classes' => '',
      'inheritable_classes' => '',
      'classes' => '',
      'heading_classes' => '',
      'skip_invisible_headings' => 0,
      'use_heading_html' => 0,
      'heading_cleanup_selector' => '.visually-hidden, .sr-only',
      'collapsible_items' => 0,
      'collapsible_expanded' => 1,
      'back_to_top' => 0,
      'back_to_top_label' => $this->t('Back to top'),
      'back_to_top_selector' => '',
      'heading_focus' => 0,
      'back_to_toc' => 0,
      'back_to_toc_label' => $this->t('Back to table of contents'),
      'back_to_toc_classes' => 'visually-hidden-focusable',
      'smooth_scrolling' => 1,
      'scroll_to_offset' => '',
      'highlight_on_scroll' => 1,
      'highlight_offset' => 0,
      'sticky' => 0,
      'sticky_offset' => 0,
      'toc_container' => '',
      'ajax_page_updates' => 0,
      'observable_selector' => '',
    ];
  }

  /**
   * List of configuration items to add as attributes in the toc element.
   *
   * The switch from exclude to include was required as some external modules
   * were modifying the settings by adding some custom options we didn't want
   * to see in the generated HTML.
   *
   * @return string[]
   *   The list of configuration item names to not add as attributes.
   */
  public function getSettingsToIncludeAsAttributes(): array {
    return [
      'selectors',
      'selectors_minimum',
      'container',
      'prefix',
      'list_type',
      'list_classes',
      'li_classes',
      'heading_classes',
      'skip_invisible_headings',
      'use_heading_html',
      'heading_cleanup_selector',
      'collapsible_items',
      'collapsible_expanded',
      'back_to_top',
      'back_to_top_selector',
      'heading_focus',
      'back_to_toc',
      'back_to_toc_classes',
      'smooth_scrolling',
      'scroll_to_offset',
      'highlight_on_scroll',
      'highlight_offset',
      'sticky',
      'sticky_offset',
      'toc_container',
      'ajax_page_updates',
      'observable_selector',
    ];
  }

  /**
   * Get the field name for a given configuration key.
   */
  protected function buildEltName(string|null $parents_prefix, string $name): string {
    return empty($parents_prefix) ? $name : $parents_prefix . '[' . $name . ']';
  }

  /**
   * Build a Toc.js configuration form with a parent element.
   *
   * @param array $form
   *   The form to build.
   * @param array $values
   *   The values to use in the form.
   * @param array|string|null $parents
   *   The name for the parent element.
   * @param array $states
   *   The states to use in the form.
   */
  public function getTocForm(array &$form, array $values, array|string|null $parents = NULL, array $states = []): void {
    // Check if $parents_array is an array and has elements.
    if (is_array($parents) && count($parents) > 0) {
      $elt_prefix = array_shift($parents) . '[' . implode('][', $parents) . ']';
    }
    else {
      $elt_prefix = $parents;
    }

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('The text to use as a title for the table of contents.'),
      '#default_value' => $values['title'],
      '#maxlength' => 255,
      '#states' => $states,
    ];

    $form['title_tag'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title HTML tag'),
      '#description' => $this->t('The HTML tag to use for the table of contents title (defaults to div).'),
      '#default_value' => $values['title_tag'],
      '#states' => $states,
    ];

    $form['title_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title CSS classes'),
      '#description' => $this->t('List of CSS classes to apply to the table of contents title tag (comma separated).'),
      '#default_value' => $values['title_classes'],
      '#states' => $states,
    ];

    $form['selectors'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Selectors'),
      '#description' => $this->t('Comma-separated list of selectors for elements to be used as headings.'),
      '#default_value' => $values['selectors'],
      '#maxlength' => 2048,
      '#states' => $states,
    ];

    $form['selectors_minimum'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum elements'),
      '#description' => $this->t('Set a minimum of elements to display the toc. Set 0 to always display the TOC.'),
      '#default_value' => $values['selectors_minimum'],
      '#states' => $states,
    ];

    $form['container'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Container'),
      '#description' => $this->t('Element to find all selectors in.'),
      '#default_value' => $values['container'],
      '#maxlength' => 2048,
      '#states' => $states,
    ];

    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#description' => $this->t('Prefix for anchor tags and ToC elements default class names.'),
      '#default_value' => $values['prefix'],
      '#states' => $states,
    ];

    $form['list_type'] = [
      '#type' => 'select',
      '#title' => $this->t('List type'),
      '#description' => $this->t('Select the list type to use.'),
      '#default_value' => $values['list_type'],
      '#options' => [
        'ul' => $this->t('Unordered HTML list (ul)'),
        'ol' => $this->t('Ordered HTML list (ol)'),
      ],
      '#states' => $states,
    ];

    $form['list_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ToC list CSS classes'),
      '#description' => $this->t('List of CSS classes to apply to the table of contents list UL/OL tag (space separated).'),
      '#default_value' => $values['list_classes'],
      '#maxlength' => 2048,
      '#states' => $states,
    ];

    $form['li_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ToC list elements CSS classes'),
      '#description' => $this->t('List of CSS classes to apply to the table of contents items LI tags (space separated).'),
      '#default_value' => $values['li_classes'],
      '#maxlength' => 2048,
      '#states' => $states,
    ];

    $form['inheritable_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Inheritable CSS classes from headings'),
      '#description' => $this->t('Comma-separated list of CSS classes from headings that should be applied to the table of contents LI elements.'),
      '#default_value' => $values['inheritable_classes'],
      '#maxlength' => 2048,
      '#states' => $states,
    ];

    $form['classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Table of contents CSS classes'),
      '#description' => $this->t('List of CSS classes to apply to the table of contents DIV tag (comma separated).'),
      '#default_value' => $values['classes'],
      '#states' => $states,
    ];

    $form['heading_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS classes to add to headings'),
      '#description' => $this->t('List of CSS classes to apply to the page headings (space separated).  Can be used to apply a scroll-top-margin for example.'),
      '#default_value' => $values['heading_classes'],
      '#states' => $states,
    ];

    $form['skip_invisible_headings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip invisible headings'),
      '#description' => $this->t('Do not add invisible headings to the ToC.'),
      '#default_value' => $values['skip_invisible_headings'],
      '#states' => $states,
    ];

    $form['use_heading_html'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use heading html'),
      '#description' => $this->t('Use the heading html content for the ToC links instead of the text content.'),
      '#default_value' => $values['use_heading_html'],
      '#states' => $states,
    ];

    $form['heading_cleanup_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Heading cleanup selector'),
      '#description' => $this->t('Use this CSS selector to remove elements such as icons or visually hidden content from headings before they are added to the ToC.'),
      '#default_value' => $values['heading_cleanup_selector'],
      '#maxlength' => 2048,
      '#states' => $states,
    ];

    $form['collapsible_items'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable collapsible toc items (experimental)'),
      '#description' => $this->t('Allows toc items with children to be made collapsible.'),
      '#default_value' => $values['collapsible_items'],
      '#states' => $states,
    ];

    $form['collapsible_expanded'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show collapsible items expanded by default  (experimental)'),
      '#description' => $this->t('Collapsible items will be shown expanded by default, hidden otherwise.'),
      '#default_value' => $values['collapsible_expanded'],
      '#states' => NestedArray::mergeDeep($states, [
        'visible' => [
          ':input[name="' . $this->buildEltName($elt_prefix, 'collapsible_items') . '"]' => ['checked' => TRUE],
        ],
      ]),
    ];

    $form['back_to_top'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show "back to top" links'),
      '#description' => $this->t('Display "back to top" links next to headings.'),
      '#default_value' => $values['back_to_top'],
      '#states' => $states,
    ];

    $form['back_to_top_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Back to top link label'),
      '#description' => $this->t('The label to use for "back to top" links, span HTML tag is allowed.<br>WCAG: remember to define a visually-hidden/sr-only span label if you are using a CSS icon.'),
      '#default_value' => $values['back_to_top_label'] ?? 'Back to top',
      '#states' => NestedArray::mergeDeep($states, [
        'visible' => [
          ':input[name="' . $this->buildEltName($elt_prefix, 'back_to_top') . '"]' => ['checked' => TRUE],
        ],
      ]),
    ];

    $form['back_to_top_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Back to top heading filter selector'),
      '#description' => $this->t('Allows to filter the headings for which we want to display a back to top link.'),
      '#default_value' => $values['back_to_top_selector'],
      '#maxlength' => 2048,
      '#states' => NestedArray::mergeDeep($states, [
        'visible' => [
          ':input[name="' . $this->buildEltName($elt_prefix, 'back_to_top') . '"]' => ['checked' => TRUE],
        ],
      ]),
    ];

    $form['heading_focus'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Heading focus'),
      '#description' => $this->t('Set focus on corresponding heading when selected.'),
      '#default_value' => $values['heading_focus'],
      '#states' => $states,
    ];

    $form['back_to_toc'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show "back to toc" links'),
      '#description' => $this->t('Display "back to toc" links next to headings.'),
      '#default_value' => $values['back_to_toc'],
      '#states' => $states,
    ];

    $form['back_to_toc_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Back to toc link label'),
      '#description' => $this->t('The label to use for "back to toc" links, span HTML tag is allowed.<br>WCAG: remember to define a visually-hidden/sr-only span label if you are using a CSS icon.'),
      '#default_value' => $values['back_to_toc_label'],
      '#states' => NestedArray::mergeDeep($states, [
        'visible' => [
          ':input[name="' . $this->buildEltName($elt_prefix, 'back_to_toc') . '"]' => ['checked' => TRUE],
        ],
      ]),
    ];

    $form['back_to_toc_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Back to toc link CSS classes'),
      '#description' => $this->t('The CSS classes to add to the back to ToC link label (space separated).'),
      '#default_value' => $values['back_to_toc_classes'],
      '#states' => NestedArray::mergeDeep($states, [
        'visible' => [
          ':input[name="' . $this->buildEltName($elt_prefix, 'back_to_toc]') . '"]' => ['checked' => TRUE],
        ],
      ]),
    ];

    $form['smooth_scrolling'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Smooth scrolling'),
      '#description' => $this->t('Enable or disable smooth scrolling on click.'),
      '#default_value' => $values['smooth_scrolling'],
      '#states' => $states,
    ];

    $form['scroll_to_offset'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scroll offset'),
      '#description' => $this->t('Offset in CSS units to apply when scrolling to heading, ex: 10px or 2rem. You can also use the var syntax, ex: var(--toc-scroll-offset, 2rem).'),
      '#default_value' => $values['scroll_to_offset'],
      '#size' => 50,
      '#states' => $states,
    ];

    $form['highlight_on_scroll'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Highlight on scroll'),
      '#description' => $this->t('Add a class to the heading that is currently in focus.'),
      '#default_value' => $values['highlight_on_scroll'],
      '#states' => $states,
    ];

    $form['highlight_offset'] = [
      '#type' => 'number',
      '#title' => $this->t('Highlight offset'),
      '#description' => $this->t('Offset to trigger the next headline.'),
      '#default_value' => $values['highlight_offset'],
      // Highlight offset is not used anymore.
      '#access' => FALSE,
      '#states' => NestedArray::mergeDeep($states, [
        'visible' => [
          ':input[name="' . $this->buildEltName($elt_prefix, 'highlight_on_scroll') . '"]' => ['checked' => TRUE],
        ],
      ]),
    ];

    $form['sticky'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sticky'),
      '#description' => $this->t('Stick the toc on window scroll.'),
      '#default_value' => $values['sticky'],
      '#states' => $states,
    ];

    $form['sticky_offset'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sticky offset'),
      '#description' => $this->t('Offset in CSS units to apply when the toc is sticky, ex: 10px or 2rem. You can also use the var syntax, ex: var(--toc-sticky-offset, 2rem).'),
      '#default_value' => $values['sticky_offset'],
      '#size' => 50,
      '#states' => NestedArray::mergeDeep($states, [
        'visible' => [
          ':input[name="' . $this->buildEltName($elt_prefix, 'sticky') . '"]' => ['checked' => TRUE],
        ],
      ]),
    ];

    $form['toc_container'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Toc container selector'),
      '#description' => $this->t('Closest parent of the toc element to use for visibility and stickiness, defaults to using the toc element if empty.'),
      '#default_value' => $values['toc_container'],
      '#maxlength' => 2048,
      '#states' => $states,
    ];

    $form['ajax_page_updates'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ajax page updates handling (Experimental)'),
      '#description' => $this->t('Refresh the table of contents when the page is being updated using Ajax.'),
      '#default_value' => $values['ajax_page_updates'],
      '#states' => $states,
    ];

    $form['observable_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom observable container selector (Experimental)'),
      '#description' => $this->t('The selector of the container we wish to monitor for Ajax page updates, leave empty to use the default container selector.'),
      '#default_value' => $values['observable_selector'],
      '#maxlength' => 2048,
      '#states' => NestedArray::mergeDeep($states, [
        'visible' => [
          ':input[name="' . $this->buildEltName($elt_prefix, 'ajax_page_updates') . '"]' => ['checked' => TRUE],
        ],
      ]),
    ];

  }

  /**
   * Gets the entity used to provide context for the table of contents.
   *
   * Attempts to retrieve the current route entity, supporting nodes and
   * taxonomy terms. If no supported entity is present on the current route,
   * NULL is returned.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The current route entity (node or taxonomy term), or NULL if none is
   *   available.
   */
  public function getTocEntity() {
    $entity = NULL;
    if ($node = $this->currentRouteMatch->getParameter('node')) {
      $entity = $node;
    }
    /** @var \Drupal\taxonomy\TermInterface $taxonomy_term */
    elseif ($taxonomy_term = $this->currentRouteMatch->getParameter('taxonomy_term')) {
      $entity = $taxonomy_term;
    }
    return $entity;
  }

  /**
   * Build the render array for the toc element.
   *
   * @param string $id
   *   The unique identifier of the toc.
   * @param array $settings
   *   The configuration of the toc to render.
   *
   * @return array
   *   The resulting render array.
   */
  public function buildToc(string $id, array $settings): array {
    $build = [];

    // Lambda function to clean css identifiers.
    $cleanCssIdentifier = function ($value) {
      return Html::cleanCssIdentifier(trim($value));
    };

    // toc-js class is used to initialize the toc. Additional classes are added
    // from the configuration.
    $classes = array_map($cleanCssIdentifier, array_merge(['toc-js'], explode(',', $settings['classes'] ?? '')));
    $attributes = new Attribute(['class' => $classes]);
    $toc_id = Html::getUniqueId($cleanCssIdentifier($id));
    $attributes->setAttribute('id', $toc_id);
    $title_id = $toc_id . '__title';
    $titleClasses = empty($settings['title_classes']) ? 'h2' : $settings['title_classes'];
    $titleClassesArray = array_map($cleanCssIdentifier, array_merge(['toc-title'], explode(',', $titleClasses)));
    $title_attributes = new Attribute([
      'id' => $title_id,
      'class' => $titleClassesArray,
    ]);

    $include_as_data_attributes = $this->getSettingsToIncludeAsAttributes();
    foreach ($settings as $name => $setting) {
      if (!in_array($name, $include_as_data_attributes)) {
        continue;
      }
      $data_name = 'data-' . $cleanCssIdentifier($name);
      $attributes->setAttribute($data_name, Xss::filter((string) $setting, []));
    }

    // Provide some entity context if available.
    $entity = $this->getTocEntity();

    $build['toc_js'] = [
      '#theme' => 'toc_js',
      '#title' => Xss::filter($settings['title'], ['span']),
      '#tag' => Xss::filter(($settings['title_tag'] ?? '') ?: 'div', []),
      '#title_attributes' => $title_attributes,
      '#attributes' => $attributes,
      '#entity' => $entity,
      '#attached' => [
        'library' => [
          'toc_js/toc',
        ],
        'drupalSettings' => [
          'toc_js' => [
            $toc_id => [
              'back_to_top_label' => Xss::filter((string) ($settings['back_to_top_label'] ?? ''), ['span']),
              'back_to_toc_label' => Xss::filter((string) ($settings['back_to_toc_label'] ?? ''), ['span']),
            ],
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * Get cache tags for the table of contents.
   *
   * Returns cache tags based on the current route entity
   * (node or taxonomy term).
   * This ensures the table of contents is properly invalidated when the
   * underlying entity changes.
   *
   * @return array
   *   An array of cache tags from the current route entity, or an empty array
   *   if no supported entity is found.
   */
  public function getTocCacheTags(): array {
    /** @var \Drupal\node\NodeInterface $node */
    if ($node = $this->currentRouteMatch->getParameter('node')) {
      return $node->getCacheTags();
    }
    /** @var \Drupal\taxonomy\TermInterface $taxonomy_term */
    elseif ($taxonomy_term = $this->currentRouteMatch->getParameter('taxonomy_term')) {
      return $taxonomy_term->getCacheTags();
    }
    else {
      return [];
    }
  }

  /**
   * Get cache contexts for the table of contents.
   *
   * Returns cache contexts to ensure the table of contents varies by URL path.
   * This is necessary because the ToC content may differ based on the current
   * page being viewed.
   *
   * @return array
   *   An array containing 'url.path' cache context.
   */
  public function getTocCacheContexts(): array {
    return ['url.path'];
  }

}
