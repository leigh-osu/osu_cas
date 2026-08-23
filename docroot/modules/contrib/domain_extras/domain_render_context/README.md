# Domain Render Context

Renders out-of-band output (an email, a PDF, a queue item) as if the request
had come from another domain, so links, per-domain configuration and path
prefixes match the domain the work belongs to rather than the request doing
the work.

## The problem

Whatever advances a piece of work is not always the domain that work belongs
to:

* an operator validates, from the back office domain, an order placed on the
  public site;
* cron releases a booking with no active domain at all;
* a queue worker mails a customer hours after the fact.

Rendering then inherits the context of the request doing the work, so the
message carries the wrong links, the wrong site name and the wrong per-domain
configuration.

## Usage

```php
$render_context = \Drupal::service('domain_render_context.renderer');

// Or, injected: Drupal\domain_render_context\DomainRenderContextInterface.
$body = $render_context->inDomain($order->getDomainId(), function () use ($order) {
  return [
    'site' => \Drupal::config('system.site')->get('name'),
    'link' => Url::fromRoute('my_module.cancel', ['order' => $order->id()], ['absolute' => TRUE])
      ->toString(),
  ];
});
```

For work that does not fit in one callback, `enter()` returns the closure that
restores the previous context:

```php
$restore = $render_context->enter($domain_id);
try {
  // ...
}
finally {
  $restore();
}
```

## What it switches

* The **domain negotiation context**, which drives per-domain configuration
  overrides (site name, site mail, anything `domain_config` registers), the
  domain path prefix, and the target domain's own language negotiation
  configuration.
* The **scheme, host and port of the router request context**, which is where
  URL generation reads the host of an absolute URL from.
* The **active theme**, read from the render domain's own `system.theme`
  configuration, so rendered output uses that domain's templates, assets and
  logo. A multi-domain site routinely gives each domain its own theme, and
  without this an email or a PDF built while another domain is active renders
  with the wrong one. A mail theme applied inside the callback (Mail System,
  say) still wins, since it switches later.

Nothing else. It does not switch the interface language, the session or the
current user, and it does not push a request onto the request stack.

## Limitations

* **Outbound only.** Never call it while a page is being routed or rendered
  for the browser: the negotiated domain also drives inbound path processing,
  routing and language negotiation for the page being served.
* Unrouted URLs built from `base:` or a non-routed `internal:` URI are
  assembled straight from the current request rather than from the request
  context, so they keep the current host. Routed URLs (`Url::fromRoute()`,
  `$entity->toUrl()`, the `[site:url]` token) follow the switch.
* A service that memoizes configuration or a rendered value for the whole
  request keeps whatever it computed before the switch.

## Compatibility

Uses only API available from Domain 3.0 through 4.x, and calls nothing
deprecated for removal in Domain 4.0.

## Related

For a single link, Domain itself offers a per-URL alternative that needs no
context switch:

```php
$url = Url::fromRoute('my_module.cancel', ['order' => 1], ['domain' => $domain]);
```

That covers URLs you build yourself. It does not cover configuration reads, an
attached PDF, tokens rendered inside a template, or anything a third-party
subscriber generates, which is what this module is for.
