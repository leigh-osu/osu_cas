<?php

declare(strict_types=1);

use drupol\PhpCsFixerConfigsDrupal\Config\Drupal8;

$finder = PhpCsFixer\Finder::create()
  ->name('*.module')
  ->name('*.inc')
  ->name('*.install')
  ->name('*.profile')
  ->name('*.theme')
  ->notPath('*.md')
  ->notPath('*.yml');

$config = new Drupal8();

$rules = $config->getRules();
$config->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
$config->setFinder($finder);

// Already applied before the adoption of the Drupal8 ruleset.
$rules['declare_strict_types'] = TRUE;
$rules['blank_line_after_opening_tag'] = TRUE;
$rules['ordered_imports'] = TRUE;

// Disables because change too much lines, break too much MRs.
// @todo Let's adopt them later.
$rules['ordered_class_elements'] = FALSE;
$rules['native_function_invocation'] = FALSE;

// Altered for phpcs compatibility.
$rules['blank_line_before_statement']['statements'] = ['case', 'declare', 'default'];

// Altered for phpstan compatibility.
$rules['return_assignment'] = ['skip_named_var_tags' => TRUE];

$config->setRules($rules);

return $config;
