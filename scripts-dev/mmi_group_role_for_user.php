<?php

/**
 * @file
 * Give one user a group role on every MMI group (adds the membership where
 * there is none).
 *
 * Usage: drush scr scripts-dev/mmi_group_role_for_user.php <uid> <group role id> [apply]
 *   e.g. drush scr scripts-dev/mmi_group_role_for_user.php 24991 basic_group-content_author apply
 */

[$uid, $role] = [$extra[0] ?? NULL, $extra[1] ?? NULL];
$apply = in_array('apply', $extra ?? [], TRUE);
$etm = \Drupal::entityTypeManager();
$account = $uid ? $etm->getStorage('user')->load($uid) : NULL;
if (!$account || !$role || !$etm->getStorage('group_role')->load($role)) {
  print "Usage: <uid> <group role id> [apply] — unknown user or role.\n";
  return;
}
$gids = \Drupal::database()->query("SELECT destid1 FROM {migrate_map_mmi_node_og_group} WHERE destid1 IS NOT NULL")->fetchCol();
$n = 0;
foreach ($etm->getStorage('group')->loadMultiple($gids) as $group) {
  $membership = $group->getMember($account);
  if (!$membership) {
    $what = 'add member with role';
    if ($apply) {
      $group->addMember($account, ['group_roles' => [$role]]);
    }
  }
  else {
    $rel = $membership->getGroupRelationship();
    $roles = array_column($rel->get('group_roles')->getValue(), 'target_id');
    if (in_array($role, $roles, TRUE)) {
      print "{$group->id()} {$group->label()}: already has role\n";
      continue;
    }
    $what = 'add role to existing membership';
    if ($apply) {
      $rel->get('group_roles')->appendItem(['target_id' => $role]);
      $rel->save();
    }
  }
  $n++;
  print "{$group->id()} {$group->label()}: $what\n";
}
print ($apply ? 'Changed' : 'Would change') . " $n group(s) for {$account->getAccountName()}\n";
