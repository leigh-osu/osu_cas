<?php

/**
 * @file
 * Shurly Service Views data include file.
 */

function shurly_service_views_data() {

  // Basic table information.
  $data['shurly_keys']['table']['group'] = t('Shurly Service');

  // Create a join association with the shurly table.
  $data['shurly_keys']['table']['join'] = [
    'shurly' => [
      'left_field' => 'uid',
      'field' => 'uid',
    ],
  ];

  // UID.
  $data['shurly_keys']['uid'] = [
    'title' => t('User ID'),
    'help' => t('ID of user associated with the Shurly API Key.'),
    'field' => [
      'handler' => 'views_handler_field_user',
      'click sortable' => TRUE,
    ],
    'argument' => [
      'handler' => 'views_handler_argument_user_uid',
      // Display this field in the summary.
      'name field' => 'name',
    ],
    'filter' => [
      'title' => t('Name'),
      'handler' => 'views_handler_filter_user_name',
    ],
    'sort' => [
      'handler' => 'views_handler_sort',
    ],
    'relationship' => [
      'handler' => 'views_handler_relationship',
      'base' => 'users',
      'base field' => 'uid',
      'label' => t('user'),
    ],
  ];

  // API Key.
  $data['shurly_keys']['apikey'] = [
    'title' => t('API Key'),
    'help' => t('The API key associated with the user.'),
    'field' => [
      'handler' => 'views_handler_field',
    ],
    'filter' => [
      'handler' => 'views_handler_filter_string',
    ],
    'sort' => [
      'handler' => 'views_handler_sort',
    ],
    'argument' => [
      'handler' => 'views_handler_argument_string',
    ],
  ];

  return $data;

}
