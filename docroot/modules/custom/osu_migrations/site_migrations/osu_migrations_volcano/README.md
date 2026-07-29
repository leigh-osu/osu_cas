# Template for site-specific migrations

Copy this directory and rename `template` with a short name of the site.

Migrations specific to that site will be included here. Any custom Source/Process plugins can be created and referenced
here.

## Checklist

It is recommended to create lists here for the site and update them during the migration process.

### Example List

#### Node types with Media/Files/Entity References

- [x] News
    - Entity Reference to Volcano's

#### Node types with Paragraph fields

Listing the dependencies of the type can help you understand what needs to be configured for the migration.

- [x] fieldtrip_stop
    - Uses Paragraphs
    - Entity reference to content
      - field_picture_with_text
- [x] Volcano
    - Paragraphs
      - Turns out not used.
    - File
    - ER to taxonomy terms

## Paragraph Types

- [x] picture_with_text
- [x] body_with_title
- [x] fieldtrip_stop
- [x] teaser_with_text
