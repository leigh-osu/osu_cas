#!/bin/bash

# MMI second-input migration runner (branch mmi-migration)
#
# Migrates the Marine Mammal Institute D7 site INTO the live CAS D10 install
# as a second input: its own migrate_mmi connection, mmi_* migration ids and
# groups, and a +400000 nid/vid offset (see MmiNidOffset::OFFSET in
# osu_migrations_mmi). Plan: "MMI Migration Audit" artifact, 2026-08-19.
#
# Unlike the retired rebuild_site.sh this never installs Drupal. The target is
# a FREEZE of the live production database, restored by section 1, and every
# later section must be reproducible against a fresh freeze: no manual drush
# one-offs -- anything the migration needs lands in this script or in the
# osu_migrations_mmi module.
#
# HARD RULE: the agsci migration map tables (migrate_map_cas_*,
# migrate_map_upgrade_*, migrate_map_paragraph_*, their migrate_message_*
# twins) are kept as provenance for the finished CAS migration and for
# reference during the first weeks of rollout. This script must NEVER drop,
# truncate or reset-status any migration outside the mmi_* namespace. Section
# guards count them before and after and abort on any loss.
#
# Usage:
#   bash scripts-dev/mmi_migrate.sh              # show section help
#   bash scripts-dev/mmi_migrate.sh list         # list sections and exit
#   bash scripts-dev/mmi_migrate.sh all          # run all sections
#   bash scripts-dev/mmi_migrate.sh 2            # run only section 2
#   bash scripts-dev/mmi_migrate.sh from 2       # run section 2 onward
#   bash scripts-dev/mmi_migrate.sh rollback     # roll back mmi_* migrations only
#
# Environment:
#   FREEZE_DUMP  path to the D10 production freeze dump; default: newest
#                osucas_*.sql.gz in the local Acquia backup mirror
#   REFRESH_D7   1 = refresh the mmi D7 source (db+files) from the Acquia
#                backup before verifying it (section 2). Default 0: the source
#                is a deliberate snapshot; refresh it on purpose, not per run.
#
# Sections:
#   1  restore D10 target from the live-site freeze   -> snapshot mmi-freeze
#   2  verify (optionally refresh) the mmi D7 source
#   3  enable osu_migrations_mmi + group wiring       -> snapshot mmi-wired
#   4  users: ONID reconciliation + mmi_users         (TODO -- sequence step 6)
#   5  media + files                                  (TODO -- step 2)
#   6  profiles + nodes (page/book follow section 7) -> snapshot mmi-nodes
#   7  paragraphs -> Layout Builder, then page + book (TODO -- step 4)
#   8  groups, memberships, group + book menus        -> snapshot mmi-groups
#   9  aliases + redirects (nid + group id resolution) -> snapshot mmi-paths
#  10  polish: external stories + publication profiles -> snapshot mmi-polish

# Configuration
SITE_URI="https://osu-cas.ddev.site"   # the ONLY uri that maps to the agsci site
TARGET_DB="db"                          # local DDEV database holding the D10 site
D7_PROJECT="/Users/leighr/Sites/osu/agscid7"
BACKUP_ROOT="/Users/leighr/Sites/osu/acquia_backup/backup"

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${PROJECT_ROOT}" || exit 1

drush() {
  ddev drush --uri="${SITE_URI}" "$@"
}

# Create a named snapshot, removing any existing snapshot of the same name
# first so `ddev snapshot` cannot fail on a name collision (same scheme as the
# retired rebuild_site.sh).
snapshot_save() {
  local name="$1"
  rm -rf "${PROJECT_ROOT}/.ddev/db_snapshots/${name}" \
         "${PROJECT_ROOT}/.ddev/db_snapshots/${name}".* \
         "${PROJECT_ROOT}/.ddev/db_snapshots/${name}"-* 2>/dev/null || true
  ddev snapshot --name "${name}"
}

# Count the non-mmi migrate_map/migrate_message tables in the target. These
# are the agsci provenance tables this script is forbidden to touch; sections
# snapshot the count on entry and re-check it on exit.
agsci_map_table_count() {
  ddev mysql -N -uroot -proot -e \
    "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema='${TARGET_DB}'
       AND (table_name LIKE 'migrate\_map\_%' OR table_name LIKE 'migrate\_message\_%')
       AND table_name NOT LIKE 'migrate\_map\_mmi\_%'
       AND table_name NOT LIKE 'migrate\_message\_mmi\_%';" 2>/dev/null | tr -d '[:space:]'
}

