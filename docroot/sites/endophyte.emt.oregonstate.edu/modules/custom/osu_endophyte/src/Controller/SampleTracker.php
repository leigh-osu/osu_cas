<?php

namespace Drupal\osu_endophyte\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use Drupal\pdf_using_mpdf\ConvertToPdfInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Com\Tecnick\Barcode\Barcode as Barcode;

class SampleTracker extends ControllerBase{

  /**
   * Generate a tracking PDF for a sample
   *
   * @param NodeInterface|null $node
   * @return RedirectResponse
   */
  public function generate($node = NULL) {

    $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], ['absolute' => TRUE])->toString();
    $relationships = \Drupal\group\Entity\GroupRelationship::loadByEntity($node);
    if (!$relationships) {
      //add error handling for no group affiliation with node
      return;
    }
    $relationship = reset($relationships);
    $group = \Drupal\group\Entity\Group::load($relationship->getGroupId());

    $ergot = $node->get('field_sample_test_ergot');
    $ergovaline = $node->get('field_sample_test_ergovaline');
    $lolitrem = $node->get('field_sample_test_lolitrem_b');
    $pellet = $node->get('field_sample_test_pellet');

    //get variety label
    $variety_list = $node->field_sample_variety->getSetting('allowed_values');
    $variety = $variety_list[$node->field_sample_variety->value];

    //loading variables directly form node to avoid setting up entity views
    $variables = [
      'theme_hook_original' => '',
      'title' => $node->getTitle(),
      'request_date' => $node->field_sample_date_requested->value,
      'field' => $node->field_sample_field_lot->value,
      'sample_name' => $node->field_sample_sample_name->value,
      'grower_name' => $node->field_sample_grower_name->value,
      'variety' => $variety,
      'variety_other' => $node->field_sample_variety_other->value,
      'barcode' => $this->CreateBarcode($url),
      'group_name' => $group->label(),
      'group_address' => $group->field_client_address->getValue(),
      'group_contact' => $group->field_client_primary_contact->value,
//      'group_email' => $group->field_client_contact_email->value,
    ];
    if (isset ($ergot->entity->field_test_status->value)){
      $variables['ergot'] = $ergot->entity->field_test_status->value;
    }
    if (isset ($ergovaline->entity->field_test_status->value)){
      $variables['ergovaline'] = $ergovaline->entity->field_test_status->value;
      $variables['ev_rush'] = $ergovaline->entity->field_test_rush->value;
    }
    if (isset ($lolitrem->entity->field_test_status->value)){
      $variables['lolitrem'] = $lolitrem->entity->field_test_status->value;
      $variables['lo_rush'] = $lolitrem->entity->field_test_rush->value;
    }
    if (isset ($pellet->entity->field_test_status->value)){
      $variables['pellet'] = $pellet->entity->field_test_status->value;
    }

    // Load the Twig theme engine so we can use twig_render_template().
    include_once \Drupal::root() . '/core/themes/engines/twig/twig.engine';
    $renderedNode = twig_render_template(\Drupal::service('extension.list.module')->getPath('osu_endophyte') . "/templates/osu-endophyte-sample-tracker.html.twig", $variables);

    /** @var ConvertToPdfInterface $pdf */
    $pdfService = \Drupal::service('pdf_using_mpdf.conversion');
//    $pdfService->convert($html, $settings, $context);
    $pdfService->convert($renderedNode);

    return new RedirectResponse($url);
  }

  /**
   * @param string $string
   * @return string
   * @throws \Com\Tecnick\Barcode\Exception
   * @throws \Com\Tecnick\Color\Exception
   */
  private function CreateBarcode (string $string) {

    // instantiate the barcode class
    $barcode = new Barcode();

    // generate a barcode
    $bobj = $barcode->getBarcodeObj(
      'QRCODE,H',                     // barcode type and additional comma-separated parameters
      $string,                             // data string to encode
      150,                           // bar width (use absolute or negative value as multiplication factor)
      150,                          // bar height (use absolute or negative value as multiplication factor)
      'black',                       // foreground color
      array(0,0,0,0)                      // padding (use absolute or negative values as multiplication factors)
    )->setBackgroundColor('white');  // background color

    // Strip out the XML declaration, which mPDF will not accept inline.
    //
    // This used to search for an undefined $marker. PHP read that as null,
    // strpos() took the null as an empty needle and returned 0, and the
    // substr() therefore trimmed a fixed number of characters off the front
    // -- correct only because the declaration happens to sit at offset 0 and
    // be exactly strlen($xmltag) long. It warned on every barcode and, from
    // PHP 8.4, passing null there is an error rather than a deprecation.
    $svg = $bobj->getSvgCode();
    $xmltag = '<?xml version="1.0" standalone="no" ?>';
    $offset = strpos($svg, $xmltag);
    $clean_svg = $offset === FALSE ? $svg : substr($svg, $offset + strlen($xmltag));
    return $clean_svg;
  }
}
