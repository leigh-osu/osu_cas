# Toc.js, a client-side table of contents generator

The Toc.js module embeds a javascript library that automatically generates a
table of contents from your page headings on the client side.

This module provides :

- An extra field available on nodes. This extra field displays a Toc based on
  the Toc settings defined in the node type configuration.
- A block which can generate a Toc for any kind of page.

The Table of contents is generated client-side in the browser and allows:
- Smooth scrolling
- Highlight on scroll
- Back-to-top links
- Custom configuration for the container and the heading you want to target
- And more...

You can put as many Toc, with specific settings, as you need on one page.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/toc_js).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/toc_js).


## Requirements

This module requires no modules outside of Drupal core (Node and Block).


## Installation

Install as you would normally install a contributed Drupal module.
For further information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

- Enable the module at Administration > Extend.

For the Toc.js Block:

- Place a Toc.js block and configure the Toc settings. Toc settings are the same
  as for nodes. See below.

For nodes:

- Enable the Table of contents on the content type you want to place a TOC on
- Configure the Toc settings
- In the manage display page configuration of this content type, place the
  TOC extra field in the position you want to display it, in any view mode you
  need.

The Toc.js settings available are :

- Title: The title displayed on top of the Toc list.
  Default: "Table of contents".
- Selectors: Comma-separated list of selectors for elements to be used as
  headings. Default: `h2,h3`.
- Container: Element to find all selectors in. Default: `.node`.
- Prefix: Prefix for anchor tags and class names. Default: `toc`.
- List type: The HTML list type to use (`ul` or `ol`).
- CSS Classes: Add additional css classes.
- Back to top: Add back to top-link on heading.
- Back to top label: The back to top link label.
- Smooth scrolling: Enable or disable smooth scrolling on click.
- Highlight on scroll: Add a class to heading that is currently in focus.
- Highlight offset: Offset to trigger the next headline.

This module contains a submodule "Toc.js per node" which permits
enabling/disabling of the table of contents on a per-node basis.
Once this submodule is enabled, you must allow the override per node for the
content type.

You have a new Toc.js settings :
- Permit to enable/disable toc per node

Once this option is checked, you can for each node disable the generated TOC.


## Similar modules

- The [TOC API](https://www.drupal.org/project/toc_api) module provides the API
  to generate a TOC based on a
  configuration entity type. TOC are generated on the backend.
- The [TOC filter](https://www.drupal.org/project/toc_filter) module uses the
  TOC API and can generate a TOC for a body field using a filter on the body.
- The [TOC API Node](https://www.drupal.org/project/toc_api_node) uses the TOC
  API module and generates a TOC on a whole node, always from the backend.
- [TOC Formatter](https://www.drupal.org/project/toc_formatter) sets a TOC to
  the top of a text area field. This module uses PHP DOMDocument to manipulate
  content (Drupal 7 only).
- See the [Comparison of Table of Contents / TOC modules](https://www.drupal.org/node/2278811)


Toc.js has a more lightweight approach than these modules and delegates the
most part of the work to an awesome internal javascript library, which comes
with native options (selectors, containers, smooth scrolling,
highlight-on-scroll).


## Maintainers

### Currents maintainers

- [flocondetoile](https://drupal.org/u/flocondetoile)
- [mably](https://drupal.org/u/mably)
- [vladimiraus](https://drupal.org/u/vladimiraus)
