# Paragraph View Mode

This module allows to dynamically pick the display mode
of the paragraph during adding/editing the paragraph item,
by creating a field with available view modes.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/paragraph_view_mode).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/paragraph_view_mode).


## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers


## Requirements

Paragraph view mode module extend the functionality
of the paragraphs module, so to use this module,
you need to download/install the Paragraph module first.


## Installation

1. Extract the tar.gz into your `'modules'` or directory and copy to modules
   folder.
2. Go to `"Extend"` after successfully login into admin.
3. Enable the module at `'administer >> modules'`.


## Configuration

1. To enable the paragraph view mode field for your paragraph type, navigate
   to `/admin/structure/paragraphs_type`.
2. Click on "Edit" (in the operations column) for the desired paragraph type.
3. Check the option `"Enable paragraph view mode field on this paragraph type."`.
4. Click "Save".
5. Proceed to the tab `"Manage form display"`.
6. Arrange the `Paragraph view mode"` field anywhere you prefer (excluding the
   disabled section).
7. Customize widget settings by selecting the view modes you wish to allow
   during content creation or editing.
8. Set a default value for the "Paragraph view mode" field.
9. By default, the option "Bind with the form mode" will be checked. This
   allows the paragraph form to reload via AJAX only if there exists a form
   mode with precisely the same machine name as the view mode.
10. By default, the "Apply to preview mode" option remains unchecked to avoid
    interference with paragraph previews. However, in specific scenarios, you
    may choose to enable it. This option allows overriding the default
    behavior, treating the preview mode as a special mode that should not be
    overridden. Nonetheless, users may need to disable this default behavior
    under exceptional circumstances, thereby permitting overrides.


## Maintainers

cspell:disable-next-line
- Mariusz Andrzejewski - [sayco](https://www.drupal.org/u/sayco)
