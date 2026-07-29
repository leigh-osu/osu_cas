<?php

declare(strict_types=1);

namespace Drupal\entityreference_filter\Service;

/**
 * Resolves dependencies between entity reference filters of a single display.
 *
 * A filter "depends on" another when its reference_arguments reference the
 * other filter's exposed identifier via the [identifier] token. The resulting
 * directed graph (controlling -> dependent) drives both the configure-time
 * cycle check and the run-time cascade.
 */
final class FilterDependencyResolver {

  public function __construct(
    protected FilterArgumentsResolver $filterArgumentsResolver,
  ) {}

  /**
   * Builds the controlling -> dependent dependency graph.
   *
   * @param array $filters
   *   Filters keyed by their handler key, each value an array with:
   *   - reference_arguments: (string) the raw reference_arguments option.
   *   - identifier: (string) the exposed filter identifier.
   *
   * @return array[]
   *   Map keyed by filter key; each value lists its direct dependents. Every
   *   key is present (possibly empty).
   */
  public function buildGraph(array $filters): array {
    // Map each exposed identifier back to its filter key.
    $identifierToKey = [];
    foreach ($filters as $key => $filter) {
      $identifier = (string) ($filter['identifier'] ?? '');
      if ($identifier !== '') {
        $identifierToKey[$identifier] = $key;
      }
    }

    $graph = array_fill_keys(array_keys($filters), []);
    foreach ($filters as $key => $filter) {
      $controlling = $this->filterArgumentsResolver->getControllingFilters((string) ($filter['reference_arguments'] ?? ''));
      foreach ($controlling as $identifier) {
        $controllingKey = $identifierToKey[$identifier] ?? NULL;
        if ($controllingKey !== NULL && !in_array($key, $graph[$controllingKey], TRUE)) {
          // Edge: controlling filter -> dependent filter.
          $graph[$controllingKey][] = $key;
        }
      }
    }

    return $graph;
  }

  /**
   * Detects a dependency cycle in the graph.
   *
   * @param array[] $graph
   *   Adjacency list from buildGraph().
   *
   * @return string[]
   *   The keys forming the first cycle found (the first key repeated at the
   *   end), or an empty array when the graph is acyclic.
   */
  public function detectCycle(array $graph): array {
    $state = [];
    $stack = [];
    $cycle = [];

    $visit = function (string $node) use (&$visit, &$graph, &$state, &$stack, &$cycle): bool {
      $state[$node] = 'open';
      $stack[] = $node;
      foreach ($graph[$node] ?? [] as $next) {
        if (($state[$next] ?? 'unseen') === 'open') {
          $index = array_search($next, $stack, TRUE);
          $cycle = array_slice($stack, $index);
          $cycle[] = $next;
          return TRUE;
        }
        if (($state[$next] ?? 'unseen') === 'unseen' && $visit($next)) {
          return TRUE;
        }
      }
      array_pop($stack);
      $state[$node] = 'done';
      return FALSE;
    };

    foreach (array_keys($graph) as $node) {
      if (($state[$node] ?? 'unseen') === 'unseen' && $visit($node)) {
        return $cycle;
      }
    }

    return [];
  }

  /**
   * Returns the descendants of a node in topological order.
   *
   * Each returned key is ordered after all of its controlling filters that are
   * also reachable, so a cascade can recompute them left to right. A visited
   * set guarantees termination even if a cycle slipped past validation.
   *
   * @param array[] $graph
   *   Adjacency list from buildGraph().
   * @param string $start
   *   The key of the filter the user changed.
   *
   * @return string[]
   *   Dependent filter keys in topological order (excluding $start).
   */
  public function descendantsTopological(array $graph, string $start): array {
    $visited = [];
    $order = [];

    $dfs = function (string $node) use (&$dfs, &$graph, &$visited, &$order): void {
      $visited[$node] = TRUE;
      foreach ($graph[$node] ?? [] as $next) {
        if (empty($visited[$next])) {
          $dfs($next);
        }
      }
      // Post-order: a node is appended after all of its dependents.
      $order[] = $node;
    };

    if (!array_key_exists($start, $graph)) {
      return [];
    }
    $dfs($start);

    // Reverse to get topological order, then drop the start node itself.
    $order = array_reverse($order);
    return array_values(array_filter($order, static fn(string $key): bool => $key !== $start));
  }

  /**
   * Computes the effective selected value of a filter after a rebuild.
   *
   * Mirrors the run-time logic in
   * EntityReferenceFilterViewResult::valueForm() so the cascade selects exactly
   * what the widget would.
   *
   * @param array $currentValue
   *   The currently selected values (entity ids).
   * @param array $optionKeys
   *   The entity ids available after the rebuild (without the 'All' option).
   * @param bool $required
   *   Whether the exposed filter is required.
   * @param bool $multiple
   *   Whether the exposed filter accepts multiple values.
   *
   * @return array|string
   *   The selected value(s): an array for multiple filters, a string for
   *   single-value filters ('All' meaning no filtering).
   */
  public function computeEffectiveValue(array $currentValue, array $optionKeys, bool $required, bool $multiple): array|string {
    $valid = array_values(array_intersect($currentValue, $optionKeys));

    if ($multiple) {
      return $valid;
    }

    if ($valid !== []) {
      return (string) reset($valid);
    }

    if (!$required) {
      return 'All';
    }

    // Required and nothing valid: fall back to the first available option.
    return $optionKeys !== [] ? (string) reset($optionKeys) : 'All';
  }

}
