<?php

namespace Drupal\Tests\webform_migrate\Traits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Trait for webform migration tests.
 */
trait WebformMigrateAssertionsTrait {

  /**
   * List of node properties whose value shouldn't have to be checked.
   *
   * @var string[]
   */
  protected $webformUnconcernedProperties = [
    'uuid',
  ];

  /**
   * List of webform submission properties which are irrelevant.
   *
   * @var string[]
   */
  protected $webformSubmissionUnconcernedProperties = [
    'uuid',
    'changed',
    'langcode',
    'token',
  ];

  /**
   * Assertions of the webform_3 configuration entity.
   */
  protected function assertWebform3Values() {
    $webform = $this->container->get('entity_type.manager')->getStorage('webform')->load('webform_3');
    assert($webform instanceof WebformInterface);

    $this->assertEquals([
      'uid' => 2,
      'status' => 'open',
      'langcode' => 'en',
      'dependencies' => [],
      'open' => NULL,
      'close' => NULL,
      'weight' => 0,
      'template' => FALSE,
      'archive' => FALSE,
      'id' => 'webform_3',
      'title' => 'A Webform host node with "webform_custom" type',
      'description' => NULL,
      'category' => NULL,
      'elements' => "first_page:
  '#type': webform_wizard_page
  '#title': Start
  title_as_textfield:
    '#type': textfield
    '#size': 100
    '#title': 'Title (as textfield)'
    '#description': |-
      Required, no default value, no conditions.
      If the title contains <code>fieldset</code>, then a fieldset will appear.
    '#required': true
  date:
    '#type': date
    '#title': Date
    '#description': 'Optional, no default value, website timezone.'
  email:
    '#type': email
    '#size': 20
    '#default_value': '[current-user:mail]'
    '#title_display': invisible
    '#title': E-mail
    '#description': 'No label; user email as default; long format (\"Example Name\" <name@example.com>) can be used.'
  file_attachment:
    '#type': managed_file
    '#max_filesize': !!float 2
    '#file_extensions': 'gif jpg jpeg png eps txt rtf html pdf doc docx odt ppt pptx odp xls xlsx ods xml ps'
    '#title': 'File attachment'
  fieldset:
    '#type': fieldset
    '#open': true
    '#title': Fieldset
    '#states':
      visible:
        - ':input[name=\"title_as_textfield\"]':
            value:
              pattern: fieldset
    hidden_value_5:
      '#type': hidden
      '#default_value': 'hidden value of \"hidden_value\"'
      '#title': 'Hidden value'
    select_multi_nested_5:
      '#type': select
      '#options':
        a: 'Option A'
        b: 'Option B'
        'Group A': ''
        aa: 'Option AA'
        ab: 'Option AB'
        'Group B': ''
        ba: 'Option BA'
        bb: 'Option BB'
        c: 'Option C'
      '#multiple': true
      '#title': 'Select options (multiple, nested)'
    text_markup:
      '#type': processed_text
      '#format': full_html
      '#text': 'Some <strong>text</strong> with <code>filtered_html</code>, displayed only on the form.'
      '#title': 'Text markup'
    number:
      '#type': textfield
      '#size': 20
      '#min': '10'
      '#max': '3000'
      '#unique': false
      '#title': Number
      '#description': 'An integer between 10 and 3000.'
      '#required': true
    radios:
      '#type': radios
      '#options':
        tuesday: Tuesday
        wednesday: Wednesday
        thursday: Thursday
        friday: Friday
        saturday: Saturday
        sunday: Sunday
      '#default_value': wednesday
      '#title': Radios
      '#description': 'Select the best day of the week'
      '#required': true
page_break:
  '#type': webform_wizard_page
  '#title': 'Page break'
  checkboxes:
    '#type': checkboxes
    '#options':
      AF: Afghanistan
      AX: 'Aland Islands'
      AL: Albania
      DZ: Algeria
      AS: 'American Samoa'
      AD: Andorra
      AO: Angola
      AI: Anguilla
      AQ: Antarctica
      AG: 'Antigua and Barbuda'
      AR: Argentina
      AM: Armenia
      AW: Aruba
      AU: Australia
      AT: Austria
      AZ: Azerbaijan
      ZM: Zambia
      ZW: Zimbabwe
    '#multiple': true
    '#title': Checkboxes
    '#description': 'Countries you want to travel to (only A and Z)'
  textarea:
    '#type': textarea
    '#title': Textarea
  time:
    '#type': webform_time
    '#time_format': 'H:i'
    '#step': 60
    '#title_display': inline
    '#title': Time",
      'css' => '',
      'javascript' => '',
      'settings' => [
        'page' => TRUE,
        'page_submit_path' => '',
        'page_confirm_path' => '',
        'wizard_progress_bar' => TRUE,
        'preview' => 0,
        'draft' => '0',
        'draft_auto_save' => FALSE,
        'confirmation_type' => 'page',
        'confirmation_url' => '',
        'confirmation_message' => '',
        'limit_total' => NULL,
        'limit_user' => NULL,
      ],
      'access' => [
        'create' => [
          'roles' => [
            'anonymous',
            'authenticated',
          ],
          'users' => [],
          'permissions' => [],
        ],
      ],
      'handlers' => [],
      'variants' => [],
    ], $this->getImportantEntityProperties($webform));
  }

