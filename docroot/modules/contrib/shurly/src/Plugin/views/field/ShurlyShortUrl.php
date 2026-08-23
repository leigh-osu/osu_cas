<?php

namespace Drupal\shurly\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Field handler to present a link to the short URL entry.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("shurly_short_url")
 */
class ShurlyShortUrl extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->additional_fields['source'] = 'source';
  }

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['text'] = ['default' => '', 'translatable' => TRUE];
    $options['longshort'] = ['default' => 0, 'translatable' => FALSE];
    $options['link'] = ['default' => FALSE, 'translatable' => FALSE];

    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    unset($form['empty']);
    unset($form['hide_empty']);
    unset($form['hide_empty']);

    $form['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text to display'),
      '#default_value' => $this->options['text'],
    ];

    $form['longshort'] = [
      '#type' => 'radios',
      '#options' => [
        0 => $this->t('Output full URL including base path'),
        1 => $this->t('Output only the short path'),
      ],
      '#default_value' => $this->options['longshort'],
    ];
    $form['link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Output as link'),
      '#default_value' => $this->options['link'],
      '#description' => $this->t('Wrap output with a link to the short URL. Use <em>Output this field as a link</em> above for more complex options.'),
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $source = $this->getValue($values, 'source');

    if (!empty($this->options['text'])) {
      $text = $this->options['text'];
    }
    else {
      if ($this->options['longshort']) {
        $text = $source;
      }
      else {
        $text = rawurldecode(_surl($source, ['absolute' => TRUE]));
      }
    }

    if ($this->options['link']) {
      $text = _sl($text, $source, ['attributes' => ['target' => ['_blank']]]);
    }

    return $text;
  }

}
