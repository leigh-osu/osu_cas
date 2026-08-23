# Image field to media


## Introduction

The "Image field to media" module helps to convert existing image fields to
Media fields. The module adds a new Media field to the bundle and updates all
entities. After that, each entity will have the Media field populated with the
same image items that the Image field has. To prevent of duplicate creation,
sha1 hash of image files compares. Since the release 2.0.1 it's possible to add
images to the existing Media field. In this case a new Media field does not
created.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/image_field_to_media).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/image_field_to_media).

## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

For the module to work, the following conditions must be met:
1. The "Image" media type should exist in your system.
2. The "Image" media type should have the field of the "Image" field type with
the machine name "field_media_image".

So check if they exist, and if not, then create them. You can do it from UI
by visiting "/admin/structure/media/add"
Also, you can copy missing config files from
"/core/profiles/standard/config/optional" to the related folder of your profile.


## How to Use

1. Visit "Manage fields" tab and choose the Image field you want to migrate to
   Media.
2. Click on the "Clone to media" operation.
3. Select one of the two options:
  a) Create a new Media Image field;
  b) Reuse an existing Media field.
4. Depending on your choice, either enter the name of the Media field to be
   created or select an existing one from the list.
5. Click on the "Proceed" button.


## Maintainers

- Andrey Vitushkin (wombatbuddy) - https://www.drupal.org/u/wombatbuddy
