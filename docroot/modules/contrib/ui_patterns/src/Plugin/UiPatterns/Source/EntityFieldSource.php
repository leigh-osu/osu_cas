<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourceTags;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'entity_field',
  label: new TranslatableMarkup('[Entity] ➜ [Field]'),
  description: new TranslatableMarkup('Data from a field'),
  context_definitions: [
    'entity' => new ContextDefinition('entity', label: new TranslatableMarkup('Entity'), required: TRUE),
  ],
  tags: [SourceTags::ContextSwitcher->value]
)]
class EntityFieldSource extends DerivableContextSourceBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['derivable_context']['#title'] = $this->t('Field');
    // When no derivable contexts exist, allow this form still be valid.
    if (isset($form['derivable_context']['#options']) && empty($form['derivable_context']['#options'])) {
      $form['derivable_context']['#required'] = FALSE;
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  protected function getSourcesTagFilter(): array {
    return [
      SourceTags::WidgetDismissible->value => FALSE,
      SourceTags::Widget->value => FALSE,
      SourceTags::Field->value => TRUE,
    ];
  }

  /**
   * {@inheritDoc}
   */
  protected function getDerivationTagFilter(): ?array {
    return [
      SourceTags::Field->value => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getChoiceSettings(string $choice_id): array {
    [, $entity_type, $bundle, $field_name] = explode(':', $choice_id);
    $derived_context = $this->getDerivedContexts($choice_id)[0] ?? [];
    /** @var \Drupal\ui_patterns\Plugin\UiPatterns\Source\FieldFormatterSource $source_field_formatter */
    $source_field_formatter = $this->sourcePluginManager->getSource(
      $this->getPropId(),
      $this->propDefinition,
      [
        'source_id' => implode(':', ['field_formatter', $entity_type, $bundle, $field_name]),
      ],
      $derived_context
    );
    return [
      'derivable_context' => $choice_id,
      $choice_id => [
        'value' => [
          'sources' => [
            [
              'source_id' => $source_field_formatter->getPluginId(),
              'source' => $source_field_formatter->defaultSettings(),
            ],
          ],
        ],
      ],
    ];
  }

}
