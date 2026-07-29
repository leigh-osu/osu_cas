# Presentation

Various utilities related to the Domain module.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/domain_extras).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/domain_extras).

## Requirements

This module requires no modules outside of Drupal core and the Domain module
itself.

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Included modules

### [Domain Access Linkit](domain_access_linkit/index.md) (domain_access_linkit)


### Domain SSO (domain_sso)

Allows single sign-on on domains when the session cookies cannot be shared.

Once logged in on the default domain, the user can be logged in on any other
domains by clicking on the **SSO** menu link.

### Domain Maintenance Mode (domain_maintenance)

Allows administrators to activate maintenance mode individually for each domain.

### [Domain Early Negotiation](domain_early_negotiation/index.md) (domain_early_negotiation)

Negotiates the active domain early in the middleware stack so that
`domain_config` overrides are available to other middlewares. Enabling the
module activates the feature -- no separate toggle needed.

### Domain Config Extras (domain_config_extras)

Various utilities related to the **Domain Config** module.

#### Service "domain_config_extras.utilities"

- **loadAllDomainOverrides**:<br>
  Return config overrides across all (or only active) domains.