  /**
   * Assertions of the webform_4 configuration entity.
   */
  protected function assertWebform4Values() {
    $webform = $this->container->get('entity_type.manager')->getStorage('webform')->load('webform_4');
    assert($webform instanceof WebformInterface);

    $this->assertEquals([
      'uid' => 1,
      'status' => 'open',
      'langcode' => 'en',
      'dependencies' => [],
      'open' => NULL,
      'close' => NULL,
      'weight' => 0,
      'template' => FALSE,
      'archive' => FALSE,
      'id' => 'webform_4',
      'title' => 'A Webform host node with the default "webform" type',
      'description' => NULL,
      'category' => NULL,
      'elements' => "grid:
  '#type': webform_likert
  '#questions':
    d7_sitebuild: 'How much you value Drupal 7 in terms of site building capabilities?'
    d7_theming: 'How much you value Drupal 7 in terms of theme development?'
    d7_backend: 'How much you value Drupal 7 in terms of module development?'
    d9_sitebuild: 'How much you value Drupal 8 or Drupal 9 in terms of site building capabilities?'
    d9_theming: 'How much you value Drupal 8 or Drupal 9 in terms of theme development?'
    d9_backend: 'How much you value Drupal 8 or Drupal 9 in terms of module development?'
  '#answers':
    hard: Hard
    neutral: 'Not too hard, but can be easier'
    easy: Easy
  '#default_value':
    d7_sitebuild: easy
    d7_theming: easy
    d7_backend: easy
    d9_sitebuild: easy
    d9_theming: easy
    d9_backend: easy
  '#title': Grid
  '#description': 'Please evaluate how Drupal fits your needs'",
      'css' => '',
      'javascript' => '',
      'settings' => [
        'page' => TRUE,
        'page_submit_path' => '',
        'page_confirm_path' => '',
        'wizard_progress_bar' => TRUE,
        'preview' => 0,
        'draft' => '0',
        'draft_auto_save' => FALSE,
        'confirmation_type' => 'page',
        'confirmation_url' => '',
        'confirmation_message' => 'Webform (default "webform" #1) confirmation message',
        'limit_total' => NULL,
        'limit_user' => 1,
      ],
      'access' => [
        'create' => [
          'roles' => [
            'anonymous',
            'authenticated',
          ],
          'users' => [],
          'permissions' => [],
        ],
      ],
      'handlers' => [],
      'variants' => [],
    ], $this->getImportantEntityProperties($webform));
  }

