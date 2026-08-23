# OSU Remediation Module

This module provides a workflow for accessibility remediation requests.
A new state is added to the Editorial workflow,
Preserve—Public Record Exposed; Substitute Provided Upon Remediation Request—and
a new transition is added to the workflow. This is only required on sites
created before April 24, 2026.

## Installation

1. Enable the module via the UI or config.
2. Run database updates:
   drush updatedb
3. Every existing node type will have a new field added to it.
   This field is not rendered normally and will be used as part of our Preserve
   workflow.

## Removal

When this feature is no longer needed, removal must follow this exact sequence
to avoid data loss or schema conflicts:

### Before uninstalling

1. Export any accessibility remediation email data you need to retain.
2. Confirm with stakeholders that all sites have addressed outstanding
   remediation requests.

### Removal steps

1. Remove the Preserve—Public Record Exception workflow state and transition
   from your workflow configuration in the Drupal UI or config.
2. Uninstall this module:
   drush pmu osu_a11y_remediation
   This will automatically remove the field storage and all associated data
   via hook_uninstall().
3. Remove the module files from the codebase.
4. Export and commit config.

### Do not

- Delete the field through the UI (it will not remove the base field storage)
- Remove the module files before uninstalling (hook_uninstall will not run)
