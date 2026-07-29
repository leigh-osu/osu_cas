# EVA

`EVA` (Entity Views Attachment) is a Drupal module that allows you to attach
the output of a View to the content of any Drupal entity.
This includes nodes, comments, user profiles, taxonomy term pages, and more.

With EVA, Views can be placed in the entity's content like other fields
added through the Field UI module.
The placement can be reordered on the view display settings page.

EVA also passes the unique ID of the entity
(and any tokens generated from that entity) as arguments to the attached View.
For example, you can create a View that filters posts by 'Author ID' and attach it
to the User entity type, so that when a user profile is displayed,
the View automatically receives the User ID as an argument.

**Note:** EVA fields are attached as pseudo-fields.
This legacy mechanism may not fully support all Field UI features
(default configuration, third-party settings, etc.).
If you need a View to behave exactly like a field, consider other solutions.

For full details, visit the [project page](https://www.drupal.org/project/eva).
Report bugs or request features on the [issue queue](https://www.drupal.org/project/issues/eva).

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Known Issues](#known-issues)
- [Maintainers](#maintainers)

---

## Requirements

- **Drupal 11, 10 or 9**
- **Views module** (included in Drupal core)
- No other contributed modules are required.

---

## Installation

Install EVA like any other Drupal module:

1. Download the module from [Drupal.org](https://www.drupal.org/project/eva) or via Composer:

   ```bash
   composer require drupal/eva
   ```

2. Enable the module:

   ```bash
   drush en eva -y
   ```

3. Configure your Views and attach them to entities as needed.

For more information on installing Drupal modules,
see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

---

## Usage

1. Create a View that you want to attach to an entity.
2. In the View display settings, choose "Entity View Attachment (EVA)" as the display type.
3. Configure which entity types the view should attach to.
4. Optionally, pass entity ID or tokens as contextual filters to customize the view for each entity.
5. EVA attachment will be added by default to all entity displays.
You can disable this in the settings.
IMPORTANT: When selecting a format in your EVA display settings,
if you choose to show rendered entities, make sure the selected view mode
does not include the EVA field itself. Otherwise, this can cause an infinite loop.
6. Reorder the EVA display like any other field on the entity display page.

---

## Known Issues

- EVA uses pseudo-fields; some features in Field UI may not work as expected.
- Default configuration and third-party settings might not always be fully supported.
- If you need Views to behave like full-fledged fields, consider alternative modules.

---

## Maintainers

- Vitaliy Bogomazyuk - [vitaliyb98](https://www.drupal.org/u/vitaliyb98)
- Andy Hebrank - [ahebrank](https://www.drupal.org/u/ahebrank)
- Larry Garfield - [Crell](https://www.drupal.org/u/crell)
- Jeff Eaton - [eaton](https://www.drupal.org/u/eaton)
- Merlin Axel Rutz - [geek-merlin](https://www.drupal.org/u/geek-merlin)
- Mike Kadin - [mkadin](https://www.drupal.org/u/mkadin)