  /**
   * Assertions of the webform_5 configuration entity.
   */
  protected function assertWebform5Values() {
    $webform = $this->container->get('entity_type.manager')->getStorage('webform')->load('webform_5');
    assert($webform instanceof WebformInterface);

    $this->assertEquals([
      'uid' => 2,
      'status' => 'open',
      'langcode' => 'en',
      'dependencies' => [],
      'open' => NULL,
      'close' => NULL,
      'weight' => 0,
      'template' => FALSE,
      'archive' => FALSE,
      'id' => 'webform_5',
      'title' => 'Coffee Questionnaire',
      'description' => NULL,
      'category' => NULL,
      'elements' => "brewing_styles:
  '#type': checkboxes
  '#options':
    drip_brew: 'Drip Brew'
    pour_over: 'Pour Over'
    cold_brew: 'Cold Brew'
    espresso: Espresso
    ristretto: Ristretto
  '#multiple': true
  '#title': 'Brewing Styles'
  '#description': 'Brewing styles you like'
drinks:
  '#type': checkboxes
  '#options':
    espresso: 'Espresso (short black)'
    double_espresso: 'Double Espresso (doppio)'
    red_eye: 'Red Eye'
    black_eye: 'Black Eye'
    long_black: 'Long Black'
    macchiato: Macchiato
    long_macchiato: 'Long Macchiato'
    cortado: Cortado
    breve: Breve
    cappuccino: Cappuccino
    flat_white: 'Flat White'
    cafe_latte: 'Cafe Latte'
    mocha: Mocha
    vienna: Vienna
    affogato: Affogato
    cafe_au_lait: 'Cafe au Lait'
    iced_coffee: 'Iced Coffee'
  '#multiple': true
  '#title': Drinks
  '#description': 'Please choose the coffee styles you like'
  '#required': true",
      'css' => '',
      'javascript' => '',
      'settings' => [
        'page' => TRUE,
        'page_submit_path' => '',
        'page_confirm_path' => '',
        'wizard_progress_bar' => TRUE,
        'preview' => 0,
        'draft' => '0',
        'draft_auto_save' => FALSE,
        'confirmation_type' => 'page',
        'confirmation_url' => '',
        'confirmation_message' => '',
        'limit_total' => NULL,
        'limit_user' => 1,
      ],
      'access' => [
        'create' => [
          'roles' => ['authenticated'],
          'users' => [],
          'permissions' => [],
        ],
      ],
      'handlers' => [
        'email_1' => [
          'id' => 'email',
          'label' => 'Email 1',
          'handler_id' => 'email_1',
          'status' => TRUE,
          'conditions' => [],
          'weight' => 1,
          'settings' => [
            'to_mail' => 'info@drupal7-webform.localhost',
            'from_mail' => 'default',
            'from_name' => 'Coffee Questionnaire',
            'subject' => 'default',
            'body' => 'default',
            'html' => FALSE,
            'attachments' => FALSE,
            'excluded_elements' => [],
            'states' => ['completed'],
            'to_options' => [],
            'cc_mail' => '',
            'cc_options' => [],
            'bcc_mail' => '',
            'bcc_options' => [],
            'from_options' => [],
            'ignore_access' => FALSE,
            'exclude_empty' => TRUE,
            'exclude_empty_checkbox' => FALSE,
            'exclude_attachments' => FALSE,
            'twig' => FALSE,
            'debug' => FALSE,
            'reply_to' => '',
            'return_path' => '',
            'sender_mail' => '',
            'sender_name' => '',
            'theme_name' => '',
            'parameters' => [],
          ],
          'notes' => '',
        ],
      ],
      'variants' => [],
    ], $this->getImportantEntityProperties($webform));
  }

  /**
   * Assertions of the webform_submission 1 content entity.
   */
  protected function assertWebformSubmission1Values() {
    $webform_submission = $this->container->get('entity_type.manager')->getStorage('webform_submission')->load(1);
    assert($webform_submission instanceof WebformSubmissionInterface);

    $this->assertEquals([
      'uid' => [['target_id' => 0]],
      'created' => [['value' => 1600263958]],
      'serial' => [['value' => 1]],
      'sid' => [['value' => 1]],
      'uri' => [['value' => '/node/3']],
      'completed' => [['value' => 1600263958]],
      'in_draft' => [['value' => 0]],
      'current_page' => [],
      'remote_addr' => [['value' => '::1']],
      'webform_id' => [['target_id' => 'webform_3']],
      'entity_type' => [['value' => 'node']],
      'entity_id' => [['value' => 3]],
      'locked' => [['value' => 0]],
      'sticky' => [['value' => 0]],
      'notes' => [],
    ], $this->getImportantEntityProperties($webform_submission));

    // Webform submission data.
    $data = $webform_submission->getData();
    $this->assertEquals([
      'checkboxes' => [
        'AF',
        'AL',
        'DZ',
        'AQ',
        'AG',
        'AR',
        'AU',
        'ZW',
      ],
      'date' => '2018-10-23',
      'file_attachment' => '1',
      'number' => '11',
      'radios' => 'sunday',
      'textarea' => 'Why I cannot choose Monday as my favorite weekday?',
      'time' => '18:01:00',
      'title_as_textfield' => 'Example submission from an anonymous user',
    ], $data);
  }

