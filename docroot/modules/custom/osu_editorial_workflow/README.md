# OSU Editorial Workflow Module

The OSU Editorial Workflow module is designed to ensure that all required
modules for an effective editorial workflow are installed and necessary
permissions are assigned. This module simplifies the process of setting up a
comprehensive editorial workflow in your Drupal site.

## Features

- Automatically installs and enables required modules.
- Assigns essential permissions to roles for managing content.

## Requirements

- Drupal 10 or higher

## Installation

1. **Download the module**: You can download the OSU Editorial Workflow module
   from [Drupal.org](https://www.drupal.org/project/osu_editorial_workflow) or
   using Composer:
    ```bash
    composer require drupal/osu_editorial_workflow
    ```

2. **Enable the module**: Enable the module using Drush or via the Drupal admin
   interface:
    ```bash
    drush en osu_editorial_workflow -y
    ```

## Configuration

After enabling the module, all required sub-modules will be enabled, and
permissions will be automatically set. You can review and adjust these settings
if necessary in the Drupal admin interface.

## Maintainers

- [Matthew Brabham](https://uit.oregonstate.edu/people/matthew-brabham)

## License

This project is licensed under the GNU General Public License, version 2 or
later.