MAP_BASELINE=""
guard_enter() {
  MAP_BASELINE="$(agsci_map_table_count)"
  echo "  [guard] agsci migrate map/message tables: ${MAP_BASELINE}"
}
guard_exit() {
  local now
  now="$(agsci_map_table_count)"
  if [ -n "${MAP_BASELINE}" ] && [ "${now}" -lt "${MAP_BASELINE}" ]; then
    echo "  [guard] FATAL: agsci migrate table count dropped ${MAP_BASELINE} -> ${now}." >&2
    echo "  [guard] The agsci maps are provenance and must never be dropped. Aborting." >&2
    exit 1
  fi
  echo "  [guard] agsci migrate map/message tables intact (${now})"
}

# The one sanctioned reset path: mmi_* migrations only. Never widen this.
rollback_mmi() {
  echo "=== Rolling back mmi_* migrations (agsci maps untouched) ==="
  guard_enter
  for group in mmi_groups mmi_content mmi_profiles mmi_support mmi_paragraphs mmi_media mmi_accounts; do
    drush migrate:rollback --group="${group}" || true
  done
  guard_exit
}

# ---------------------------------------------------------------------------
# Section 1: restore the D10 target from the live-site freeze
#            -> snapshot mmi-freeze
# ---------------------------------------------------------------------------
section_1() {
  echo "=== Section 1: restore D10 target from live freeze ==="

  local dump="${FREEZE_DUMP:-}"
  if [ -z "${dump}" ]; then
    dump="$(ls -1t "${BACKUP_ROOT}/osucas/prod/databases/"osucas_*.sql.gz 2>/dev/null | head -1)"
  fi
  if [ -z "${dump}" ] || [ ! -f "${dump}" ]; then
    echo "No freeze dump found (FREEZE_DUMP unset, nothing in ${BACKUP_ROOT}/osucas/prod/databases)" >&2
    exit 1
  fi

  echo "Freeze dump: ${dump} ($(du -h "${dump}" | cut -f1), $(date -r "${dump}" '+%Y-%m-%d %H:%M'))"
  echo "This REPLACES the local '${TARGET_DB}' database. Ctrl-C within 5s to abort."
  sleep 5

  ddev import-db --database="${TARGET_DB}" --file="${dump}" || exit 1

  # The freeze carries the agsci provenance maps with it; prove they arrived
  # before anything else runs against this copy.
  guard_enter
  if [ -z "${MAP_BASELINE}" ] || [ "${MAP_BASELINE}" -eq 0 ]; then
    echo "FATAL: freeze restored with no agsci migrate_map tables -- wrong dump?" >&2
    exit 1
  fi

  drush cr
  drush status --field=bootstrap || exit 1
  echo "Nodes in restored target: $(ddev mysql -N -uroot -proot "${TARGET_DB}" -e 'SELECT COUNT(*) FROM node;')"

  guard_exit
  snapshot_save mmi-freeze
}

# ---------------------------------------------------------------------------
# Section 2: verify (optionally refresh) the mmi D7 source
# ---------------------------------------------------------------------------
section_2() {
  echo "=== Section 2: mmi D7 source ==="

  if [ "${REFRESH_D7:-0}" = "1" ]; then
    echo "Refreshing mmi source from the Acquia backup mirror..."
    (cd "${D7_PROJECT}" && bash localscripts/sync_d7_backup.sh --site mmi) || exit 1
  fi

  # The D7 project must be running for ddev-agscid7-db to resolve.
  if ! (cd "${D7_PROJECT}" && ddev describe >/dev/null 2>&1); then
    echo "Starting the agscid7 DDEV project..."
    (cd "${D7_PROJECT}" && ddev start) || exit 1
  fi

  # Prove the migrate_mmi connection works end to end: from inside the web
  # container, through the settings.local.php block, into the mmi database.
  echo "Source inventory over the migrate_mmi connection:"
  drush php:eval '
    $db = \Drupal\Core\Database\Database::getConnection("default", "migrate_mmi");
    printf("  nodes:     %d\n", $db->query("SELECT COUNT(*) FROM {node}")->fetchField());
    printf("  files:     %d\n", $db->query("SELECT COUNT(*) FROM {file_managed}")->fetchField());
    printf("  aliases:   %d\n", $db->query("SELECT COUNT(*) FROM {url_alias}")->fetchField());
    printf("  redirects: %d\n", $db->query("SELECT COUNT(*) FROM {redirect}")->fetchField());
    printf("  users:     %d\n", $db->query("SELECT COUNT(*) FROM {users} WHERE uid > 0")->fetchField());
    printf("  newest changed: %s\n", date("Y-m-d H:i", $db->query("SELECT MAX(changed) FROM {node}")->fetchField()));
  ' || { echo "migrate_mmi connection FAILED -- is the migrate_mmi block in settings.local.php?" >&2; exit 1; }
}

