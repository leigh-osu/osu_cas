# Domain Render Context

Domain Render Context runs a callback as if the request had come from another
domain, so output built outside the request it belongs to (an email, a PDF, a
queue item) carries that domain's links, configuration and path prefix.

## Why this module exists

Whatever advances a piece of work is not always the domain that work belongs
to. An operator validates, from the back office domain, an order placed on the
public site. Cron releases a hold with no active domain at all. A queue worker
mails a customer hours after the fact.

Rendering then inherits the context of the request doing the work: the
confirmation email links to the back office, shows the back office site name,
and reads the back office per-domain configuration.

## What it does

```php
$render_context = \Drupal::service('domain_render_context.renderer');

$render_context->inDomain('public_example_com', function () use ($order) {
  // Inside here:
  //   config('system.site')->get('name') is the public site's name,
  //   Url::fromRoute(..., ['absolute' => TRUE]) points at the public site,
  //   the domain path prefix of the public site is applied,
  //   the public site's language negotiation configuration applies,
  //   and the active theme is the public site's own theme.
  $this->mailer->send($order);
});
```

The previous context is restored when the callback returns, including when it
throws. `enter()` is the lower-level counterpart for work that does not fit in
one callback: it returns the closure that restores the previous context, and
that closure is safe to call more than once.

```php
$restore = $render_context->enter($domain_id);
try {
  // ...
}
finally {
  $restore();
}
```

An unknown machine name is logged and the current context is kept, so a
deleted domain never turns a notification into a fatal error.

## Two contexts, not one

Switching the **domain negotiation context** carries most of the load. Per-
domain configuration overrides read the active domain from it
(`DomainConfigFactoryOverride::loadOverrides()`), and so does the outbound path
processor that prepends the domain path prefix
(`DomainPrefixPathProcessor::processOutbound()`), which is why the prefix and
the target domain's language prefix both follow.

What it does not carry is the host. `UrlGenerator::generateFromRoute()` reads
the scheme, host and port from core's `router.request_context`, and the only
thing in Domain that overrides them is `DomainPathProcessor`, which returns
early unless a `domain` option is set on the individual URL. Switching the
negotiation context alone therefore yields a URL with the right prefix, the
right language and the wrong host:

```text
active host : public.example.com
target      : inbox_example_com (inbox.example.com)

after switching the negotiation context only
  site name : Operator inbox
  URL       : https://public.example.com/en/user/login
```

So this module switches the scheme, host and port of the request context too,
and restores both contexts afterwards.

## The theme

A multi-domain site routinely gives each domain its own theme, stored as a
per-domain `system.theme` override (that is what `domain_theme_switch` writes
too). The active theme is negotiated once per request, so without a switch an
email or a PDF built while another domain is active renders with that domain's
templates, assets and logo.

The theme is therefore switched last, after the configuration context, so it
is read through the render domain's own overrides whatever module wrote them.
A theme that fails to initialize is logged and the current one kept: a broken
theme must not stop a notification going out. A mail theme applied inside the
callback (Mail System, say) still wins, since it switches later.

## What it deliberately does not switch

The interface language, the session and the current user. It does not push a
request onto the request stack either, which would drag in route matching,
session handling and inbound path processing.

## Limitations

!!! warning "Outbound only"

    Never call this while a page is being routed or rendered for the browser.
    The negotiated domain also drives inbound path processing, routing and
    language negotiation for the page being served, so switching it mid-request
    breaks language prefixes and path prefix domains. This service is for
    output built outside the request it belongs to.

* Unrouted URLs built from a `base:` or non-routed `internal:` URI are
  assembled straight from the current request
  (`UnroutedUrlAssembler::buildLocalUrl()`), not from the request context, so
  they keep the current host. Routed URLs (`Url::fromRoute()`,
  `$entity->toUrl()`, the `[site:url]` token) follow the switch.
* A service that memoizes configuration or a rendered value for the whole
  request keeps whatever it computed before the switch.
* When the negotiation context held no domain at all on entry, which is the
  normal state of a CLI or cron run, it cannot be emptied again on restore:
  `DomainNegotiationContext::setDomain()` does not accept NULL. The restore
  re-negotiates from the current request instead, which is the state the next
  read would have produced had the service never been called. What matters in
  practice is that the render domain's configuration overrides do not stay
  active for the rest of the run.

## Alternative for a single link

Domain offers a per-URL option that needs no context switch:

```php
$url = Url::fromRoute('my_module.cancel', ['order' => 1], ['domain' => $domain]);
```

`DomainPathProcessor` rewrites the base URL, applies the path prefix and
re-runs language negotiation for the target domain. Use it for a link built in
isolation. It does not cover configuration reads, an attached PDF, tokens
rendered inside a template, or anything a third-party subscriber generates.

## Compatibility

The service uses only API available from Domain 3.0 through 4.x
(`DomainNegotiationContext`, `DomainNegotiatorInterface::getActiveDomain()`)
and calls nothing deprecated for removal in Domain 4.0.