  /**
   * Assertions of the webform_submission 2 content entity.
   */
  protected function assertWebformSubmission2Values() {
    $webform_submission = $this->container->get('entity_type.manager')->getStorage('webform_submission')->load(2);
    assert($webform_submission instanceof WebformSubmissionInterface);

    $this->assertEquals([
      'uid' => [['target_id' => 0]],
      'created' => [['value' => 1600264081]],
      'serial' => [['value' => 2]],
      'sid' => [['value' => 2]],
      'uri' => [['value' => '/node/3']],
      'completed' => [['value' => 1600264081]],
      'in_draft' => [['value' => 0]],
      'current_page' => [],
      'remote_addr' => [['value' => '::1']],
      'webform_id' => [['target_id' => 'webform_3']],
      'entity_type' => [['value' => 'node']],
      'entity_id' => [['value' => 3]],
      'locked' => [['value' => 0]],
      'sticky' => [['value' => 0]],
      'notes' => [],
    ], $this->getImportantEntityProperties($webform_submission));

    // Webform submission data.
    $data = $webform_submission->getData();
    $this->assertEquals([
      'checkboxes' => [
        0 => 'AQ',
      ],
      'email' => 'me@somewhere.else',
      'file_attachment' => '2',
      'number' => '20',
      'radios' => 'friday',
      'title_as_textfield' => 'Text with fieldset',
      'hidden_value_5' => 'hidden value of "hidden_value"',
      'select_multi_nested_5' => [
        'a',
        'ba',
        'bb',
      ],
    ], $data);
  }

  /**
   * Assertions of the webform_submission 3 content entity.
   */
  protected function assertWebformSubmission3Values() {
    $webform_submission = $this->container->get('entity_type.manager')->getStorage('webform_submission')->load(3);
    assert($webform_submission instanceof WebformSubmissionInterface);

    $this->assertEquals([
      'uid' => [['target_id' => 0]],
      'created' => [['value' => 1600327212]],
      'serial' => [['value' => 3]],
      'sid' => [['value' => 3]],
      'uri' => [['value' => '/node/3']],
      'completed' => [['value' => 1600327212]],
      'in_draft' => [['value' => 0]],
      'current_page' => [],
      'remote_addr' => [['value' => '172.16.0.166']],
      'webform_id' => [['target_id' => 'webform_3']],
      'entity_type' => [['value' => 'node']],
      'entity_id' => [['value' => 3]],
      'locked' => [['value' => 0]],
      'sticky' => [['value' => 0]],
      'notes' => [],
    ], $this->getImportantEntityProperties($webform_submission));

    // Webform submission data.
    $data = $webform_submission->getData();
    $this->assertEquals([
      'checkboxes' => [
        'AG',
        'AW',
      ],
      'date' => '2020-09-17',
      'email' => 'another@email.address',
      'file_attachment' => '3',
      'number' => '244',
      'radios' => 'sunday',
      'textarea' => 'FooBarBaz',
      'time' => '09:22:00',
      'title_as_textfield' => 'Submission from an another anonymous user',
    ], $data);
  }

  /**
   * Assertions of the webform_submission 4 content entity.
   */
  protected function assertWebformSubmission4Values() {
    $webform_submission = $this->container->get('entity_type.manager')->getStorage('webform_submission')->load(4);
    assert($webform_submission instanceof WebformSubmissionInterface);

    $this->assertEquals([
      'uid' => [['target_id' => 0]],
      'created' => [['value' => 1600327251]],
      'serial' => [['value' => 1]],
      'sid' => [['value' => 4]],
      'uri' => [['value' => '/node/4']],
      'completed' => [['value' => 1600327251]],
      'in_draft' => [['value' => 0]],
      'current_page' => [],
      'remote_addr' => [['value' => '172.16.0.166']],
      'webform_id' => [['target_id' => 'webform_4']],
      'entity_type' => [['value' => 'node']],
      'entity_id' => [['value' => 4]],
      'locked' => [['value' => 0]],
      'sticky' => [['value' => 0]],
      'notes' => [],
    ], $this->getImportantEntityProperties($webform_submission));

    // Webform submission data.
    $data = $webform_submission->getData();
    $this->assertEquals([
      'grid' => [
        'd7_sitebuild' => 'neutral',
        'd7_theming' => 'easy',
        'd7_backend' => 'neutral',
        'd9_sitebuild' => 'neutral',
        'd9_theming' => 'easy',
        'd9_backend' => 'easy',
      ],
    ], $data);
  }

