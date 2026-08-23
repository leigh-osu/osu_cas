# Domain Access Linkit

Domain Access Linkit provides a Linkit matcher that filters node suggestions to
the domains assigned to the current user. It extends Domain Access so that
editors only see content they’re allowed to link to based on their domain
assignments.

Unlike Domain Access’ default behavior—focused on the current active domain—this
matcher temporarily broadens access during Linkit lookups. Editors can search
for and link to content from any domain they are assigned to, regardless of
which domain they are currently browsing.

## What it provides

- Linkit matcher `node_assigned_domains` (Assigned Domains Content)
    - Extends Linkit’s core `NodeMatcher` and automatically restricts results to
      the current user’s assigned domains.
    - Respects core/node access and Domain Access grants, including language and
      publication status.
- No UI of its own — configure it inside a Linkit profile like any other
  matcher.

## How this differs from Domain Access defaults

- Default Domain Access: Access grants for listings typically depend on the
  active (negotiated) domain; users generally only see content assigned to that
  current domain.
- With this matcher: For Linkit suggestions only, the user’s assigned domain IDs
  are merged into the node grants used for the lookup. As a result, suggestions
  can include content from any domain the user is assigned to — regardless of
  the current domain context.
- Security model is unchanged: Results are still subject to Node access and
  Domain Access rules (published status, per‑realm grants, “view unpublished”
  permissions, etc.). The matcher only broadens the domain scope to “assigned
  domains” during the search.

## Requirements

- Drupal Linkit module
- Domain and Domain Access modules (from the Domain project)
- The user’s role must include the permission `Allow referencing content on all
  assigned domains` (machine name: `reference content on assigned domains`).
  Without this permission, the matcher falls back to normal access grants and
  will not list content from other assigned domains.

## Installation

1. Enable modules:
    - Domain (`domain`)
    - Domain Access (`domain_access`)
    - Linkit (`linkit`)
    - Domain Access Linkit (`domain_access_linkit`)
2. Ensure your site has domains configured and users assigned to domains via the
   Domain Access fields.

## Configure in a Linkit profile

1. Go to Administration → Configuration → Content authoring → Linkit.
2. Edit or create a profile.
3. In the “Matchers” section, add the matcher “Assigned Domains Content”.
    - Technical ID: `node_assigned_domains`
    - Important: Remove any other Node/content matchers from this profile (e.g.,
      Linkit’s default Node matcher). If multiple Node matchers are enabled, the
    same content can appear multiple times in the suggestions.
4. Optional: Adjust inherited Node matcher settings (bundles, limit, include
   unpublished).
5. Save the profile and use it in your text formats/editor.

Notes:

- This matcher inherits Linkit’s core Node matcher options. If “Include
  unpublished nodes” is enabled, the user still needs the correct permissions
  (e.g., “view own unpublished content”, “view any unpublished content”).
- To avoid duplicate results in the Linkit suggestions, ensure only one Node
  matcher is active in the profile. When using this module, prefer
  `node_assigned_domains` and remove other Node matchers.

## How it works

- The matcher temporarily augments node grants to include the numeric IDs of the
  user’s assigned domains. Internally it sets a drupal_static that Domain Access
  reads in `hook_node_grants_alter()`, merging the user’s domain IDs for the
  duration of the Linkit lookup.
- Linkit’s entity query runs with `accessCheck(TRUE)`, and each loaded entity is
  also checked with `$entity->access('view', $account)`. As a result, only nodes
  visible to the user on their assigned domains are returned.
- If a user is assigned to multiple domains, suggestions from each of those
  domains are eligible — even when the currently negotiated domain is different.

## Expected behavior (examples)

- User assigned to Domain A only: only nodes assigned to Domain A appear (no
  matter which domain they are browsing on).
- User reassigned to Domain B: suggestions flip to nodes assigned to Domain B.
- User assigned to Domain A and Domain B: suggestions include nodes from both
  domains (even if the active domain is A or something else).

These behaviors are covered by the kernel test `AssignedDomainsNodeMatcherTest`
in this module.

## Cross‑domain links and canonical domain (important)

When this matcher suggests content from domains different from the current
active domain, the generated link may point to a path that is not available on
the current domain. This can lead to dead links unless each piece of content has
its canonical/source domain defined.

- Recommended: Use the Domain Source module to define a canonical “Domain
  source” for nodes. This ensures URLs resolve on the correct domain and helps
  avoid broken links when editors link across domains.
- Without a defined source domain, a node assigned to another domain might not
  be reachable from the current domain’s hostname, even if it appears in the
  suggestions list.

## Permissions and context

Ensure the following so results are correctly filtered:

- The user must not bypass node access (avoid UID 1 and “bypass node access”
  permission for testing/editing unless intended).
- The user must have `access content` and any additional permissions to see
  unpublished content if “Include unpublished” is enabled.
- The role must have `reference content on assigned domains` for
  cross-assigned-domain lookups to work. Without it, results will be limited to
  what standard grants allow (typically the currently active domain).
- Domain context: Domain Access grants normally consider the active domain. The
  matcher supplies temporary grants for the user’s assigned domains, so
  suggestions work across assigned domains regardless of the current domain.
  Site‑wide policies (like unpublished access) still apply.

## Troubleshooting

- Seeing all published nodes across domains:
    - Check if a global default grant is interfering. Core writes a
      `{node_access}` default row with `nid=0` that can allow viewing all
      published nodes. In production this typically coexists with Domain Access
      just fine, but in minimal test setups you might need to rebuild grants.
- No results are returned:
    - Verify the user has `access content` and is assigned to at least one
      domain.
    - Confirm the user’s role includes `reference content on assigned domains`.
    - Confirm nodes are assigned to domains via `field_domain_access`.
    - Rebuild node grants if you changed access modules: run a grants rebuild
      (via UI or Drush), or in tests call `node_access_rebuild()`.
- UID 1 always sees everything:
    - Site owner (UID 1) bypasses access checks by design. Test with a
      non‑privileged user.

## Testing

- Kernel coverage:
  `domain_extras/domain_access_linkit/tests/src/Kernel/AssignedDomainsNodeMatcherTest.php`
  verifies:
    - Single-domain restriction
    - Switching assigned domain
    - Both domains assigned → both contents appear

## FAQ

- Does this require a permission? Yes. The role must include `Allow referencing
  content on all assigned domains` (machine name: `reference content on
  assigned domains`). Without it, the matcher behaves like standard access and
  won’t list content from other assigned domains.
- Can I combine with Linkit’s bundle filters? Yes. Bundle filters apply on top
  of domain filtering.

## Credits

- Built on top of the Domain project and Linkit module.
