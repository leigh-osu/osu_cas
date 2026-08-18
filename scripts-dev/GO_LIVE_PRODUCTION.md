# Go live: production

Running list of things that must happen when the CAS D10 site becomes the
public production site. Kept in this file rather than in someone's head because
several of them are only wrong *in production* — they are the correct settings
everywhere else, so nothing on dev or stage will ever flag them.

Last updated 17 Aug 2026.

---

## Must do at go-live

### 1. reroute_email — nothing to do, but verify

`reroute_email` is installed and points every outbound message at
`roger.leigh@oregonstate.edu`. 98 webforms carry 148 active email handlers, so
any environment holding a copy of this database would otherwise mail real OSU
staff the moment a form is submitted.

**This is now automatic.** The last lines of
`docroot/sites/agsci.oregonstate.edu/settings.php` set:

```php
$osu_cas_ah_env = $_ENV['AH_SITE_ENVIRONMENT'] ?? '';
$config['reroute_email.settings']['enable'] = ($osu_cas_ah_env !== 'prod');
```

Expressed as "reroute unless prod" so both failure modes land safe: a new
environment nobody thought about reroutes by default, and a database copied
from production down to stage starts rerouting again as soon as it is served
from a non-production environment. It is a settings override, so it cannot be
switched off from the admin UI by accident and it survives every database push.

Verified across simulated environments — local, dev and stage reroute; prod
sends real mail.

At go-live, just confirm on production:

```
drush @osucas.prod -l agsci.oregonstate.edu php:eval \
  'var_dump(\Drupal::config("reroute_email.settings")->get("enable"));'
```

Must print `bool(false)` on prod, and `bool(true)` anywhere else.

### 2. Restore production performance settings

`system.performance` currently ships `cache.page.max_age: 0` and
`css.preprocess: false`. Correct locally, wrong in production: it tells
Acquia's edge cache and Varnish not to cache anything and serves unaggregated
CSS. This is config, so it travels with every database push.

```
drush @osucas.prod -l agsci.oregonstate.edu config:set system.performance cache.page.max_age 900 -y
drush @osucas.prod -l agsci.oregonstate.edu config:set system.performance css.preprocess 1 -y
drush @osucas.prod -l agsci.oregonstate.edu config:set system.performance js.preprocess 1 -y
```

### 3. Confirm cron is scheduled

Acquia Cloud → Environments → prod → Cron tasks:

```
drush --root=/var/www/html/${AH_SITE_GROUP}.${AH_SITE_ENVIRONMENT}/docroot \
      --uri=${AH_SITE_NAME}.${AH_REALM}.acquia-sites.com \
      cron
```

Every 15 minutes. Purge is enabled and its queue reached 100,000 items during
the rebuild, so queue processing is the main thing cron carries today. If the
editorial workflow is ever enabled, Scheduler's embargo dates depend on this
entirely.

### 4. Confirm backups

Acquia's backup schedule must cover prod before it is the environment people
are typing into.

---

## Check before, expect after

### Search results will be stale at first

Site search is a Google Custom Search Engine (`/search/osu`), not a Drupal
index — core node search is deliberately disabled. CSE returns what Google has
crawled, which at go-live is still the D7 site, so results will point at old
URLs until Google re-crawls.

This is what the 27,018 redirects are for: stale results land correctly instead
of 404ing. Do not prune redirects to tidy up — including the retired `/users/`
and `/people/` namespaces.

### robots.txt

The file that deploys is the standard permissive Drupal one. `Disallow: /` only
appears locally, injected by DDEV for `.ddev.site` hosts. Nothing to do; noted
so nobody "fixes" it in a panic.

### Error pages

D7 had a custom 404 at `node/26860`, which was not migrated. D10 falls back to
the themed default, which renders correctly with full site chrome. Cosmetic
only — decide whether a custom 404 is wanted.

---

## Known content state at handover

None of these block go-live; they are listed so the first person to notice one
does not file it as a migration failure.

| Item | Count | Note |
|---|---|---|
| Profiles with no title | 8 | Cannot generate an alias; reachable at `/node/N` only |
| Published nodes with no alias | 23 | 6 of them the above |
| Dead D6-era private links | 6 | On nodes 71286, 77701, 79036 |
| Corrupt source images | 40 | Damaged in D7; catalogued in `corrupt_image_report.md` |
| Unpublished nodes | 549 | Migrated unpublished, still unpublished |

---

## Open decisions

- **Editorial workflow** — not enabled. See the workflow study. Enabling it
  later is cheap (moderation state is derived from publication status), so this
  is not blocking.
- **Audit findings #5–#14** — still open. The cheap window for these closes
  once editors start, because `rebuild_site.sh` can never run again after that.
