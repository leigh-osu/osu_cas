<?php

declare(strict_types=1);

$ruleset = new TwigCsFixer\Ruleset\Ruleset();

// Add a default standard.
$ruleset->addStandard(new TwigCsFixer\Standard\TwigCsFixer());

// Add some specific rules.
$ruleset->addRule(new TwigCsFixer\Rules\File\FileExtensionRule());

$config = new TwigCsFixer\Config\Config();
$config->setRuleset($ruleset);

return $config;
