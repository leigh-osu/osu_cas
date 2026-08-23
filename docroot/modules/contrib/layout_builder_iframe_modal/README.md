# Layout Builder Iframe Modal

Render Laybout Builder block edit forms in an iframe, using the admin theme.

## Motivation
Layout Builder uses the off-canvas for every section or block form, while also
rendering the entire layout page in the frontend theme. This approach works
for simple forms with a few text fields, where styling is easy. But when using
more complex form elements like tabs, accordion, entity autocomplete,
media library or entity browser, the amount of work required to get everything
to look (and work) good can be huge.

This module fixes this problem by rendering any layout builder forms inside an
iframe, with your admin theme.

## Features
- Completely isolated edit forms - no more CSS leaking
- forms are rendered in the site's admin theme
- Integrates seamlessly in the existing LB interface
- Closes modal when the block is saved successfully
- Scrolls to the previous position in the layout after the modal is closed
- Works with translations (layout_builder_st)
- Provides a new "Rebuild" action in the layout form

## How does it work?
At its core, it provides a new Ajax renderer, which is based on
DialogRenderer. But instead of rendering the actual block edit form, it renders
an iframe, with the path of the form as the iframe source. It also appends a
destination query parameter to this url.

The custom ajax command handles setting up the dialog and attaches event
listeners to the iframe.

When submitting the block edit form and there were no errors, Drupal redirects
to the page provided in the destination parameter. This page includes a single
line of JavaScript which uses postMessage to notify the iframe's parent window.
The parent window listens for messages and will then close the modal and
trigger a rebuild of the Layout, so that it reflects the updated content.

## Requirements
None, it should work with any standard Layout Builder setup.

## Differences to layout_builder_modal
layout_builder_modal also shows the forms in a modal, but it renders them
directly in the page, using templates from your frontend theme and without any
CSS from the admin theme. This is the same behaviour as core Layout Builder.

This module just renders an iframe that contains the form rendered by Drupal in
the admin theme.
