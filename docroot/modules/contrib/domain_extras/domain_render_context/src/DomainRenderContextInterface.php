<?php

namespace Drupal\domain_render_context;

use Drupal\domain\DomainInterface;

/**
 * Renders out-of-band output in the context of a given domain.
 *
 * Whatever advances a piece of work is not always the domain that work belongs
 * to: an operator validates on the back office an order placed on the public
 * site, cron releases a booking with no active domain at all, a queue worker
 * mails a customer hours later. Rendering then inherits the context of the
 * request doing the work, so the message carries the wrong links, the wrong
 * site name and the wrong per-domain configuration. This service runs a
 * callback as if the request had come from the domain the work belongs to.
 *
 * It switches exactly three things and nothing else:
 * - The domain negotiation context, which drives per-domain configuration
 *   overrides, the domain path prefix and the target domain's own language
 *   negotiation configuration.
 * - The scheme, host and port of the router request context, which is where
 *   URL generation reads the host of an absolute URL from.
 * - The active theme, read from the render domain's own system.theme
 *   configuration, so rendered output uses that domain's templates, assets
 *   and logo rather than those of the request doing the work.
 *
 * It does not switch the interface language, the session or the current user,
 * and it does not push a request onto the request stack.
 *
 * @see \Drupal\domain\DomainNegotiationContext
 * @see \Drupal\Core\Routing\RequestContext
 */
interface DomainRenderContextInterface {

  /**
   * Runs a callback as if the request had come from the given domain.
   *
   * Only for output built outside the request it belongs to: an email, a PDF,
   * a queue item. Never call it while a page is being routed or rendered for
   * the browser, since the negotiated domain also drives inbound path
   * processing, routing and language negotiation for the page being served.
   *
   * @param \Drupal\domain\DomainInterface|string $domain
   *   The domain to render in, or its machine name. An unknown machine name
   *   is logged and the current context is kept, so a deleted domain never
   *   turns a notification into a fatal error.
   * @param callable $callback
   *   The callback to run. It receives no arguments.
   *
   * @return mixed
   *   Whatever the callback returns.
   */
  public function inDomain(DomainInterface|string $domain, callable $callback): mixed;

  /**
   * Enters a domain context and returns the closure that restores it.
   *
   * The lower-level counterpart of inDomain(), for work that does not fit in
   * one callback (a queue worker switching for the whole of processItem(),
   * say). The caller is responsible for calling the returned closure, and
   * should do so in a `finally` block. Calling it more than once is safe: only
   * the first call restores anything.
   *
   * Nested calls restore correctly as long as the closures are called in
   * reverse order of the calls that produced them.
   *
   * @param \Drupal\domain\DomainInterface|string $domain
   *   The domain to render in, or its machine name.
   *
   * @return \Closure
   *   A closure taking no arguments that restores the previous context.
   */
  public function enter(DomainInterface|string $domain): \Closure;

}
