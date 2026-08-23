<?php

declare(strict_types=1);

namespace Drupal\sitewide_alert\Plugin\views\field;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to display page visibility settings for sitewide alerts.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("sitewide_alert_page_visibility")]
class SitewideAlertPageVisibility extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['all_pages_text'] = ['default' => 'All pages'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['all_pages_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text for "All pages"'),
      '#default_value' => $this->options['all_pages_text'],
      '#description' => $this->t('Text to display when the alert is shown on all pages.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // This field is computed from the entity, not a database column.
    // Do not add anything to the query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array|string {
    /** @var \Drupal\sitewide_alert\Entity\SitewideAlertInterface|null $entity */
    $entity = $this->getEntity($values);
    if (!$entity) {
      return '';
    }

    // Get pages from entity method.
    $pages = $entity->getPagesToShowOn();
    if (!is_array($pages)) {
      $pages = preg_split('/\R/', (string) $pages, -1, PREG_SPLIT_NO_EMPTY);
    }
    $pages = array_filter(array_map('trim', $pages));

    // No restrictions - All pages.
    if (empty($pages)) {
      return [
        '#plain_text' => $this->options['all_pages_text'] ?: $this->t('All pages'),
      ];
    }

    // Check for negate setting.
    $negate = $entity->hasField('limit_to_pages_negate')
      ? (bool) ($entity->get('limit_to_pages_negate')->value ?? FALSE)
      : FALSE;

    if ($negate) {
      $pages = array_map(function ($path) {
        return '<strong>' . $this->t('Except:') . '</strong> ' . Html::escape(trim($path));
      }, $pages);
    }
    else {
      $pages = array_map([Html::class, 'escape'], $pages);
    }

    return [
      '#markup' => implode('<br>', $pages),
    ];
  }

}
