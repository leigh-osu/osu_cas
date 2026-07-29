# Domain Menu Extras

Domain Menu Extras re-keys core's menu plugin discovery caches by the
active domain so derivers reading overridable configuration do not
freeze their result on the first domain that populates the cache.

This module is opt-in -- install only if you have overridable
configuration that feeds into local task / local action / contextual
link derivers (e.g. `system.theme.default` flowing into the block
administration "default theme" tab).

## Why this module exists

`Drupal\Core\Menu\LocalTaskManager`, `LocalActionManager` and
`ContextualLinkManager` key their plugin definition cache on the
current language only:

```php
// core/lib/Drupal/Core/Menu/LocalTaskManager.php
$this->setCacheBackend(
  $cache,
  'local_task_plugins:' . $language_manager->getCurrentLanguage()->getId(),
  ['local_task'],
);
```

Derivers running through these managers are free to read overridable
configuration when computing their derivatives. The canonical example
is `Drupal\block\Plugin\Derivative\ThemeLocalTask`, which reads
`system.theme.default` -- a value that varies per domain when
`domain_config_ui` registers `system.theme` as overridable. The
deriver's output therefore varies per domain, but the cached output
does not, so whichever domain hits the cache cold first wins for
everyone.

The user-visible symptom is that the active "default theme" tab on
`/admin/structure/block` is the same on every domain, regardless of
each domain's overridden default theme.

## What it does

* **`DomainAwareLocalTaskManager`** extends
  `Drupal\Core\Menu\LocalTaskManager` and re-binds the plugin
  definition cache backend in its constructor with a domain-aware
  cache key:

  ```text
  local_task_plugins:LANGCODE:DOMAIN_ID
  ```

  Where `DOMAIN_ID` is the active domain id resolved through
  `DomainNegotiationContext`, falling back to the `und` sentinel when
  no domain has been negotiated yet (CLI, install hooks, ...). Each
  domain therefore gets its own cache slot, and the deriver runs once
  per domain rather than once globally.

* **`DomainMenuExtrasServiceProvider::alter()`** wires the swap by
  calling `setClass()` + `addArgument('@domain.negotiation_context')`
  on the existing `plugin.manager.menu.local_task` service definition.
  Inheriting core's argument list keeps us resilient to upstream
  constructor changes -- we only append the extra
  `DomainNegotiationContext` reference our subclass needs.

Only `LocalTaskManager` is swapped today.
`Drupal\Core\Menu\LocalActionManager` and
`Drupal\Core\Menu\ContextualLinkManager` key their plugin definition
caches the same way (`local_action_plugins:LANGCODE` and
`contextual_links_plugins:LANGCODE`) and would benefit from the same
treatment. They are intentionally out of scope for the initial release
— add a sibling subclass + a second `setClass()` call in
`DomainMenuExtrasServiceProvider::alter()` if a deriver running through
either of those managers reads overridable configuration on your site.

## Requirements

* `domain` (base module)

## Installation

```bash
drush en domain_menu_extras -y
```

Module install rebuilds the service container automatically, so the
class swap takes effect immediately -- no extra `drush cr` needed.
Uninstalling reverts to core's `LocalTaskManager` on the next
container build.

## Performance trade-off

* **Per-request overhead**: one extra string concatenation when the
  service is instantiated (cache key composition). The
  `DomainNegotiationContext` service is already in memory at that
  point because `DomainSubscriber` resolves the active domain at
  kernel.request priority 256.
* **Cache fragmentation**: `local_task_plugins` entries multiply by
  the number of domains actually visited (one entry per
  `<language x domain x route>` combo). Plugin definitions are small
  metadata maps, so even on a site with dozens of active domains the
  total bytes stay small.
* **Cold-cache miss rate**: the *first* request to a given route on a
  given domain triggers a discovery run for that combo; thereafter
  every request hits the cache. There is no per-request hot-path
  penalty.
* **Tag invalidation** is unchanged: the `local_task` cache tag still
  invalidates everything together when needed.

The alternative -- invalidating the `local_task` cache tag on every
domain switch -- would be strictly worse: it evicts all entries on
every switch, forcing constant rediscovery.

## Why this lives in domain_extras and not in the base module

Cache fragmentation is opt-in here. Every site running `domain` binds
an active domain on every request via `DomainSubscriber`; with the
swap always on in the base module, the local task plugin definition
cache would fragment per domain regardless of whether anyone
registered an overridable config -- a small footprint cost imposed on
users who get nothing for it.

Module install/uninstall also handles container rebuilds idiomatically.
Gating the swap behind a config flag in the base module would require
invalidating the cached service container on toggle (heavier than the
entity-type definitions clear we use for
`domain_config_entity_ui`), which is awkward to wire up cleanly.

The parallel proposal that lands the same fix directly in the `domain`
base module lives at [#3588057](https://www.drupal.org/i/3588057).
[#3588108](https://www.drupal.org/i/3588108) provides the side-by-side
opt-in alternative for comparison.

## Related issues

* [#3588108](https://www.drupal.org/i/3588108) -- this submodule.
* [#3588057](https://www.drupal.org/i/3588057) -- parallel proposal in
  the `domain` base module.
* [#3588091](https://www.drupal.org/i/3588091) -- sibling
  `domain_config_entity_ui` submodule, addresses the same class of
  problem on the config-entity admin list / form side.
