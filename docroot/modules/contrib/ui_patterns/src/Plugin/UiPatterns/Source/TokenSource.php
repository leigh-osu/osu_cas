<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Plugin\UiPatterns\Source;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;

use function Symfony\Component\String\u;

/**
 * Plugin implementation of the source.
 *
 * Slot is explicitly added to prop_types to allow getPropValue
 * to return a renderable array in case of slot prop type.
 */
#[Source(
  id: 'token',
  label: new TranslatableMarkup('Token'),
  description: new TranslatableMarkup('Text with placeholder variables, replaced before display.'),
  prop_types: ['slot', 'string', 'url'],
  context_definitions: [
    'entity' => new ContextDefinition('entity', label: new TranslatableMarkup('Entity'), required: FALSE),
  ]
)]
class TokenSource extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'value' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $value = $this->getSetting('value') ?? NULL;
    if (!$value || !is_string($value)) {
      return [];
    }

    return [
      u(strip_tags($value))->truncate(20, '...', FALSE),
    ];
  }

  /**
   * Determines if we are in preview mode.
   *
   * @return bool
   *   TRUE if in preview mode, FALSE otherwise.
   */
  protected function hasSampleEntity(): bool {
    $tokenData = $this->getTokenData();
    foreach ($tokenData as $tokenEntity) {
      if ($tokenEntity instanceof EntityInterface && $tokenEntity->id() === NULL) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $value = $this->getSetting('value') ?? '';
    $isSlot = ($this->propDefinition['ui_patterns']['type_definition']->getPluginId() === 'slot');
    $has_sample_entity = $this->hasSampleEntity();
    $bubbleable_metadata = new BubbleableMetadata();
    // For a slot, escape the template (the `value` setting) so markup typed
    // into this config setting renders as text; the result is emitted as
    // #markup below, so Drupal core still runs Xss::filterAdmin over the
    // token output. For a string/URL prop, the raw result is returned and
    // Twig autoescapes it at render.
    if ($isSlot && is_string($value)) {
      $value = Html::escape($value);
    }
    try {
      $value = $this->replaceTokens($value, $isSlot, $bubbleable_metadata);
    }
    catch (\Exception $e) {
      if (!$has_sample_entity) {
        throw $e;
      }
      // We are probably in a preview system and there can
      // be side effects.
      $value = NULL;
    }
    if (empty($value)) {
      return $isSlot ? [] : '';
    }
    if ($isSlot) {
      // A plain string in #markup (not Markup::create()) lets Drupal core
      // run Xss::filterAdmin over the token output: <script>, <iframe> and
      // event handlers are stripped; <a href> and <img src> survive.
      $build = [
        '#markup' => $value,
      ];
      if (!$has_sample_entity) {
        $bubbleable_metadata->applyTo($build);
      }
      return $build;
    }
    // A string/URL prop stays a plain untrusted string: Twig autoescapes
    // it at render.
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['value'] = [
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('value'),
      // Tokens always start with a [ and end with a ].
      // '#pattern' => '^\[.+\]$',.
    ];
    $this->addRequired($form['value']);
    $this->addTokenTreeLink($form, 'help');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    if ($this->moduleHandler->moduleExists('token')) {
      static::mergeConfigDependencies($dependencies, ['module' => ['token']]);
    }
    return $dependencies;
  }

}
