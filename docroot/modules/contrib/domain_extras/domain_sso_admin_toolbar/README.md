# Domain SSO Admin Toolbar

This module provides a domain switcher in the Drupal admin toolbar that allows
authenticated users to quickly switch between domains with automatic Single
Sign-On (SSO) login.

## Features

- Adds a "Domains" dropdown menu to the admin toolbar
- Lists all available domains in the dropdown
- Highlights the current domain
- Automatically logs you into the target domain using the existing domain_sso
  infrastructure
- Preserves your current path when switching domains (when possible)

## Requirements

- Drupal 10.2+ or Drupal 11
- domain module (from the Domain project)
- domain_sso module (from domain_extras)
- admin_toolbar module

## Installation

1. Ensure all dependencies are installed and enabled
2. Enable the module:
   ```bash
   drush en domain_sso_admin_toolbar
   ```
3. Clear the cache:
   ```bash
   drush cr
   ```

## Usage

Once enabled, authenticated users with the "access toolbar" and
"use domain sso admin toolbar" permissions will see a new "Domains" item in
their admin toolbar (next to the user account menu).

1. Click on the "Domains" menu item
2. Select the domain you want to switch to from the dropdown
3. You will be automatically redirected and logged into the selected domain
4. The module will attempt to preserve your current path on the new domain

## How it Works

The module integrates with the existing domain_sso SSO handshake system:

1. When you click a domain in the dropdown, the module redirects to the SSO
   issue endpoint
2. The issue endpoint generates a secure, time-limited token for your user
   account
3. You are redirected to the target domain's consume endpoint with the token
4. The consume endpoint validates the token and logs you in automatically
5. You are redirected to your destination on the new domain

All security features of domain_sso are preserved:
- HMAC-SHA256 signed tokens
- 60-second token expiration
- One-time use nonces
- Replay attack prevention

## Permissions

Users need the "access toolbar" permission to see and use the domain switcher.

## Troubleshooting

**The Domains menu doesn't appear:**
- Verify you are logged in and have the "access toolbar" permission
- Check that multiple domains are configured (the menu only shows if there's
  more than one domain)
- Clear the cache: `drush cr`

**Getting access denied when switching:**
- Verify domain_sso is properly configured
- Check that both domains share the same Drupal database and hash_salt
- Ensure cookies can be set across domains

**Token validation errors:**
- Ensure all domains use the same hash_salt in settings.php
- Check that system time is synchronized across servers

## Technical Details

### Files Structure
```
domain_sso_admin_toolbar/
├── css/
│   └── toolbar_domain_switcher.css
├── src/
│   └── Controller/
│       └── DomainSwitchController.php
├── tests/
│   └── src/
│       └── Functional/
│           └── DomainSsoAdminToolbarTest.php
├── domain_sso_admin_toolbar.info.yml
├── domain_sso_admin_toolbar.libraries.yml
├── domain_sso_admin_toolbar.module
├── domain_sso_admin_toolbar.permissions.yml
├── domain_sso_admin_toolbar.routing.yml
└── README.md
```

### Hooks Implemented
- `hook_toolbar_alter()`: Adds the domain switcher to the toolbar

### Routes
- `domain_sso_admin_toolbar.switch`: Handles domain switching requests

## Contributing

This module is part of the domain_extras package. Please report issues and
contribute patches through the domain_extras issue queue on Drupal.org.

## License

This module is licensed under GPL v2 or later, matching Drupal core.
