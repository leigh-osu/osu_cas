<?php

/**
 * @file
 */

define('API_VERSION', 'A');

/**
 * Generate a new api key for this user and put it into the database.
 *
 * @return
 *   new API key or FALSE on database insert error.
 */
function shurly_generate_new_api_key($uid) {
  // Be sure that the new key is unique.
  do {
    $key = md5(uniqid(rand(), TRUE)) . '_' . API_VERSION;
  } while (shurly_get_uid($key));
  \Drupal::database()
    ->query('DELETE FROM {shurly_keys} WHERE uid = :uid', ['uid' => $uid]);
  $result = \Drupal::database()
    ->query("INSERT INTO {shurly_keys} (uid, apikey) VALUES (:uid, :key)", [
      'uid' => $uid,
      'key' => $key,
    ]);
  return $result ? $key : FALSE;
}

function shurly_get_uid($key) {
  return \Drupal::database()
    ->query("SELECT uid FROM {shurly_keys} WHERE apikey = :key", ['key' => $key])
    ->fetchField();
}

function shurly_get_api_key($uid) {
  return \Drupal::database()
    ->query("SELECT apikey FROM {shurly_keys} WHERE uid = :uid", ['uid' => $uid])
    ->fetchField();
}
