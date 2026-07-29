# Domain Configuration Entity UI

Domain Configuration Entity UI extends `domain_config_ui` so that
`EntityForm`-based config entity admin flows (block, view modes,
search pages, views, …) work cleanly under per-domain overrides.

This module is **experimental**. Installing it is the opt-in -- there
is no separate runtime flag. The `lifecycle: experimental` marker on
the `.info.yml` plus Drupal's install confirmation prompt are what
gate exposure of the EntityForm-based per-domain flow.

## Why this submodule exists

`domain_config_ui` exposes a "Enable domain configuration" toggle on
config edit forms, and its parent `domain_config` module wires the
override layer so subsequent reads of registered configurations return
the per-domain value. That contract works for `ConfigFormBase` flows
(`system.site`, `system.theme`, …) -- but it does not extend cleanly
to **EntityForm-based** config entities (`block.block.*`, view modes,
search pages, ...) because of three Drupal core mechanisms:

1. **No toggle on EntityForm.** The parent module's `form_alter`
   only injects the toggle on `ConfigFormBase` and
   `ConfigTranslationFormBase`; EntityForm-based edit pages never see
   it, even when the underlying config name is otherwise overridable.
2. **`Drupal\Core\ParamConverter\AdminPathConfigEntityConverter`**
   loads config entities *override-free* on every admin route, so the
   edit form for a domain-overridden block renders the base values
   even when the active domain has an override registered.
3. **`Drupal\Core\Config\Entity\ConfigEntityListBuilder::load()`**
   calls `loadMultipleOverrideFree()` on the entity storage handler,
   so admin list pages (e.g. `/admin/structure/block`) render the
   base label too. The same call path bites
   `Drupal\block\BlockListBuilder::submitForm()`, where saving a
   region or weight on a block whose label is overridden silently
   overwrites the existing override via the diff bridge in
   `DomainConfigOverrideEditable::save()`.

This submodule layers a fix for each: a `form_alter` that exposes the
toggle on EntityForm pages, a higher-priority `ParamConverter` that
loads override-merged on registered configs, and a list-builder swap
that reads the same way for the block list page.

## What it does

The submodule layers five coordinated pieces on top of
`domain_config_ui`:

