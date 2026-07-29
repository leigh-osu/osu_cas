<?php

declare(strict_types=1);

namespace Drupal\entityreference_filter\Service;

/**
 * Resolves reference view argument tokens into actual values.
 *
 * The reference_arguments filter option is a '/'-separated list where each
 * item is one of:
 * - !n  : URL argument number n of the view being filtered,
 * - #n  : contextual filter value number n of the view being filtered,
 * - [x] : value of the exposed filter with identifier x,
 * - any other string is passed through as a literal value.
 *
 * Several [x] tokens may share one item ("[a]+[b]"); their values are joined
 * with '+' into a single multi-value argument (so a reference view contextual
 * filter receives "2+3", matching 2 OR 3).
 */
final class FilterArgumentsResolver {

  /**
   * Resolves the configured argument string to actual argument values.
   *
   * @param string $reference_arguments
   *   Raw reference_arguments option value.
   * @param array $view_args
   *   URL arguments of the view being filtered.
   * @param array $view_context_args
   *   Contextual filter values of the view being filtered.
   * @param callable $exposed_filter_value
   *   Returns the current value of an exposed filter by identifier, or NULL.
   *   The source differs between run time and the AJAX rebuild request.
   *
   * @return array
   *   Calculated arguments to pass to the reference view.
   */
  public function resolve(string $reference_arguments, array $view_args, array $view_context_args, callable $exposed_filter_value): array {
    $arg_str = trim($reference_arguments);
    if ($arg_str === '') {
      return [];
    }

    $args = explode('/', $arg_str);

    foreach ($args as $i => $arg) {
      $arg = trim($arg);
      $first_char = mb_substr($arg, 0, 1);

      // URL argument.
      if ($first_char === '!') {
        $arg_no = (int) (mb_substr($arg, 1)) - 1;
        if ($arg_no >= 0) {
          $args[$i] = $view_args[$arg_no] ?? NULL;
        }
      }

      // Exposed filter(s) as argument. One item may hold several [identifier]
      // tokens ("[a]+[b]"); their values are glued with '+' into one
      // multi-value argument. A multi-value filter value is glued the same way.
      // @todo check multiple arguments support e.g. /#1/[a]/[b]+[c]/!2.
      if (str_contains($arg, '[')) {
        preg_match_all('/\[([^\]]+)\]/', $arg, $matches);
        $parts = [];
        foreach ($matches[1] as $identifier) {
          $value = $exposed_filter_value(trim($identifier));
          if (is_array($value)) {
            $value = implode('+', $value);
          }
          $value = (string) ($value ?? '');
          // Drop the "no selection" sentinel and empty values.
          if ($value !== '' && $value !== 'All') {
            $parts[] = $value;
          }
        }
        $args[$i] = $parts !== [] ? implode('+', $parts) : NULL;
      }

      // Contextual filter as argument.
      if ($first_char === '#' && !empty($view_context_args)) {
        $arg_no = (int) (mb_substr($arg, 1)) - 1;
        $args[$i] = $view_context_args[$arg_no] ?? NULL;
      }

      // Overwrite empty values to NULL.
      if (($args[$i] === 'All') || ($args[$i] === [])) {
        $args[$i] = NULL;
      }
    }

    return $args;
  }

  /**
   * Extracts exposed filter identifiers used as arguments.
   *
   * @param string $reference_arguments
   *   Raw reference_arguments option value.
   *
   * @return string[]
   *   Identifiers of the exposed filters referenced via the [x] token.
   */
  public function getControllingFilters(string $reference_arguments): array {
    $filters = [];
    $arg_str = trim($reference_arguments);
    if ($arg_str === '') {
      return $filters;
    }

    // @todo check multiple arguments support e.g. /#1/[a]/[b]+[c]/!2.
    if (preg_match_all('/\[([^\]]+)\]/', $arg_str, $matches)) {
      foreach ($matches[1] as $identifier) {
        $identifier = trim($identifier);
        if ($identifier !== '' && !in_array($identifier, $filters, TRUE)) {
          $filters[] = $identifier;
        }
      }
    }

    return $filters;
  }

}
