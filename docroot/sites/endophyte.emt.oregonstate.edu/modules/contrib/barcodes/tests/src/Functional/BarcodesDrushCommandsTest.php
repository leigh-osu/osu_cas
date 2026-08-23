<?php

declare(strict_types=1);

namespace Drupal\Tests\barcodes\Functional;

use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * @coversDefaultClass \Drupal\barcodes\Drush\Commands\BarcodesDrushCommands
 *
 * @group barcodes
 */
class BarcodesDrushCommandsTest extends BrowserTestBase {
  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['barcodes'];

  /**
   * Tests the Drush barcodes:generate command.
   */
  public function testGenerateCommand(): void {
    // Creates dummy voting data for the specified entity type, each with a
    // maximum age of 60 seconds.
    $this->drush('barcodes:generate', ['023130'], ['type' => 'codabar', 'format' => 'png']);
    $expected_output = '<img alt="Embedded Image" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkAQMAAABKLAcXAAAABlBMVEX///8AAABVwtN+AAAAAXRSTlMAQObYZgAAAAlwSFlzAAAOxAAADsQBlSsOGwAAACNJREFUOI1j2CiyOGO3yNLTs67NNTRgGOWN8kZ5o7xRHo15AOGSiPPiFchBAAAAAElFTkSuQmCC" />';
    $this->assertEquals($expected_output, $this->getOutput());

    // phpcs:ignore Drupal.Arrays.Array.LongLineDeclaration
    $this->drush('barcodes:generate', ['0xABADCAFE'], ['type' => 'qrcode', 'height' => 250, 'width' => 250, 'format' => 'png']);
    $expected_output = '<img alt="Embedded Image" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAPoAAAD6AQMAAACyIsh+AAAABlBMVEX///8AAABVwtN+AAAAAXRSTlMAQObYZgAAAAlwSFlzAAAOxAAADsQBlSsOGwAAAQtJREFUaIHt2M0NwyAMBWBQBskorM4oDBLFjW1w3N9TMZdnRT3Al0ufMIREVi2llKlu/FjVBPA3wOMy1eRRUHVwA4gFWZIagA4Zl7wqwCpgAQEsB3k0LoBVwDUxHuy963OXA5gKrJrbUKwAAoEr62PfCmAuIDrTfvIs6S/dHYw3eoBAoAFdZtelcqQi66U8bygA84GMj7AuVo77rAXwV8B/v8XBtdmK0FAA4kDL/faDJLUy1kXHAHHAlTvTcqfyEiACkJWmpm+51wHiQBtJtRGahahhAcSBt/soIncNArAE6CeGO82+hgUQBvQA/PPOHGAusCZm97RpfIPwBEAcsNK1I4N9c++XJAAx4AGlN9+hKmcGrAAAAABJRU5ErkJggg==" />';
    $this->assertEquals($expected_output, $this->getOutput());
  }

  /**
   * Tests the Drush barcodes:formats command.
   */
  public function testFormatsCommand(): void {
    $this->drush('barcodes:formats');
    // cspell:disable
    $expected_output =
      "-------------- -------------------------------------------------------- " . "\n" .
      "  Abbreviation   Description                                             " . "\n" .
      " -------------- -------------------------------------------------------- " . "\n" .
      "  C128           CODE 128                                                " . "\n" .
      "  C128A          CODE 128 A                                              " . "\n" .
      "  C128B          CODE 128 B                                              " . "\n" .
      "  C128C          CODE 128 C                                              " . "\n" .
      "  C39            CODE 39 - ANSI MH10.8M-1983 - USD-3 - 3 of 9.           " . "\n" .
      "  C39+           CODE 39 + CHECKSUM                                      " . "\n" .
      "  C39E           CODE 39 EXTENDED                                        " . "\n" .
      "  C39E+          CODE 39 EXTENDED + CHECKSUM                             " . "\n" .
      "  C93            CODE 93 - USS-93                                        " . "\n" .
      "  CODABAR        CODABAR                                                 " . "\n" .
      "  CODE11         CODE 11                                                 " . "\n" .
      "  EAN13          EAN 13                                                  " . "\n" .
      "  EAN2           EAN 2-Digits UPC-Based Extension                        " . "\n" .
      "  EAN5           EAN 5-Digits UPC-Based Extension                        " . "\n" .
      "  EAN8           EAN 8                                                   " . "\n" .
      "  I25            Interleaved 2 of 5                                      " . "\n" .
      "  I25+           Interleaved 2 of 5 + CHECKSUM                           " . "\n" .
      "  IMB            IMB - Intelligent Mail Barcode - Onecode - USPS-B-3200  " . "\n" .
      "  IMBPRE         IMB - Intelligent Mail Barcode pre-processed            " . "\n" .
      "  KIX            KIX (Klant index - Customer index)                      " . "\n" .
      "  LRAW           1D RAW MODE (comma-separated rows of 01 strings)        " . "\n" .
      "  MSI            MSI (Variation of Plessey code)                         " . "\n" .
      "  MSI+           MSI + CHECKSUM (modulo 11)                              " . "\n" .
      "  PHARMA         PHARMACODE                                              " . "\n" .
      "  PHARMA2T       PHARMACODE TWO-TRACKS                                   " . "\n" .
      "  PLANET         PLANET                                                  " . "\n" .
      "  POSTNET        POSTNET                                                 " . "\n" .
      "  RMS4CC         RMS4CC (Royal Mail 4-state Customer Bar Code)           " . "\n" .
      "  S25            Standard 2 of 5                                         " . "\n" .
      "  S25+           Standard 2 of 5 + CHECKSUM                              " . "\n" .
      "  UPCA           UPC-A                                                   " . "\n" .
      "  UPCE           UPC-E                                                   " . "\n" .
      "  AZTEC          AZTEC Code (ISO/IEC 24778:2008)                         " . "\n" .
      "  DATAMATRIX     DATAMATRIX (ISO/IEC 16022)                              " . "\n" .
      "  PDF417         PDF417 (ISO/IEC 15438:2006)                             " . "\n" .
      "  QRCODE         QR-CODE                                                 " . "\n" .
      "  SRAW           2D RAW MODE (comma-separated rows of 01 strings)        " . "\n" .
      " -------------- --------------------------------------------------------";
    // cspell:enable
    $this->assertEquals($expected_output, $this->getOutput());
  }

}
