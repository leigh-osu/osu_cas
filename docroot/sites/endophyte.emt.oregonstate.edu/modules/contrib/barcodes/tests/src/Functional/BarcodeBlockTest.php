<?php

declare(strict_types=1);

namespace Drupal\Tests\barcodes\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Barcodes module block functionality.
 *
 * @coversDefaultClass \Drupal\barcodes\Plugin\Block\Barcode
 *
 * @group barcodes
 */
class BarcodeBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['barcodes', 'block', 'token'];

  /**
   * The block being tested.
   *
   * @var \Drupal\block\Entity\Block
   */
  protected $block;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->block = $this->drupalPlaceBlock('barcode');

    // Set the front page so we aren't automatically redirected to /user/login.
    $this->config('system.site')->set('page.front', '/node')->save();
  }

  /**
   * Tests the "Show value" functionality.
   *
   * @covers ::build
   */
  public function testShowValue(): void {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Test with "Show value" set to TRUE.
    // PNG is used for output because the default SVG output will always contain
    // the value in a <desc> tag, making it difficult to test.
    $this->block->getPlugin()->setConfigurationValue('format', 'PNG');
    $this->block->getPlugin()->setConfigurationValue('value', '01234567890123456789');
    $this->block->getPlugin()->setConfigurationValue('show_value', TRUE);
    $this->block->save();

    $this->drupalGet('');
    // QRCODE is the default barcode format.
    $assert->responseContains('barcode-qrcode');
    $assert->pageTextContains($this->block->label());
    $assert->pageTextContains('01234567890123456789');

    // Now test with "Show value" set to FALSE.
    $this->block->getPlugin()->setConfigurationValue('show_value', FALSE);
    $this->block->save();

    $this->drupalGet('');
    $assert->responseContains('barcode-qrcode');
    $assert->pageTextContains($this->block->label());
    $assert->pageTextNotContains('01234567890123456789');
  }

  /**
   * Tests using a Token module token in the value textfield.
   *
   * @covers ::build
   */
  public function testTokenIntegration(): void {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Set "Value" using a token.
    // To prevent matching a portion of a longer URL found on the page, or
    // a URL located somewhere other than in the barcode, we enclose the URL
    // in double quotes and prepend a string.
    $this->block->getPlugin()->setConfigurationValue('value', 'Value="[current-page:url]"');
    $this->block->getPlugin()->setConfigurationValue('show_value', TRUE);
    $this->block->save();

    $this->drupalGet('');
    $assert->pageTextContains('Value="' . $this->getUrl() . '"');
  }

  /**
   * Tests all barcode display formats.
   *
   * @param string $format
   *   Format to test.
   * @param string $value
   *   Value of barcode field.
   * @param string $expected
   *   Barcode markup.
   *
   * @dataProvider providerBarcodeFormat
   * @covers ::build
   */
  public function testBarcodeFormat(string $format, string $value, string $expected): void {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Test using the CODABAR barcode type.
    $this->block->getPlugin()->setConfigurationValue('type', 'CODABAR');
    $this->block->getPlugin()->setConfigurationValue('format', $format);
    $this->block->getPlugin()->setConfigurationValue('value', $value);
    $this->block->save();

    $this->drupalGet('');
    $assert->responseContains('barcode-codabar');
    $assert->responseContains($expected);
  }

  /**
   * Provides test data for testBarcodeFormat().
   *
   * @return array<string, array<int, string>>
   *   An array of test case data, where each test case is an array of three
   *   items corresponding to the three input parameters needed for the case:
   *   - format: Barcode format to test.
   *   - value: Value of barcode field.
   *   - expected: Barcode markup.
   */
  public static function providerBarcodeFormat(): array {
    return [
      'PNG display format.' => [
        'PNG',
        '023130',
        '<img alt="Embedded Image" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkAQMAAABKLAcXAAAABlBMVEX///8AAABVwtN+AAAAAXRSTlMAQObYZgAAAAlwSFlzAAAOxAAADsQBlSsOGwAAACNJREFUOI1j2CiyOGO3yNLTs67NNTRgGOWN8kZ5o7xRHo15AOGSiPPiFchBAAAAAElFTkSuQmCC" />',
      ],
      'SVG display format.' => [
        'SVG',
        '023130',
        '<desc>023130</desc>
	<g id="bars" fill="#000000" stroke="none" stroke-width="0" stroke-linecap="square">
		<rect x="0.000000" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="2.469136" y="0.000000" width="2.469136" height="100.000000" />
		<rect x="7.407407" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="11.111111" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="13.580247" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="16.049383" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="18.518519" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="22.222222" y="0.000000" width="2.469136" height="100.000000" />
		<rect x="25.925926" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="28.395062" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="32.098765" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="34.567901" y="0.000000" width="2.469136" height="100.000000" />
		<rect x="38.271605" y="0.000000" width="2.469136" height="100.000000" />
		<rect x="43.209877" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="45.679012" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="48.148148" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="50.617284" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="53.086420" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="55.555556" y="0.000000" width="2.469136" height="100.000000" />
		<rect x="60.493827" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="62.962963" y="0.000000" width="2.469136" height="100.000000" />
		<rect x="67.901235" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="70.370370" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="72.839506" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="75.308642" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="77.777778" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="80.246914" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="83.950617" y="0.000000" width="2.469136" height="100.000000" />
		<rect x="87.654321" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="90.123457" y="0.000000" width="2.469136" height="100.000000" />
		<rect x="95.061728" y="0.000000" width="1.234568" height="100.000000" />
		<rect x="98.765432" y="0.000000" width="1.234568" height="100.000000" />
	</g>
</svg>',
      ],
      'HTMLDIV display format.' => [
        'HTMLDIV',
        '023130',
        '<div class="code"><div style="width:100.000000px;height:100.000000px;position:relative;font-size:0;border:none;padding:0;margin:0;">
	<div style="background-color:rgba(0%,0%,0%,1);left:0.000000px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:2.469136px;top:0.000000px;width:2.469136px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:7.407407px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:11.111111px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:13.580247px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:16.049383px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:18.518519px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:22.222222px;top:0.000000px;width:2.469136px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:25.925926px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:28.395062px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:32.098765px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:34.567901px;top:0.000000px;width:2.469136px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:38.271605px;top:0.000000px;width:2.469136px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:43.209877px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:45.679012px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:48.148148px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:50.617284px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:53.086420px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:55.555556px;top:0.000000px;width:2.469136px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:60.493827px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:62.962963px;top:0.000000px;width:2.469136px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:67.901235px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:70.370370px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:72.839506px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:75.308642px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:77.777778px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:80.246914px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:83.950617px;top:0.000000px;width:2.469136px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:87.654321px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:90.123457px;top:0.000000px;width:2.469136px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:95.061728px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(0%,0%,0%,1);left:98.765432px;top:0.000000px;width:1.234568px;height:100.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
</div>',
      ],
      'UNICODE display format.' => [
        'UNICODE',
        '023130',
        '<pre style="font-family:monospace;line-height:0.61em;font-size:6px;">▄ ▄▄  ▄  ▄ ▄ ▄ ▄  ▄▄ ▄ ▄  ▄ ▄▄ ▄▄  ▄ ▄ ▄ ▄ ▄ ▄▄  ▄ ▄▄  ▄ ▄ ▄ ▄ ▄ ▄  ▄▄ ▄ ▄▄  ▄  ▄
</pre>',
      ],
      'BINARY display format.' => [
        'BINARY',
        '023130',
        '<pre style="font-family:monospace;">101100100101010100110101001011011001010101010110010110010101010101001101011001001
</pre>',
      ],
    ];
  }

}
