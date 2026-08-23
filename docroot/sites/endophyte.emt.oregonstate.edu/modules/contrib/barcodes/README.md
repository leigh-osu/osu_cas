# Barcodes
The Barcodes module provides a Field Formatter for various field types, a Block plugin, Token module support, and a Twig Filter to display various field types as rendered Barcodes.

Drush commands are also provided to manually work with Barcodes from the command line.

More details, and examples, may be found in the [Documentation Guide](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/barcodes).

**Available Barcode Types**
* C39        : CODE 39 - ANSI MH10.8M-1983 - USD-3 - 3 of 9
* C39+       : CODE 39 with checksum
* C39E       : CODE 39 EXTENDED
* C39E+      : CODE 39 EXTENDED + CHECKSUM
* C93        : CODE 93 - USS-93
* S25        : Standard 2 of 5
* S25+       : Standard 2 of 5 + CHECKSUM
* I25        : Interleaved 2 of 5
* I25+       : Interleaved 2 of 5 + CHECKSUM
* C128       : CODE 128
* C128A      : CODE 128 A
* C128B      : CODE 128 B
* C128C      : CODE 128 C
* EAN2       : 2-Digits UPC-Based Extension
* EAN5       : 5-Digits UPC-Based Extension
* EAN8       : EAN 8
* EAN13      : EAN 13
* UPCA       : UPC-A
* UPCE       : UPC-E
* MSI        : MSI (Variation of Plessey code)
* MSI+       : MSI + CHECKSUM (modulo 11)
* POSTNET    : POSTNET
* PLANET     : PLANET
* RMS4CC     : RMS4CC (Royal Mail 4-state Customer Code) - CBC (Customer Bar Code)
* KIX        : KIX (Klant index - Customer index)
* IMB        : IMB - Intelligent Mail Barcode - Onecode - USPS-B-3200
* IMBPRE     : IMB - Intelligent Mail Barcode - Onecode - USPS-B-3200- pre-processed
* CODABAR    : CODABAR
* CODE11     : CODE 11
* PHARMA     : PHARMACODE
* PHARMA2T   : PHARMACODE TWO-TRACKS
* AZTEC      : AZTEC Code (ISO/IEC 24778:2008)
* DATAMATRIX : DATAMATRIX (ISO/IEC 16022)
* PDF417     : PDF417 (ISO/IEC 15438:2006)
* QRCODE     : QR-CODE
* RAW        : 2D RAW MODE comma-separated rows
* RAW2       : 2D RAW MODE rows enclosed in square parentheses

## Configuration
* Add a field of one of the types of email, integer, link, string, telephone,
* text, text_long, text_with_summary, bigint, or uuid.
* Choose Barcode as formatter.
* Adjust the settings like type, color and dimensions to your liking.

## Template / TWIG
Alternatively, you may use a Twig filter to display any string as a barcode.
```
  {{ "any string" | barcode(type='QRCODE', color='#000000', height=100, width=100, padding_top=0, padding_right=0, padding_bottom=0, padding_left=0, format='svg') }}
```
You may specify any or all of these values, in any order. The ones you don't
specify default to the values shown above. So to use just the default values
with no customization we would write:
```
        {{ "any string" | barcode }}
```
If you want to override just a few of the settings, pass just those settings
as arguments to the filter - the other values will be set to their defaults.
For example:
```
        {{ "any string" | barcode(color='red') }}
```
or
```
        {{ "any string" | barcode(type='png') }}
```
or
```
        {{ "any string" | barcode(width=720, height=480) }}
```

## Optional dependencies
* [Composer manager](https://drupal.org/project/composer_manager) (Drupal 7.x)
  You may use composer manager module to manage external dependencies.
* [Token](https://drupal.org/project/token) (Drupal 7.x / 8.x)
  You may use Token module, if you need token replacement functionality in your
  barcode data.

## Dependencies
The Barcodes module integrates the [tecnickcom/tc-lib-barcode PHP barcode library](https://github.com/tecnickcom/tc-lib-barcode/blob/main/README.md) 
into Drupal. More information about this library may be found on that project
page on GitHub.
* No further system dependencies, just PHP and Drupal.
* No external service dependencies.
* No special font dependencies.
