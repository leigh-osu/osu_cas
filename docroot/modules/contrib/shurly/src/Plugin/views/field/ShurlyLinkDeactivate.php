<?php

namespace Drupal\shurly\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Field handler to provide a link to the short URL entry.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("shurly_link_deactivate")
 */
class ShurlyLinkDeactivate extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->additional_fields['uid'] = 'uid';
    $this->additional_fields['active'] = 'active';
    $this->additional_fields['rid'] = 'rid';
  }

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['text'] = ['default' => '', 'translatable' => TRUE];

    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text to display'),
      '#default_value' => $this->options['text'],
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
    $user = \Drupal::currentUser();
    $uid = $this->getValue($values, 'uid');
    $active = $this->getValue($values, 'active');
    if (!$active) {
      return $this->t('deactivated');
    }
    // Only allow the user to view the link if they can actually delete.
    if (\Drupal::currentUser()
      ->hasPermission('administer short URLs') || (\Drupal::currentUser()
        ->hasPermission('deactivate own URLs') && $uid == $user->id())) {
      $text = !empty($this->options['text']) ? $this->options['text'] : $this->t('deactivate');
      $rid = $values->rid;
      return link::fromTextAndUrl($text, Url::fromUri('internal:/shurly/deactivate/' . $rid, [
        'query' => \Drupal::service('redirect.destination')
          ->getAsArray(),
      ]))->toString();
    }
  }

}
