<?php

/**
 * @file
 * Link general search functionalities to services module.
 */

use Drupal\Component\Serialization\Json;

/**
 * Callback for shurly/api/shorten.
 */
function shurly_service_shorten() {
  $defaults = [
    'format' => 'json',
    // 'domain' => NULL,
    'longUrl' => '',
    'shortUrl' => NULL,
    'apiKey' => NULL,
    // Function name for padded JSON.
    'func' => 'urlData',
  ];
  $input = $_GET + $defaults;

  \Drupal::moduleHandler()->loadInclude('shurly_service', 'inc', 'shurly_api_keys');
  $uid = isset($input['apiKey']) ? shurly_get_uid($input['apiKey']) : NULL;
  $account = $uid ? \Drupal::entityTypeManager()
    ->getStorage('user')
    ->load($uid) : NULL;
  $access = $account->hasPermission('create short URLs');

  if ($access) {
    // If the user doesn't have access to request a custom short URL from the
    // service, reset it to NULL.
    if (!$account->hasPermission('Request custom short URL')) {
      $input['shortUrl'] = NULL;
    }
    $data = shurly_shorten($input['longUrl'], $input['shortUrl'], $account);
  }
  else {
    $data = [
      'success' => FALSE,
      'error' => t('Invalid API key'),
    ];
  }
  shurly_service_output($data, $input);
}

/**
 * Callback for shurly/api/shorten.
 */
function shurly_service_expand() {
  $defaults = [
    'format' => 'json',
    // 'domain' => NULL,
    // 'longUrl' => '',
    'shortUrl' => '',
    // 'login' => NULL,
    'apiKey' => NULL,
    // Function name for padded JSON.
    'func' => 'urlData',
  ];
  $input = $_GET + $defaults;

  \Drupal::moduleHandler()->loadInclude('shurly_service', 'inc', 'shurly_api_keys');
  $uid = isset($input['apiKey']) ? shurly_get_uid($input['apiKey']) : NULL;
  $account = $uid ? \Drupal::entityTypeManager()
    ->getStorage('user')
    ->load($uid) : NULL;
  $access = $account->hasPermission('Expand short URLs');

  if ($access) {
    // Only works with clean URLs.
    $path = array_pop(explode('/', parse_url($input['shortUrl'], PHP_URL_PATH)));
    $data = shurly_expand($path, $account);
  }
  else {
    $data = [
      'success' => FALSE,
      'error' => t('Invalid API key'),
    ];
  }

  shurly_service_output($data, $input);

}

/**
 * Output data in a given format.
 */
function shurly_service_output($data, $input) {
  $format = $input['format'] ? $input['format'] : 'json';
  if (function_exists('shurly_service_output_' . $format)) {
    call_user_func('shurly_service_output_' . $format, $data, $input);
  }
  else {
    print t('invalid format request');
  }
}

/**
 * Output JSON data.
 */
function shurly_service_output_json($data) {
  drupal_json_output($data);
}

/**
 * Output JSON data.
 */
function shurly_service_output_jsonp($data, $input) {
  $func = $input['func'] ? $input['func'] : 'urlData';
  // Send the header.
  drupal_json_output();
  print $func . '(' . Json::encode($data) . ');';
}

/**
 * Output txt data.
 *
 * Just outputs the shortUrl attribute... won't show errors.
 */
function shurly_service_output_txt($data) {
  // Sniff the URL and figure out what to output.
  switch (arg(2)) {
    case 'expand':
      $key = 'longUrl';
      break;

    case 'shorten':
    default:
      $key = 'shortUrl';
  }
  if ($data['success']) {
    print $data[$key];
  }
  else {
    print t("ERROR: !error", ['!error' => $data['error']]);
  }
}

/**
 * Output PHP serialized data.
 */
function shurly_service_output_php($data) {
  print serialize($data);
}

/**
 * Output xml data.
 */
function shurly_service_output_xml($data) {
  $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><data />");
  if (is_array($data)) {
    foreach ($data as $key => $value) {
      $value = htmlentities($value);
      $xml->addChild($key, $value);
    }
  }
  $output = $xml->asXML();
  header('Connection: close');
  header('Content-Length: ' . strlen($output));
  header('Content-Type: text/xml');
  header('Date: ' . date('r'));
  print $output;
}
