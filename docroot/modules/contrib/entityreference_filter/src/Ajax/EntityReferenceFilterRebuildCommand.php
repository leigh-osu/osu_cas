<?php

declare(strict_types=1);

namespace Drupal\entityreference_filter\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Sends rebuilt filter options to the client as structured data.
 *
 * The client builds the selectors and DOM, so the server never assembles
 * markup from user input.
 */
final class EntityReferenceFilterRebuildCommand implements CommandInterface {

  /**
   * Constructs an EntityReferenceFilterRebuildCommand.
   *
   * @param string $filterName
   *   The dependent filter machine name.
   * @param string $formHtmlId
   *   The validated HTML id of the exposed form.
   * @param list<array{value: string, label: string}> $options
   *   Ordered value/label pairs (plain text). Indexed, not associative, so JS
   *   keeps insertion order.
   * @param bool $hideEmptyFilter
   *   Whether the filter should be hidden when empty.
   * @param bool $hasValues
   *   Whether the rebuilt filter has any selectable values.
   * @param array|string $selected
   *   The value the cascade selected: an array for multiple filters, a string
   *   ('All' = no filtering) for single-value filters.
   */
  public function __construct(
    private readonly string $filterName,
    private readonly string $formHtmlId,
    private readonly array $options,
    private readonly bool $hideEmptyFilter,
    private readonly bool $hasValues,
    private readonly array|string $selected = 'All',
  ) {}

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'entityReferenceFilterRebuild',
      'filter_name' => $this->filterName,
      'form_html_id' => $this->formHtmlId,
      'options' => $this->options,
      'hide_empty_filter' => $this->hideEmptyFilter,
      'has_values' => $this->hasValues,
      'selected' => $this->selected,
    ];
  }

}
