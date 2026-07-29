# Domain Extras

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

### Domain SSO (domain_sso)

Allows single sign-on on domains when the session cookies cannot be shared.

Once logged in on the default domain, the user can be logged in on any other
domains by clicking on the **SSO** menu link.

### Domain Maintenance Mode (domain_maintenance)

Allows administrators to activate maintenance mode individually for each domain.

### Domain Config Extras (domain_config_extras)

Various utilities related to the **Domain Config** module.

#### Service "domain_config_extras.utilities"

- **loadAllDomainOverrides**:<br>
  Return config overrides across all (or only active) domains.

### Domain Content Extras (domain_content_extras)

Various utilities related to the **Domain Content** module.

Provides quick access to all affiliated content on the content overview
page via a new 'Affiliated Content' local task.

A sub-list of local tasks are then made available containing a list of 
your configured domains. Clicking one of these tasks will show the domain 
specific content for that domain.
