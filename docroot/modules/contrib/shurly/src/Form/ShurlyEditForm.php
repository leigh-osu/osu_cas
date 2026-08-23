<?php

namespace Drupal\shurly\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * ShurlyActionsForm.
 */
class ShurlyEditForm extends FormBase {

  /**
   * Access check for editing a short url.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param $rid
   */
  public function access(AccountInterface $account, $rid) {
    if (is_numeric($rid)) {
      $row = \Drupal::database()
        ->query('SELECT uid, source, destination FROM {shurly} WHERE rid = :rid', ['rid' => $rid])
        ->fetchObject();
      // If there's a row, and either the user is an admin, or they've got
      // permission to create and they own this URL, then let them access.
      return AccessResult::allowedIf($account->hasPermission('administer short URLs') || $account->hasPermission('edit own URLs') && $row->uid == $account->id());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shurly_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rid = NULL) {
    $shurly_link = \Drupal::database()
      ->query('SELECT * FROM {shurly} WHERE rid = :rid', ['rid' => $rid])
      ->fetchAllAssoc('rid');
    $shurly_history = \Drupal::database()
      ->query('SELECT * FROM {shurly_history} WHERE rid = :rid', ['rid' => $rid])
      ->fetchAll();
    $shurly_history_count = count($shurly_history);

    // Store the current values.
    $form_state->setStorage([
      'shurly' => [
        'rid' => $rid,
        'source' => $shurly_link[$rid]->source,
        'count' => $shurly_link[$rid]->count,
        'destination' => urldecode($shurly_link[$rid]->destination),
      ],
    ]);

    if ($shurly_history) {
      $form['history'] = [
        '#prefix' => '<table>',
        '#suffix' => '</table>',
        '#tree' => TRUE,
      ];

      $form['history']['header'] = [
        '#markup' => '<thead>
        <tr>
          <th>' . $this->t('Source') . '</th>
          <th>' . $this->t('Changed') . '</th>
        </tr>
      </thead>',
      ];

      for ($i = 0; $i < $shurly_history_count; $i++) {
        $form['history']['row_' . $i] = [
          '#prefix' => '<tr class="' . ($i % 2 ? "odd" : "even") . '">',
          '#suffix' => '</tr>',
        ];

        $form['history']['row_' . $i]['destination'] = [
          '#prefix' => '<td>',
          '#suffix' => '</td>',
          '#markup' => link::fromTextAndUrl(Unicode::truncate($shurly_history[$i]->destination, 30), Url::fromUri($shurly_history[$i]->destination, ['attributes' => ['target' => ['_blank']]]))
            ->toString(),
        ];

        $form['history']['row_' . $i]['last_date'] = [
          '#prefix' => '<td>',
          '#suffix' => '</td>',
          '#markup' => \Drupal::service('date.formatter')->format($shurly_history[$i]->last_date, 'short'),
        ];
      }
    }

    $form['source'] = [
      '#type' => 'textfield',
      '#title' => 'source',
      '#value' => $shurly_link[$rid]->source,
      '#disabled' => TRUE,
    ];

    $form['new_destination'] = [
      '#type' => 'textfield',
      '#title' => 'destination',
      '#maxlength' => 2048,
      '#default_value' => $shurly_link[$rid]->destination,
    ];

    $form['rid'] = [
      '#type' => 'hidden',
      '#value' => $rid,
    ];

    $form['count'] = [
      '#type' => 'hidden',
      '#value' => $shurly_link[$rid]->count,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!shurly_validate_long($form_state->getValue('new_destination'))) {
      $form_state->setErrorByName('long_url', $this->t('Invalid URL'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = &$form_state->getStorage();

    $rid = $storage['shurly']['rid'];
    $new_destination = $form_state->getValue('new_destination');

    $request_time = \Drupal::time()->getRequestTime();

    // Get the most recent history for this redirect (if exists)
    $previous_history = \Drupal::database()
      ->query('SELECT * FROM {shurly_history} WHERE rid = :rid ORDER BY vid DESC LIMIT 1', ['rid' => $rid])
      ->fetchAssoc();

    // Still to add: vid, count
    // First save the current data into the history table for future reference.
    \Drupal::database()
      ->query('INSERT INTO {shurly_history} (rid, vid, source, destination, last_date, count) VALUES (:rid, :vid, :source, :destination, :last_date, :count)', [
        ':rid' => $rid,
        ':vid' => (isset($previous_history['vid']) ? $previous_history['vid'] + 1 : 0),
        ':source' => $storage['shurly']['source'],
        ':destination' => $storage['shurly']['destination'],
        ':last_date' => $request_time,
        ':count' => $storage['shurly']['count'],
      ]);

    // Update access information on this row.
    \Drupal::database()
      ->query('UPDATE {shurly} SET destination = :new_destination, count = :reset_count WHERE rid = :rid', [
        ':new_destination' => $new_destination,
        ':reset_count' => 0,
        ':rid' => $rid,
      ]);

    unset($storage['shurly']);

    $this->messenger()->addStatus($this->t('URL has been edited.'));
  }

}
