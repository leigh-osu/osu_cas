# Domain Early Negotiation

Domain Early Negotiation provides a middleware that negotiates the active domain
early in the HTTP middleware stack. This makes `domain_config` overrides
available to other middlewares that run before Drupal's kernel request event.

## Why this module exists

By default, the active domain is negotiated during the kernel request event
(`DomainSubscriber`). This works for most sites, but some contributed modules
register HTTP middlewares that read Drupal configuration -- for example, the
[CleanTalk](https://www.drupal.org/project/cleantalk) anti-spam module. These
middlewares run *before* the domain is known, so `domain_config` overrides are
not yet active.

Installing this module activates a `DomainNegotiationMiddleware` that negotiates
the active domain earlier in the stack -- after ReverseProxy (priority 300) so
`HTTP_HOST` is already corrected, but before other middlewares that need
domain-aware configuration.

**Enabling the module = enabling the feature.** There is no separate toggle --
the middleware is always active when the module is installed.

## Requirements

- Domain (`domain`)

## Installation

Enable the module:

```bash
drush en domain_early_negotiation
```

The middleware is immediately active with a default priority of **220**.

## Configuration

Visit `/admin/config/domain/early-negotiation` (or Administration > Domain >
Early negotiation) to adjust the middleware priority.

The priority must stay **below 300** (ReverseProxy) and **above** any middleware
that depends on `domain_config` overrides.

!!! warning "Priority and page cache performance"
    The **page cache** middleware runs at priority 200. With the default
    priority of 220, domain negotiation runs **before** the page cache, meaning
    it executes on every request -- even when the response is served from cache
    and negotiation is not needed.

    On **Drupal < 11.1** this is particularly expensive: the middleware must
    call `loadAll()` to load every `.module` file so that procedural hook
    implementations (e.g. `hook_domain_request_alter`) are available during
    negotiation.

    On **Drupal 11.1+** OOP hooks are dispatched via the container and
    `loadAll()` is only needed as a fallback on cold entity type cache, so the
    overhead is much smaller -- but negotiation still runs unnecessarily on
    cached pages.

    If you do not need domain_config overrides in middlewares that run before
    the page cache, consider setting the priority **below 200** to avoid this
    overhead.

## How it works

The priority is compiled into a container parameter via
`DomainEarlyNegotiationServiceProvider`, so there is zero overhead from reading
configuration at runtime. When the priority setting changes, the
`ConfigSubscriber` invalidates the container so the new priority takes effect on
the next request.

## Related issues

- [#3575069: Negotiate domain early in the middleware stack](https://www.drupal.org/i/3575069)
