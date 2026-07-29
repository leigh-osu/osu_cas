<?php

declare(strict_types=1);

namespace Drupal\rules\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Hook implementations used to provide help.
 */
final class RulesHelpHooks {
  use StringTranslationTrait;

  /**
   * Constructs a new RulesHelpHooks service.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.rules':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>';
        // cspell:ignore gitbooks
        $output .= $this->t('The Rules module allows site administrators to define conditionally executed actions based on occurring events (ECA-rules). For more information, see the <a href=":url1" target="_blank">online documentation for the Rules module</a> and the current <a href=":url2" target="_blank">Rules documentation for Drupal 8 on Gitbooks</a>.', [
          ':url1' => 'https://www.drupal.org/project/rules',
          ':url2' => 'https://thefubhy.gitbooks.io/rules/content/',
        ]);
        $output .= '</p>';
        $output .= '<h3>' . $this->t('Uses') . '</h3>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Reaction rules') . '</dt>';
        $output .= '<dd>' . $this->t('Reaction rules associate one or more reactions to one or more specific site events. Execution of a reaction rule actions can optionally be tied to one or more conditions. To list and update existing reaction rules and to create a new one, visit the <a href=":url">reaction rules overview page</a>.', [':url' => Url::fromRoute('entity.rules_reaction_rule.collection')->toString()]) . '</dd>';
        $output .= '<dt>' . $this->t('Components') . '</dt>';
        $output .= '<dd>' . $this->t('Rule components allows to define reusable combined actions which can optionally be tied to one or more conditions. Components are usable as actions in reaction rules or in other components. To list and update existing rule components and to create a new one, visit the <a href=":url">components overview pages</a>.', [':url' => Url::fromRoute('entity.rules_component.collection')->toString()]) . '</dd>';
        $output .= '<dt>' . $this->t('General settings') . '</dt>';
        $output .= '<dd>' . $this->t('The Rules modules allows to set global settings settings, such as logging. Visit the <a href=":url">rules settings page</a> to view and update current settings.', [':url' => Url::fromRoute('rules.settings')->toString()]) . '</dd>';
        $output .= '</dl>';
        return $output;
    }
    return NULL;
  }

}
