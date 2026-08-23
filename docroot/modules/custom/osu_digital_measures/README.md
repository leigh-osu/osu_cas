# OSU Digital Measures

Provides a field formatter that displays a
[Digital Measures](https://www.digitalmeasures.com) web-profiles report for a
person, keyed on their ONID (CAS) account.

This is the Drupal 10 port of the Drupal 7 `osu_digital_measures` module. In D7
the formatter ran on user/profile entities and read `$user->cas_name`; in D10
profiles are `osu_profile` nodes, so the ONID is read from the CAS authname of
the entity owner via the `externalauth.authmap` service.

## How it works

The module defines one field formatter, **Digital Measures report**
(`osu_digital_measures_report`), for **boolean** fields:

- It resolves the ONID by taking the field's entity owner
  (`EntityOwnerInterface::getOwner()`) and looking up that user's `cas` authname
  in the `authmap` table (`externalauth.authmap`).
- When the boolean is **on** — and the owner has a CAS authname and a Client ID
  and Report ID are configured — it renders an empty container `<div>` and
  attaches the `osu_digital_measures/report` library.
- The library loads the Digital Measures web-profiles widget (CSS/JS from
  `cfcdn.digitalmeasures.com`) plus `js/osu_digital_measures.js`, which calls
  `dmWebProfiles.showProfile()` for each container in `drupalSettings` and
  truncates long link text to 30 characters.
- Nothing is rendered when the boolean is off, the owner has no CAS authname, or
  the formatter is missing a Client ID / Report ID. Multiple reports can be
  placed on one page.

## Current configuration

The formatter is wired up on the `osu_profile` content type for two boolean
fields:

| Field | Report |
| --- | --- |
| `field_profile_dm_pubs` | Publications |
| `field_profile_dm_awards` | Awards |

Both use the same Digital Measures Client ID with per-report Report IDs, set on
the field formatter in the profile view display
(`config_imports/display/core.entity_view_display.node.osu_profile.full.yml`,
and the Layout Builder `default` display). Both fields use a hidden label.

## Adding the formatter to another field

1. Add a **Boolean (on/off)** field to an entity type that has an owner.
2. On **Manage display**, set the field's format to **Digital Measures report**
   and enter a valid Digital Measures **Client ID** and **Report ID** (hide the
   label).

## Requirements

- `externalauth` (the CAS authname store).
- The field's entity must implement `EntityOwnerInterface`, and the owner must
  have a `cas` authname for a report to display.
