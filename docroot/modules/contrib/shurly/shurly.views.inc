<?php

/**
 * @file
 * Shurly Views data include file.
 */

function shurly_views_data() {

  $data = [];

  $data['shurly'] = [];

  $data['users'] = [];

  $data['users_data'] = [];

  $data['users_field_data'] = [];

  $data['shurly']['table']['group'] = t('Shurly');

  $data['shurly']['table']['provider'] = 'shurly';

  // Not sure if this is the best way, but it allows access to the user data.
  $data['shurly']['table']['entity type'] = 'user';

  $data['shurly']['table']['entity revision'] = FALSE;

  $data['shurly']['table']['base'] = [
    'field' => 'rid',
    'title' => t('Short URLs'),
    'help' => t('Shurly shortened URLs.'),
    'weight' => 10,
  ];

  $data['users']['table']['join']['shurly'] = [
    'left_field' => 'uid',
    'field' => 'uid',
  ];

  $data['users_data']['table']['join']['shurly'] = [
    'left_field' => 'uid',
    'field' => 'uid',
  ];

  $data['users_field_data']['table']['join']['shurly'] = [
    'left_field' => 'uid',
    'field' => 'uid',
  ];

  // User ID.
  $data['shurly']['uid'] = [
    'title' => t('User ID'),
    'help' => t('ID of user who created the link.'),
    'field' => [
      'id' => 'numeric',
      'click sortable' => TRUE,
    ],
    'argument' => [
      'id' => 'numeric',
      'name field' => 'name',
    ],
    'filter' => [
      'title' => t('Name'),
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'relationship' => [
      'id' => 'standard',
      'base' => 'users',
      'base field' => 'uid',
      'label' => t('user'),
    ],
  ];

  // Uid.
  $data['shurly']['uid_current'] = [
    'real field' => 'uid',
    'title' => t('Current user'),
    'help' => t('Filter the view to the currently logged in user.'),
    'filter' => [
      'id' => 'user_current',
      'type' => 'yes-no',
    ],
  ];

  // Shurly Source - Short URL.
  $data['shurly']['source'] = [
    'title' => t('Short URL'),
    'help' => t('The source URL which generates a redirect'),
    'field' => [
      'id' => 'shurly_short_url',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  // Shurly Desination - Long URL.
  $data['shurly']['destination'] = [
    'title' => t('Long URL'),
    'help' => t('The destination URL'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  // Link created timestamp.
  $data['shurly']['created'] = [
    'title' => t('Created date'),
    'help' => t('The date/time the link was created.'),
    'field' => [
      'id' => 'date',
    ],
    'sort' => [
      'id' => 'date',
    ],
    'filter' => [
      'id' => 'date',
    ],
    'argument' => [
      'id' => 'date',
    ],
  ];

  // Count.
  $data['shurly']['count'] = [
    'title' => t('Clicks'),
    'help' => t('Number of times this link has been followed.'),
    'field' => [
      'id' => 'numeric',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'numeric',
    ],

  ];

  // Link last used timestamp.
  $data['shurly']['last_used'] = [
    'title' => t('Last used date'),
    'help' => t('The last date/time the link was used.'),
    'field' => [
      'id' => 'date',
    ],
    'sort' => [
      'id' => 'date',
    ],
    'filter' => [
      'id' => 'date',
    ],
    'argument' => [
      'id' => 'date',
    ],
  ];

  // Boolean for custom entry.
  $data['shurly']['custom'] = [
    'title' => t('Custom URL'),
    'help' => t('Boolean flag set for custom short URLs entered by user.'),
    'field' => [
      'id' => 'boolean',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'boolean',
      'label' => t('Custom'),
      'type' => 'yes-no',
    ],
    'argument' => [
      'id' => 'boolean',
    ],
  ];

  // Boolean for active link.
  $data['shurly']['active'] = [
    'title' => t('URL active'),
    'help' => t('Is this link active or has it been deactivated?'),
    'field' => [
      'id' => 'boolean',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'boolean',
      'label' => t('Custom'),
      'type' => 'yes-no',
    ],
    'argument' => [
      'id' => 'boolean',
    ],
  ];

  $data['shurly']['link_edit'] = [
    'title' => t('Edit URL'),
    'help' => t('Link to edit the URL'),
    'field' => [
      'id' => 'shurly_link_edit',
    ],
  ];

  $data['shurly']['link_deactivate'] = [
    'title' => t('Deactivate URL'),
    'help' => t('Link to deactivate the URL'),
    'field' => [
      'id' => 'shurly_link_deactivate',
    ],
  ];

  return $data;
}