* **`DomainAwareConfigEntityStorageTrait`** overrides
  `doLoadMultiple()` so override-free reads fold the active domain's
  override on top for configurations registered for that domain. The
  entity is stamped with the `domain` cache context so render caches
  stay correct across domains. Regular loads (saves, runtime,
  drush's regular load, …) are unaffected: the trait short-circuits
  when `$this->overrideFree` is FALSE and lets the parent storage's
  behavior flow through.
* **`DomainAwareConfigEntityStorageInterface`** is an empty marker
  interface implemented by every storage handler that uses the
  trait. The form_alter and ParamConverter gate on this interface,
  so capability discovery is runtime introspection rather than a
  hardcoded entity-type list.
* **`DomainAwareConfigEntityStorage`** is a thin shell extending
  `ConfigEntityStorage`, using the trait, and implementing the
  interface. `DomainAwareSwapRegistry` auto-discovers every config
  entity type whose default `storage_class` is exactly
  `ConfigEntityStorage` (block, view modes, search pages, views, …)
  and `DomainConfigEntityUiEntityTypeHooks::entityTypeAlter()` swaps
  them to this class. The strict-equality guard preserves contrib
  subclasses untouched.
* **`DomainConfigEntityUiFormHooks::formAlter()`** exposes the
  parent module's "Enable domain configuration" toggle on
  EntityForm-based config-entity edit pages -- but only when the
  resolved storage instance implements
  `DomainAwareConfigEntityStorageInterface`. Entity types we have
  not yet curated never see the toggle, so the user cannot register
  a config the read-side cannot safely round-trip.
* **`DomainOverrideConfigEntityConverter`** runs at higher priority
  than core's `AdminPathConfigEntityConverter`. Same capability gate
  as the form_alter: it intercepts only when the storage is
  domain-aware and the config is registered for the active domain;
  otherwise it defers to core's override-free behavior.

A latent bug in `BlockListBuilder::submitForm` is fixed for free: the
swapped `block` storage returns override-merged data on the
override-free load path, so changing region or weight on a block
whose label is overridden no longer silently drops the label
override on the diff bridge in
`DomainConfigOverrideEditable::save()`.

## Requirements

* `domain` (base module)
* `domain_config` (transitive)
* `domain_config_ui`

## Installation

```bash
drush en domain_config_entity_ui -y
```

The module declares `lifecycle: experimental`. Drupal will display a
confirmation prompt on install pointing at issue
[#3588091](https://www.drupal.org/project/domain_extras/issues/3588091).

## Configuration

Two opt-in layers gate exposure of the per-domain flow:

1. **Installing the submodule** is the gross gate. The
   `lifecycle: experimental` marker on the `.info.yml` plus
   Drupal's install confirmation prompt make this an explicit user
   action.
2. **Per-entity-type checkboxes** on
   *Administration > Configuration > Domain > Domain Config Entity UI*
   (`/admin/config/domain/config-entity-ui`) are the fine gate.
   Default install is **empty** — no entity type is covered until the
   user explicitly checks a box.

Saving the form flips the entity type definitions cache via
`DomainConfigEntityUiSettingsSubscriber`, so the new selection
takes effect on the next request without `drush cr`.

The form lists only entity types whose storage handler this
submodule (or contrib via the alter hook below) can cleanly swap.
Types whose storage handler is custom and we have not provided a
sibling subclass for (`user_role`, `image_style`, `menu`, …) do not
appear in the form, do not carry the toggle on their edit forms, and
are not affected at all.

## Adding another config entity type

The registry that drives both the SettingsForm checkboxes and the
storage swap is built in two stages:

### Auto-discovery (no code needed)

Every config entity type whose default `storage_class` is core's
`ConfigEntityStorage` is picked up automatically by
`DomainAwareSwapRegistry::computeSwaps()`. Out of the box that
covers `block`, `entity_view_mode`, `entity_form_mode`,
`search_page`, `view`, and any other vanilla-storage config entity
type Drupal core or contrib registers. No map entry to maintain.

### Custom-storage types via the alter hook

Entity types that ship their own storage handler subclass
(`image_style` → `ImageStyleStorage`, `user_role` → `RoleStorage`,
`menu` → `MenuStorage`, `taxonomy_vocabulary` → `VocabularyStorage`,
…) are NOT auto-discovered — the strict-equality guard would
refuse to swap their `storage_class` to a class that doesn't extend
the existing handler. To cover them, ship a sibling subclass and
register it via the alter hook:

```php
class DomainAwareImageStyleStorage
  extends ImageStyleStorage
  implements DomainAwareConfigEntityStorageInterface {
  use DomainAwareConfigEntityStorageTrait;
}
```

```php
/**
 * Implements hook_domain_config_entity_ui_swaps_alter().
 */
function my_module_domain_config_entity_ui_swaps_alter(array &$swaps): void {
  $swaps['image_style'] = [
    \Drupal\my_module\Entity\DomainAwareImageStyleStorage::class,
    \Drupal\image\ImageStyleStorage::class,
  ];
}
```

The alter hook fires once per request from
`DomainAwareSwapRegistry::computeSwaps()`. After registration, the
new type appears as a checkbox on the SettingsForm and the user
opts in explicitly.

Modules can also REMOVE auto-discovered entries from `$swaps` to
opt a vanilla-storage entity type out of coverage entirely.

### Strict-equality guard

The expected current class in the second tuple position is compared
against the entity type's actual `storage_class` at discovery time.
The swap is only applied when they match — preserving any custom
`storage_class` set by another module. To chain on top of someone
else's custom handler, your DomainAware* class must extend their
class and declare it as the expected current class.

### Tests

The functional test
`DomainConfigEntityUiToggleTest::testNoToggleOnNonCoveredEntityType`
asserts that uncovered types (today: `user_role`) do NOT carry the
toggle. When you ship coverage for a type, flip its assertion to the
positive form.

## Related issues

* [#3587744](https://www.drupal.org/project/domain/issues/3587744) --
  parent issue on `domain_config_ui` introducing the EntityForm flow
  (originally drafted there, then moved here to keep the experimental
  feature self-contained).
* [#3588091](https://www.drupal.org/project/domain_extras/issues/3588091)
  -- this submodule.
* [#3588057](https://www.drupal.org/project/domain/issues/3588057) /
  [#3588108](https://www.drupal.org/project/domain_extras/issues/3588108)
  -- the parallel "menu plugin manager cache leaks across domains"
  pair.
