# CKEditor 5 Link Styles

Provides the ability to select styles (css classes) for links within CKEditor5

## Features:
- Link styles can be selected as the editor adds or edits a link, avoiding multiple steps.
- Allows site builder to define predefined classes that can be added to links.
- Multiple link styles can be enabled at once
- Can work alongside other modules that interact with CKEditor's link plugin, like linkit and editor_advanced_link.
- Avoids use of styles dropdown. See note below.

## Installation & Configuration:

1. Install & Enable the module
2. Open Administration > Configuration > Content authoring >
   Text formats and editors (admin/config/content/formats)
3. Edit a text format's settings, like Full HTML
4. Drag the "Link" toolbar button to the toolbar if it is not there already.
5. Add link styles to the "Link styles" tab using the same format as the Styles configuration,
   `a.classA.classB|Label`. For example: `a.btn|Button`
6. Save


## Notes

By using [CKEditor 5 Link plugin's decorator config](https://ckeditor.com/docs/ckeditor5/latest/features/link.html), 
this module overcomes usability issues when trying to use the styles dropdown to apply classes to links. 

See https://github.com/ckeditor/ckeditor5/issues/11709 and https://www.drupal.org/project/drupal/issues/3334617