  /**
   * Assertions of the webform_submission 5 content entity.
   */
  protected function assertWebformSubmission5Values() {
    $webform_submission = $this->container->get('entity_type.manager')->getStorage('webform_submission')->load(5);
    assert($webform_submission instanceof WebformSubmissionInterface);

    $this->assertEquals([
      'uid' => [['target_id' => 3]],
      'created' => [['value' => 1600327319]],
      'serial' => [['value' => 1]],
      'sid' => [['value' => 5]],
      'uri' => [['value' => '/node/5']],
      'completed' => [['value' => 1600327319]],
      'in_draft' => [['value' => 0]],
      'current_page' => [],
      'remote_addr' => [['value' => '172.16.0.166']],
      'webform_id' => [['target_id' => 'webform_5']],
      'entity_type' => [['value' => 'node']],
      'entity_id' => [['value' => 5]],
      'locked' => [['value' => 0]],
      'sticky' => [['value' => 0]],
      'notes' => [],
    ], $this->getImportantEntityProperties($webform_submission));

    // Webform submission data.
    $this->assertEquals([
      'brewing_styles' => [
        'espresso',
        'ristretto',
      ],
      'drinks' => [
        'espresso',
        'double_espresso',
        'macchiato',
        'cappuccino',
        'mocha',
        'vienna',
      ],
    ], $webform_submission->getData());
  }

  /**
   * Assertions of the webform_submission 6 content entity.
   */
  protected function assertWebformSubmission6Values() {
    $webform_submission = $this->container->get('entity_type.manager')->getStorage('webform_submission')->load(6);
    assert($webform_submission instanceof WebformSubmissionInterface);

    $this->assertEquals([
      'uid' => [['target_id' => 3]],
      'created' => [['value' => 1600327404]],
      'serial' => [['value' => 2]],
      'sid' => [['value' => 6]],
      'uri' => [['value' => '/node/4']],
      'completed' => [['value' => 1600327404]],
      'in_draft' => [['value' => 0]],
      'current_page' => [],
      'remote_addr' => [['value' => '172.16.0.166']],
      'webform_id' => [['target_id' => 'webform_4']],
      'entity_type' => [['value' => 'node']],
      'entity_id' => [['value' => 4]],
      'locked' => [['value' => 0]],
      'sticky' => [['value' => 0]],
      'notes' => [],
    ], $this->getImportantEntityProperties($webform_submission));

    // Webform submission data.
    $data = $webform_submission->getData();
    $this->assertEquals([
      'grid' => [
        'd7_sitebuild' => 'neutral',
        'd7_theming' => 'easy',
        'd7_backend' => 'neutral',
        'd9_sitebuild' => 'neutral',
        // No answer for "d9_theming".
        'd9_backend' => 'hard',
      ],
    ], $data);
  }

  /**
   * Assertions of the webform_submission 7 content entity.
   */
  protected function assertWebformSubmission7Values() {
    $webform_submission = $this->container->get('entity_type.manager')->getStorage('webform_submission')->load(7);
    assert($webform_submission instanceof WebformSubmissionInterface);

    $this->assertEquals([
      'uid' => [['target_id' => 3]],
      'created' => [['value' => 1600327527]],
      'serial' => [['value' => 4]],
      'sid' => [['value' => 7]],
      'uri' => [['value' => '/node/3']],
      'completed' => [['value' => 1600327527]],
      'in_draft' => [['value' => 0]],
      'current_page' => [],
      'remote_addr' => [['value' => '172.16.0.166']],
      'webform_id' => [['target_id' => 'webform_3']],
      'entity_type' => [['value' => 'node']],
      'entity_id' => [['value' => 3]],
      'locked' => [['value' => 0]],
      'sticky' => [['value' => 0]],
      'notes' => [],
    ], $this->getImportantEntityProperties($webform_submission));

    // Webform submission data.
    $this->assertEquals([
      'checkboxes' => [
        'AQ',
        'AW',
      ],
      'date' => '2020-01-23',
      'email' => 'user@drupal7-webform.localhost',
      'number' => '45',
      'radios' => 'wednesday',
      'textarea' => 'No file was attached.',
      'time' => '20:04:00',
      'title_as_textfield' => "User's submission",
    ], $webform_submission->getData());
  }