# ---------------------------------------------------------------------------
# Section 3: enable osu_migrations_mmi + group wiring  -> snapshot mmi-wired
# ---------------------------------------------------------------------------
section_3() {
  echo "=== Section 3: enable osu_migrations_mmi ==="
  guard_enter

  if drush pm:list --field=status --filter=osu_migrations_mmi 2>/dev/null | grep -qi enabled; then
    # Already on: refresh the module-shipped migration/group config so yml
    # edits land without a reinstall (map tables survive either way). A full
    # cim is NOT safe here: core.extension in the repo does not list
    # osu_migrations_mmi, so it would uninstall the module and purge the
    # mmi_* map tables.
    drush cim --partial --source=/var/www/html/docroot/modules/custom/osu_migrations_mmi/config/install -y || exit 1
  else
    # Fresh freeze: the prod DB predates the branch's config. Import the full
    # site export first so the migration targets exist (research_project
    # content type, its field_res_proj_* fields, group wiring), then enable
    # the migration module on top.
    drush cim -y || exit 1
    drush en -y osu_migrations_mmi || exit 1
  fi
  drush cr

  echo "MMI migration groups:"
  drush config:get migrate_plus.migration_group.mmi_content id >/dev/null || exit 1
  for group in mmi_accounts mmi_profiles mmi_content mmi_media mmi_support mmi_paragraphs mmi_groups; do
    echo "--- ${group}"
    drush migrate:status --group="${group}" 2>/dev/null || echo "  (no migrations yet)"
  done

  guard_exit
  snapshot_save mmi-wired
}

