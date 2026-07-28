<?php

/**
 * @file
 * Roles & permissions report for the CAS platform, as Markdown on stdout.
 *
 * Usage:
 *   ddev drush --uri=agsci.oregonstate.edu scr scripts-dev/roles_report.php
 *   ddev drush --uri=agsci.oregonstate.edu scr scripts-dev/roles_report.php > roles-report.md
 *
 * Everything is read live: role definitions and permissions from active
 * config, holder/membership counts from the database, and each role's origin
 * by scanning profiles/ and modules/ for the user.role.* / group.role.*
 * config/install file that ships it (roles with no shipping extension are
 * reported as site-config only). Permissions on a small watchlist are
 * surfaced per role so privilege escalators stand out.
 */

use Drupal\group\Entity\GroupRole;
use Drupal\user\Entity\Role;

// Permissions that make a role more powerful than its name suggests.
$watchlist = [
  'administer modules',
  'administer permissions',
  'administer users',
  'administer nodes',
  'bypass node access',
  'import configuration',
  'export configuration',
  'synchronize configuration',
  'administer site configuration',
  'update any media',
  'use text format full_html',
  'edit webform twig',
  'view user email addresses',
];

$db = \Drupal::database();

// --- Origins: which extension ships each role config. ---------------------
$origins = [];
$dirs = array_merge(
  glob(DRUPAL_ROOT . '/profiles/*/*/config/*', GLOB_ONLYDIR) ?: [],
  glob(DRUPAL_ROOT . '/modules/*/*/config/*', GLOB_ONLYDIR) ?: [],
  glob(DRUPAL_ROOT . '/modules/*/*/modules/*/config/*', GLOB_ONLYDIR) ?: []
);
foreach ($dirs as $dir) {
  foreach (glob($dir . '/{user,group}.role.*.yml', GLOB_BRACE) ?: [] as $file) {
    // .../<extension>/config/<install|optional>/<prefix>.role.<id>.yml
    $extension = basename(dirname(dirname($dir === dirname($file) ? $file : $dir)));
    $extension = basename(dirname(dirname($dir)));
    $id = preg_replace('/^(user|group)\.role\.(.+)\.yml$/', '$2', basename($file));
    $origins[$id][] = $extension;
  }
}
$origin = fn(string $id): string => isset($origins[$id]) ? implode(', ', array_unique($origins[$id])) : '(site config only)';

// --- Counts. ---------------------------------------------------------------
$total_users = (int) $db->query('SELECT COUNT(*) FROM {users_field_data} WHERE uid > 0')->fetchField();
$users_per_role = $db->query('SELECT roles_target_id, COUNT(*) c FROM {user__roles} GROUP BY roles_target_id')->fetchAllKeyed();
$group_count = (int) $db->query('SELECT COUNT(*) FROM {groups_field_data}')->fetchField();
$membership_count = (int) $db->query("SELECT COUNT(*) FROM {group_relationship_field_data} WHERE plugin_id = 'group_membership'")->fetchField();
$group_role_assignments = $db->query('SELECT group_roles_target_id, COUNT(*) c FROM {group_content__group_roles} GROUP BY group_roles_target_id')->fetchAllKeyed();

$today = date('Y-m-d');
$host = \Drupal::request()->getHost() ?: 'cli';

print "# Roles & permissions — OSU CAS platform\n\n";
print "Generated {$today} against `{$host}`. ";
print "{$total_users} user accounts, {$group_count} groups, {$membership_count} group memberships.\n\n";

// --- Site roles. -----------------------------------------------------------
print "## Site roles\n\n";
print "| Role | Machine name | Origin | Users | Perms | Watchlist permissions |\n";
print "|---|---|---|---:|---:|---|\n";

$site_roles = Role::loadMultiple();
uasort($site_roles, fn($a, $b) => $a->getWeight() <=> $b->getWeight());
foreach ($site_roles as $rid => $role) {
  $perms = $role->getPermissions();
  $flagged = array_intersect($watchlist, $perms);
  $count = $role->isAdmin() ? 'all' : count($perms);
  $users = $rid === 'authenticated' ? $total_users : (int) ($users_per_role[$rid] ?? 0);
  $users = $rid === 'anonymous' ? '—' : $users;
  $label = $role->label() . ($role->isAdmin() ? ' **(is_admin)**' : '');
  $flags = $role->isAdmin() ? '*bypasses all checks*' : ($flagged ? '`' . implode('`, `', $flagged) . '`' : '');
  print "| {$label} | `{$rid}` | " . $origin($rid) . " | {$users} | {$count} | {$flags} |\n";
}

// --- Admin tier comparison. ------------------------------------------------
$arch = Role::load('architect');
$dx = Role::load('dx_administrator');
if ($arch && $dx) {
  $a = $arch->getPermissions();
  $d = $dx->getPermissions();
  $only_dx = array_diff($d, $a);
  $only_arch = array_diff($a, $d);
  print "\n### Architect vs DX Administrator\n\n";
  printf("Shared: %d. DX-only: %d. Architect-only: %d.\n\n", count(array_intersect($a, $d)), count($only_dx), count($only_arch));
  print "DX Administrator only:\n\n";
  foreach ($only_dx as $p) {
    print "- `{$p}`\n";
  }
  print "\nArchitect only" . (in_array('bypass node access', $d) ? ' (all redundant under `bypass node access`, which both roles hold)' : '') . ":\n\n";
  foreach ($only_arch as $p) {
    print "- `{$p}`\n";
  }
}

// --- Group roles. ----------------------------------------------------------
print "\n## Group roles\n\n";
print "| Group role | Scope | Global role | Admin | Perms | Assignments | Origin |\n";
print "|---|---|---|---|---:|---:|---|\n";

foreach (GroupRole::loadMultiple() as $rid => $role) {
  $perms = $role->getPermissions();
  $count = $role->isAdmin() ? 'all' : count($perms);
  $assignments = $role->getScope() === 'individual'
    ? (int) ($group_role_assignments[$rid] ?? 0)
    : '—';
  printf("| %s | %s | %s | %s | %s | %s | %s |\n",
    $role->label(), $role->getScope(), $role->getGlobalRoleId() ?? '—',
    $role->isAdmin() ? 'yes' : '', $count, $assignments, $origin($rid));
}

print "\n### Group role permissions\n";
foreach (GroupRole::loadMultiple() as $rid => $role) {
  if ($role->isAdmin()) {
    continue;
  }
  $perms = $role->getPermissions();
  print "\n**{$role->label()}** (`{$rid}`, " . count($perms) . "):\n\n";
  foreach ($perms as $p) {
    print "- `{$p}`\n";
  }
}

print "\n---\n";
print "Watchlist legend: permissions that grant broad or escalating power ";
print "(module/user/permission administration, node administration or bypass, ";
print "config import/export, any-media editing, full HTML, webform Twig).\n";
