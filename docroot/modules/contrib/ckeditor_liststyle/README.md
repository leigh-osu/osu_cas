# List Style Plugin

## Description
This plugin adds numbered list and ordered list properties dialogs (available
in context menu).

They allow setting:

* list type (e.g. circle, square, dot for bulleted list or decimal, lower/upper
  roman, lower/upper alpha for numbered list)
* start number (for numbered list).

## Known issue
This plugin works only with the text formats supporting full HTML.

Any text format with `Limit allowed HTML tags and correct faulty HTML` filter
enabled will strip the `style` attribute from ANY tags. See [FilterHtml](https://api.drupal.org/api/drupal/core%21modules%21filter%21src%21Plugin%21Filter%21FilterHtml.php/class/FilterHtml) class:

> The 'style' and 'on*' ('onClick' etc.) attributes are always forbidden, and
> are removed by Xss::filter()

However, the CKEditor 4 [List Style](https://ckeditor.com/cke4/addon/liststyle)
plugin sets the list properties via inline styles.

See [#2850288-12]

## Usage
Right click on any numbered or ordered list in CKEditor to open the context
menu.

## Installation
This module requires:

- [List Style](https://ckeditor.com/cke4/addon/liststyle) plugin:
  A CKEditor 4 plugin that adds numbered list and ordered list properties dialogs
  (available in context menu).

### Install using Composer (recommended)

Edit your `composer.json` file, and add the **repository**, **installer type**,
and **installer path** references from following.

Make sure the **installer path** matches to your site's paths.

``` 
{
  "repositories": {
    "ckeditor.liststyle": {
      "type": "package",
      "package": {
        "name": "ckeditor/liststyle",
        "version": "4.17.1",
        "type": "ckeditor-plugin",
        "dist": {
          "url": "https://download.ckeditor.com/liststyle/releases/liststyle_4.17.1.zip",
          "type": "zip"
        }
      }
    }
  },
  "extra": {
    "installer-types": ["ckeditor-plugin"],
    "installer-paths": {
      "web/libraries/ckeditor/plugins/{$name}": ["type:ckeditor-plugin"]
    }
  }
}
```

Run `composer update --lock` to generate an updated `composer.lock` file.

Make sure you already have these packages installed:
- `composer/installers`
- `oomphinc/composer-installers-extender`

Install the CKEditor List Style plugin, and this module:

```shell
composer require ckeditor/liststyle drupal/ckeditor_liststyle
```

### Install manually
- Download the [List Style](https://ckeditor.com/cke4/addon/liststyle) plugin;
  extract the content, and copy to *libraries* folder.
  i.e. `/libraries/liststyle/plugin.js`

- Download [CKEditor List Style](https://www.drupal.org/project/ckeditor_liststyle)
  (this module) and then extract files to `/modules/contrib/ckeditor_liststyle`

## Resources
[Other contributed modules and plug-ins available for CKEditor](https://www.drupal.org/documentation/modules/ckeditor/contrib)

## Maintainers
Osman Gormus - https://www.drupal.org/u/osman