  /**
   * Assertions of the webform_submission 8 content entity.
   */
  protected function assertWebformSubmission8Values() {
    $webform_submission = $this->container->get('entity_type.manager')->getStorage('webform_submission')->load(8);
    assert($webform_submission instanceof WebformSubmissionInterface);

    $this->assertEquals([
      'uid' => [['target_id' => 0]],
      'created' => [['value' => 1600328002]],
      'serial' => [['value' => 3]],
      'sid' => [['value' => 8]],
      'uri' => [['value' => '/node/4']],
      'completed' => [['value' => 1600328002]],
      'in_draft' => [['value' => 0]],
      'current_page' => [],
      'remote_addr' => [['value' => '::1']],
      'webform_id' => [['target_id' => 'webform_4']],
      'entity_type' => [['value' => 'node']],
      'entity_id' => [['value' => 4]],
      'locked' => [['value' => 0]],
      'sticky' => [['value' => 0]],
      'notes' => [],
    ], $this->getImportantEntityProperties($webform_submission));

    // Webform submission data.
    $this->assertEquals([
      'grid' => [
        'd7_sitebuild' => 'easy',
        'd7_theming' => 'neutral',
        'd7_backend' => 'hard',
        'd9_sitebuild' => 'hard',
        'd9_theming' => 'neutral',
        'd9_backend' => 'hard',
      ],
    ], $webform_submission->getData());
  }

  /**
   * Assertions of the webform_submission 9 content entity.
   */
  protected function assertWebformSubmission9Values() {
    $webform_submission = $this->container->get('entity_type.manager')->getStorage('webform_submission')->load(9);
    assert($webform_submission instanceof WebformSubmissionInterface);

    $this->assertEquals([
      'uid' => [['target_id' => 2]],
      'created' => [['value' => 1600328929]],
      'serial' => [['value' => 4]],
      'sid' => [['value' => 9]],
      'uri' => [['value' => '/node/4']],
      'completed' => [['value' => 1600328929]],
      'in_draft' => [['value' => 0]],
      'current_page' => [],
      'remote_addr' => [['value' => '127.0.0.1']],
      'webform_id' => [['target_id' => 'webform_4']],
      'entity_type' => [['value' => 'node']],
      'entity_id' => [['value' => 4]],
      'locked' => [['value' => 0]],
      'sticky' => [['value' => 0]],
      'notes' => [],
    ], $this->getImportantEntityProperties($webform_submission));

    // Webform submission data.
    $this->assertEquals([
      'grid' => [
        'd7_sitebuild' => 'easy',
        'd7_theming' => 'easy',
        'd7_backend' => 'neutral',
        'd9_sitebuild' => 'easy',
        'd9_theming' => 'easy',
        'd9_backend' => 'easy',
      ],
    ], $webform_submission->getData());
  }

  /**
   * Assertions of the webform_submission 10 content entity.
   */
  protected function assertWebformSubmission10Values() {
    $webform_submission = $this->container->get('entity_type.manager')->getStorage('webform_submission')->load(10);
    assert($webform_submission instanceof WebformSubmissionInterface);

    $this->assertEquals([
      'uid' => [['target_id' => 1]],
      'created' => [['value' => 1600335185]],
      'serial' => [['value' => 2]],
      'sid' => [['value' => 10]],
      'uri' => [['value' => '/node/5']],
      'completed' => [['value' => 1600335185]],
      'in_draft' => [['value' => 0]],
      'current_page' => [],
      'remote_addr' => [['value' => '::1']],
      'webform_id' => [['target_id' => 'webform_5']],
      'entity_type' => [['value' => 'node']],
      'entity_id' => [['value' => 5]],
      'locked' => [['value' => 0]],
      'sticky' => [['value' => 0]],
      'notes' => [],
    ], $this->getImportantEntityProperties($webform_submission));

    // Webform submission data.
    $data = $webform_submission->getData();
    $this->assertEquals([
      'brewing_styles' => [
        'pour_over',
        'espresso',
      ],
      'drinks' => [
        'macchiato',
        'long_macchiato',
        'cortado',
        'affogato',
      ],
    ], $data);
  }

  /**
   * Filters out unconcerned properties from an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity instance.
   *
   * @return array
   *   The important entity property values as array.
   */
  protected function getImportantEntityProperties(EntityInterface $entity) {
    $entity_type_id = $entity->getEntityTypeId();
    $exploded = explode('_', $entity_type_id);
    $prop_prefix = count($exploded) > 1
      ? $exploded[0] . implode('', array_map('ucfirst', array_slice($exploded, 1)))
      : $entity_type_id;
    $property_filter_preset_property = "{$prop_prefix}UnconcernedProperties";
    $entity_array = $entity->toArray();
    $unconcerned_properties = property_exists(get_class($this), $property_filter_preset_property)
      ? $this->$property_filter_preset_property
      : [
        'uuid',
        'langcode',
        'dependencies',
        '_core',
      ];

    foreach ($unconcerned_properties as $item) {
      unset($entity_array[$item]);
    }

    return $entity_array;
  }

}