# ---------------------------------------------------------------------------
# Sections 4-9: filled in as the build progresses (audit sequence steps 2-7).
# Each must stay re-runnable against a fresh section-1 freeze.
# ---------------------------------------------------------------------------
section_4() {
  echo "=== Section 4: users -- ONID reconciliation + mmi_users ==="
  guard_enter

  drush scr scripts-dev/mmi_domain_record.php || exit 1
  echo "--- reconciliation audit (CSV: scripts-dev/mmi_user_reconciliation.csv)"
  drush scr scripts-dev/mmi_user_audit.php || exit 1
  echo "--- pre-seed adopted accounts (ROLLBACK_PRESERVE)"
  drush scr scripts-dev/mmi_preseed_user_map.php || exit 1

  drush migrate:import mmi_users || exit 1
  drush migrate:import mmi_user_authmap || exit 1

  # People roles: functional_groups terms -> shared membership_types vocab.
  # Approved mapping 2026-08-28: 7 exact-name adoptions (pre-seeded,
  # ROLLBACK_PRESERVE), 10 created verbatim, 5 unreferenced skipped.
  echo "--- pre-seed name-matched membership terms (ROLLBACK_PRESERVE)"
  drush scr scripts-dev/mmi_preseed_term_map.php || exit 1
  drush migrate:import mmi_membership_terms || exit 1

  drush migrate:status --group=mmi_accounts

  # Prove no live account was touched: every pre-seeded destination uid must
  # still carry its pre-run name/mail (spot data), and created accounts must
  # all have uids above the pre-run maximum.
  drush php:eval '
    $d10 = \Drupal::database();
    $rows = $d10->query("SELECT sourceid1, destid1 FROM {migrate_map_mmi_users} WHERE rollback_action = 1")->fetchAllKeyed();
    printf("adopted rows: %d (live uids: %s)\n", count($rows), implode(",", $rows));
    $created = $d10->query("SELECT COUNT(*) FROM {migrate_map_mmi_users} WHERE rollback_action = 0 AND destid1 IS NOT NULL")->fetchField();
    printf("created accounts: %d\n", $created);
    $bad = $d10->query("SELECT COUNT(*) FROM {migrate_map_mmi_users} WHERE destid1 IS NULL")->fetchField();
    printf("unresolved map rows: %d\n", $bad);
  ' || exit 1

  guard_exit
  snapshot_save mmi-users
}
section_5() {
  echo "=== Section 5: media + files ==="
  guard_enter

  # The D7 source files ride the read-only /var/www/d7 mount
  # (.ddev/docker-compose.d7files.yaml); mmi_files copies them into the live
  # public filesystem under their D7-verbatim uris (no collisions with live
  # uris, verified 2026-08-28). Private scheme is deliberately absent: MMI's
  # only private files are webform submission uploads (not migrated) and one
  # unused image.
  drush migrate:import mmi_files || exit 1
  # 13 private files: 12 stranding-report uploads the webform submissions
  # reference, plus one unreferenced image. No media entities wrap these.
  drush migrate:import mmi_files_private || exit 1
  for m in mmi_media_images mmi_media_documents mmi_media_local_video \
           mmi_media_remote_video mmi_media_kaltura; do
    drush migrate:import "${m}" || exit 1
  done
  drush migrate:status --group=mmi_media

  # Every media row must have resolved its file/oembed source: unresolved
  # rows mean a map hole that node text embeds would trip over later.
  drush php:eval '
    $db = \Drupal::database();
    $bad = 0;
    foreach (["mmi_files","mmi_files_private","mmi_media_images","mmi_media_documents","mmi_media_local_video","mmi_media_remote_video","mmi_media_kaltura"] as $m) {
      $t = "migrate_map_" . $m;
      $n = $db->query("SELECT COUNT(*) FROM {" . $t . "} WHERE destid1 IS NULL")->fetchField();
      if ($n) { printf("%s: %d unresolved rows\n", $m, $n); $bad = 1; }
    }
    if ($bad) { exit(1); }
    print "all mmi media maps fully resolved\n";
  ' || exit 1

  guard_exit
  snapshot_save mmi-media
}
section_6() {
  echo "=== Section 6: profiles + nodes ==="
  guard_enter

  # People first: base node per profiled user (all 101, ownership falls back
  # to uid 1 outside mmi_users scope), then the per-profile2-type layers.
  # Needs section 5 (profile images via mmi_files, CVs via
  # mmi_media_documents). Roles land on lab memberships in section 9.
  drush migrate:import mmi_profiles || exit 1
  for m in mmi_profile_person mmi_profile_employee mmi_profile_faculty \
           mmi_profile_student; do
    drush migrate:import "${m}" || exit 1
  done
  drush migrate:status --group=mmi_profiles

  guard_exit
  snapshot_save mmi-profiles

  guard_enter

  # Biblio dictionaries: shared vocabularies, so name-matched contributors
  # and keywords adopt the live terms (ROLLBACK_PRESERVE) before the imports
  # create the rest.
  echo "--- biblio author/keyword terms (pre-seed shared-vocabulary adoptions)"
  drush scr scripts-dev/mmi_preseed_biblio_terms.php || exit 1
  drush migrate:import mmi_biblio_authors || exit 1
  drush migrate:import mmi_biblio_keywords || exit 1

  # Webform config entities before the nodes that attach them.
  echo "--- webforms"
  drush migrate:import mmi_webform || exit 1

  # Node migrations. page and book are NOT here: their layouts assemble from
  # the paragraph migrations, so they follow section 7.
  echo "--- nodes"
  for m in mmi_biblio mmi_news mmi_feature_story mmi_image_album mmi_video \
           mmi_feed mmi_webform_node mmi_research_project; do
    drush migrate:import "${m}" || exit 1
  done

  echo "--- webform submissions"
  drush migrate:import mmi_webform_submissions || exit 1

  drush migrate:status --group=mmi_content
  drush migrate:status --group=mmi_support

  # Every migrated node must sit in the offset namespace, and every content
  # map row must have resolved.
  drush php:eval '
    $db = \Drupal::database();
    $bad = 0;
    foreach (["mmi_biblio","mmi_news","mmi_feature_story","mmi_image_album","mmi_video","mmi_feed","mmi_webform_node","mmi_research_project","mmi_webform","mmi_webform_submissions"] as $m) {
      $n = $db->query("SELECT COUNT(*) FROM {migrate_map_" . $m . "} WHERE destid1 IS NULL")->fetchField();
      if ($n) { printf("%s: %d unresolved rows\n", $m, $n); $bad = 1; }
    }
    $low = $db->query("SELECT COUNT(*) FROM {migrate_map_mmi_biblio} WHERE destid1 < 400000")->fetchField();
    if ($low) { printf("FATAL: %d biblio nodes below the +400000 offset\n", $low); $bad = 1; }
    if ($bad) { exit(1); }
    print "all mmi node/support maps fully resolved, offset intact\n";
  ' || exit 1

  guard_exit
  snapshot_save mmi-nodes
}
section_7() {
  echo "=== Section 7: paragraphs -> Layout Builder + page/book nodes ==="
  guard_enter

  # Block-producing paragraph migrations first. Embedded D7 views are NOT
  # migrated: pure view paragraphs become labeled placeholder sections
  # ("D7 view: <name>") and compound view columns are skipped, both awaiting
  # the post-section-9 group-view backfill. navigation_grid_paragraph and
  # expedition log missing-migration messages on purpose (hand-build list).
  for m in mmi_paragraph_1_col mmi_paragraph_1_col_clean \
           mmi_paragraph_2_col_left mmi_paragraph_2_col_right \
           mmi_paragraph_3_col_left mmi_paragraph_3_col_center \
           mmi_paragraph_3_col_right mmi_paragraph_accordion \
           mmi_paragraph_menu \
           mmi_2_col_compound_left mmi_2_col_compound_right \
           mmi_3_col_compound_left mmi_3_col_compound_mid \
           mmi_3_col_compound_right; do
    drush migrate:import "${m}" || exit 1
  done
  drush migrate:status --group=mmi_paragraphs

  echo "--- page + book nodes (layouts assemble from the paragraph maps)"
  drush migrate:import mmi_page || exit 1
  drush migrate:import mmi_book || exit 1

  # Both node migrations must be fully imported; their skipped-bundle
  # messages (navigation_grid_paragraph x3, expedition) are expected
  # hand-build breadcrumbs, listed for the record.
  drush php:eval '
    $db = \Drupal::database();
    foreach (["mmi_page", "mmi_book"] as $m) {
      $bad = $db->query("SELECT COUNT(*) FROM {migrate_map_" . $m . "} WHERE destid1 IS NULL")->fetchField();
      if ($bad) { printf("%s: %d unresolved rows\n", $m, $bad); exit(1); }
      foreach ($db->query("SELECT message FROM {migrate_message_" . $m . "}") as $r) {
        printf("  [%s] %s\n", $m, $r->message);
      }
    }
    print "mmi_page + mmi_book fully imported\n";
  ' || exit 1

  guard_exit
  snapshot_save mmi-paragraphs
}
section_9() {
  echo "=== Section 9: aliases + redirects (after groups: redirect targets resolve group nids) ==="
  guard_enter

  # Aliases: node offset, user -> profile node; file/ + taxonomy/ dropped
  # silently; live-alias collisions skipped with a message (breadcrumbs).
  drush migrate:import mmi_url_alias || exit 1
  # Redirects: node offset, user -> profile node, file -> the real file URL;
  # hash collisions with live redirects skipped with a message.
  drush migrate:import mmi_redirect || exit 1

  drush php:eval '
    $db = \Drupal::database();
    foreach (["mmi_url_alias", "mmi_redirect"] as $m) {
      $st = $db->query("SELECT source_row_status s, COUNT(*) c FROM {migrate_map_" . $m . "} GROUP BY s")->fetchAllKeyed();
      printf("%s: imported %d, ignored %d\n", $m, $st[0] ?? 0, $st[2] ?? 0);
      foreach ($db->query("SELECT message FROM {migrate_message_" . $m . "} LIMIT 15") as $r) {
        printf("  [%s] %s\n", $m, $r->message);
      }
    }
    // Spot check: a migrated node alias must resolve to its offset node.
    $alias = $db->query("SELECT pa.alias FROM {path_alias} pa WHERE pa.path LIKE :p ORDER BY pa.id DESC LIMIT 1", [":p" => "/node/40%"])->fetchField();
    printf("sample migrated alias: %s\n", $alias ?: "NONE FOUND");
  ' || exit 1

  guard_exit
  snapshot_save mmi-paths
}
section_10() {
  echo "=== Section 10: polish -- external stories + publication profiles ==="
  guard_enter

  # External-story treatment (rebuild parity: fix_external_stories.php).
  # Resolves the mmi.oregonstate.edu targets, then pre-creates each external
  # story's redirect with auto_forward_external flipped on for just that
  # pass. Needs sections 6 (stories) and 9 (aliases + redirects). Probes
  # live D7 mmi for unresolved targets, so it wants network access.
  drush scr scripts-dev/mmi_fix_external_stories.php || exit 1

  # "My Publications" on profiles: biblio_contributor_data.drupal_uid ->
  # field_pub_osu_profile (rebuild parity: backfill_publication_profiles).
  drush scr scripts-dev/mmi_backfill_publication_profiles.php || exit 1

  drush php:eval '
    $db = \Drupal::database();
    $ext = $db->query("SELECT COUNT(*) FROM {node__field_osu_story_external_url} WHERE entity_id >= 400000")->fetchField();
    $red = $db->query("SELECT COUNT(*) FROM {redirect} r JOIN {node__field_osu_story_external_url} e ON r.redirect_source__path = CONCAT(:n, e.entity_id) WHERE e.entity_id >= 400000", [":n" => "node/"])->fetchField();
    printf("external stories: %d, with redirect treatment: %d\n", $ext, $red);
    if ($red < $ext) { printf("WARNING: %d external stories without redirects\n", $ext - $red); exit(1); }
    $pubs = $db->query("SELECT COUNT(DISTINCT entity_id) FROM {node__field_pub_osu_profile} WHERE entity_id >= 400000")->fetchField();
    printf("publications linked to profiles: %d\n", $pubs);
    if (!$pubs) { exit(1); }
  ' || exit 1

  guard_exit
  snapshot_save mmi-polish
}
section_8() {
  echo "=== Section 8: groups, memberships, menus ==="
  guard_enter

  # Main (D7 nid 1) first: every other group's field_group_parent looks it
  # up in this same migration's map. Group ids are auto-assigned -- never
  # the D7 nids, which live groups already occupy.
  drush migrate:import mmi_node_og_group --idlist=1 || exit 1
  drush migrate:import mmi_node_og_group || exit 1

  echo "--- content placements"
  for m in mmi_group_content_page mmi_group_content_story \
           mmi_group_content_publications mmi_group_content_image_album \
           mmi_group_content_video mmi_group_content_webform \
           mmi_group_content_feed mmi_group_content_research_project; do
    drush migrate:import "${m}" || exit 1
  done

  echo "--- people: typed profile placements + roled user memberships"
  drush migrate:import mmi_profile_placements || exit 1
  drush migrate:import mmi_user_memberships || exit 1

  echo "--- menus: main menu -> Main group menu; book tocs -> lab group menus"
  drush migrate:import mmi_main_menu_links || exit 1
  drush migrate:import mmi_book_menu_links || exit 1

  # Per-domain site name + front page (config collection, the D10 home of
  # D7 domain_conf).
  drush scr scripts-dev/mmi_domain_config.php || exit 1

  drush migrate:status --group=mmi_groups

  drush php:eval '
    $db = \Drupal::database();
    $groups = $db->query("SELECT COUNT(*) FROM {migrate_map_mmi_node_og_group} WHERE destid1 IS NOT NULL")->fetchField();
    printf("groups migrated: %d (expect 15)\n", $groups);
    if ($groups != 15) { exit(1); }
    $placements = $db->query("SELECT COUNT(*) FROM {group_relationship_field_data} g JOIN {migrate_map_mmi_node_og_group} m ON m.destid1 = g.gid")->fetchField();
    printf("relationships in mmi groups (content+people+menus): %d\n", $placements);
    $typed = $db->query("SELECT COUNT(*) FROM {group_content__field_membership_type} f JOIN {group_relationship_field_data} g ON g.id = f.entity_id JOIN {migrate_map_mmi_node_og_group} m ON m.destid1 = g.gid")->fetchField();
    printf("typed profile placements: %d (66 of 88 placements carry a functional group)\n", $typed);
    $alias = $db->query("SELECT alias FROM {path_alias} WHERE path = CONCAT(:p, (SELECT destid1 FROM {migrate_map_mmi_node_og_group} WHERE sourceid1 = 4921))", [":p" => "/group/"])->fetchField();
    printf("GEMM Lab group alias: %s (expect /group/gemm-lab, the D7 alias verbatim)\n", $alias ?: "NONE");
  ' || exit 1

  guard_exit
  snapshot_save mmi-groups
}

# ---------------------------------------------------------------------------
LAST_SECTION=10

list_sections() {
  sed -n '/^# Sections:/,/^$/p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

run_section() {
  "section_$1" || exit 1
}

case "${1:-}" in
  list) list_sections ;;
  all) for i in $(seq 1 ${LAST_SECTION}); do run_section "$i"; done ;;
  from) for i in $(seq "${2:?usage: from N}" ${LAST_SECTION}); do run_section "$i"; done ;;
  rollback) rollback_mmi ;;
  [1-9]|10) run_section "$1" ;;
  *) list_sections; echo; echo "usage: $0 {list|all|N|from N|rollback}" ;;
esac
