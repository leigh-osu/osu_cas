<?php

namespace Drupal\osu_endophyte\Controller;

use Drupal\Core\Controller\ControllerBase;
//use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
//use \Drupal\field\Entity\FieldConfig;
use Drupal\pdf_using_mpdf\Conversion\ConvertToPdf;
use Drupal\Core\Url;
//use Symfony\Component\DependencyInjection\ContainerInterface;
//use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\pdf_using_mpdf\ConvertToPdfInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
//use Drupal\pdf_using_mpdf\Controller\GeneratePdf;
//use Symfony\Component\HttpFoundation\Response;
//use Drupal\group\Entity\GroupContent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TestCertificate extends ControllerBase{

  /**
   * Generate a tracking PDF for a sample
   *
   * @param NodeInterface|null $node
   * @param null $test_type
   * @return RedirectResponse
   */
  public function generate($node = NULL, $test_type = NULL): RedirectResponse {
    $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], ['absolute' => TRUE])->toString();
    $relationships = \Drupal\group\Entity\GroupRelationship::loadByEntity($node);
    if (!$relationships) {
      //add error handling for no group affiliation with node
      return NULL;
    }
    $relationship = reset($relationships);
    $group = \Drupal\group\Entity\Group::load($relationship->getGroupId());

    $field_name = 'field_sample_test_'. $test_type;
    if (isset ($group) && $node->hasField($field_name)) {
      $test = $node->get($field_name);

      //use test completion date, not sample completion date
      $test_complete_date = $test->entity->field_test_final_ok_date->value;

      if (isset ($test->entity->field_test_status->value) && $test->entity->field_test_status->value == 'lab_test_completed' && !is_null($test_complete_date)) {
        $status = $test->entity->field_test_status->value;
        $final_calc = $test->entity->field_test_final_value_calc->value;

        //get variety label
        $variety_list = $node->field_sample_variety->getSetting('allowed_values');
        $variety = $variety_list[$node->field_sample_variety->value];

        $module_handler = \Drupal::service('module_handler');
        $module_path = $module_handler->getModule('osu_endophyte')->getPath();

        // logo as base64
        $logo_data = file_get_contents($module_path . '/assets/osu_logo_base64.txt');
        $subtitle = "Endophyte Alkaloid Analysis Results";
        $final_value = 'VALUE '. $test->entity->field_test_final_value_calc->value;
        $note_livestock = "<table class='note-table'>
	<tr><td class='category'>Horses:</td><td>300-500 ppb</td></tr>
	<tr><td class='category'>Cattle:</td><td>400-750 ppb</td></tr>
	<tr><td class='category'>Sheep:</td><td>500-800 ppb</td></tr>
</table>";
        // info specific to tests
        switch ($test_type) {
          case 'pellet':
            $test_name = "Pellet Test";
            $test_note = '';
            if ($test_complete_date > '2026-03-15T00:00:00') {
              $lab_method = 'ESL-SOP-ATM-003.05';
            } elseif ($test_complete_date > '2024-01-04T00:00:00') {
                $lab_method = 'ESL-SOP-ATM-003.04';
            } elseif ($test_complete_date > '2023-06-01T00:00:00') {
              $lab_method = 'ESL-SOP-ATM-003.03';
            } elseif ($test_complete_date > '2022-04-15T00:00:00') {
              $lab_method = 'ESL-SOP-ATM-003.02';
            } else {
              $lab_method = 'ESL-SOP-ATM-003.01';
            }
            $concentration = 'Ergovaline';
            if (isset($test->entity->field_test_claviceps->value) && $test->entity->field_test_claviceps->value == TRUE){
              $test_note = "<p>*Additional ergot alkaloids suggestive of infection with Claviceps spp. are suspected.</p>";
            }
            $test_note .="<table><tr><td class='note-label'><p>Note:</p></td><td class='note-body'><p>With the scientific knowledge available as of 2014, toxicosis from ergovaline is induced in livestock as follows, with other alkaloids having an additive effect:</p></td></tr></table>";
            $test_note .= $note_livestock;
            $final_value = 'ERGOVALINE '. $final_value;
            break;
          case 'ergovaline':
            $test_name = "Ergovaline Test";
            if ($test_complete_date > '2026-03-15T00:00:00') {
              $lab_method = 'ESL-SOP-ATM-003.05';
            } elseif ($test_complete_date > '2024-01-04T00:00:00') {
              $lab_method = 'ESL-SOP-ATM-003.04';
            } elseif ($test_complete_date > '2023-06-01T00:00:00') {
                $lab_method = 'ESL-SOP-ATM-003.03';
            } elseif ($test_complete_date > '2022-04-15T00:00:00') {
              $lab_method = 'ESL-SOP-ATM-003.02';
            } else {
              $lab_method = 'ESL-SOP-ATM-003.01';
            }
            $concentration = 'Ergovaline';
            $test_note = "<table><tr><td class='note-label'><p>Note:</p></td><td class='note-body'><p>According to the National Hay Association's OSU Advisory Panel, less than 500 ppb ergovaline is considered safe for feeding dairy and beef cattle.</p></td></tr></table>";
            break;
          case 'lolitrem_b':
            $test_name = "Lolitrem B Test";
            if ($test_complete_date > '2026-03-15T00:00:00') {
              $lab_method = 'ESL-SOP-ATM-004.05';
            } elseif ($test_complete_date > '2023-02-01T00:00:00') {
              $lab_method = 'ESL-SOP-ATM-004.04';
            } elseif ($test_complete_date > '2021-08-03T00:00:00') {
              $lab_method = 'ESL-SOP-ATM-004.03';
            } else {
              $lab_method = 'ESL-SOP-ATM-004.02';
            }
            $concentration = 'Lolitrem B';
            $test_note = "<table><tr><td class='note-label'><p>Note:</p></td><td class='note-body'><p>According to the National Hay Association's OSU Advisory Panel, less than 2000 ppb lolitrem B is considered safe for feeding dairy and beef cattle.</p></td></tr></table>";
            break;
          case 'ergot':
            $test_name = "Ergot Test";
            $subtitle = "Ergopeptine Alkaloid Analysis Results";
            $lab_method = 'ESL-SOP-ATM-005.02';
            $concentration = 'Ergopeptine alkaloids';
            $final_value = "TOTAL ERGOPEPTINE ALKALOIDS ". $final_value;
            // Seed the note before the conditional below appends to it. Without
            // this, a sample whose ergot estimate flag is false reached the
            // .= with $test_note undefined and warned on every such
            // certificate. The other three test types already assign first.
            $test_note = '';
            if (isset($test->entity->field_test_ergot_estimate->value) && $test->entity->field_test_ergot_estimate->value == TRUE){
              $final_value = $final_value ."*";
              $test_note = "<p>*One or more ergot alkaloids exceed upper limit of quantitation. Value given is an estimate.</p>";
            }
            $test_note .= "<table><tr><td class='note-label'><p>Note:</p></td><td class='note-body'><p>Total includes ergosine, ergotamine,
ergocornine, alpha-ergocryptine, and ergocristine, when the value(s) are at or above the method limit of quantitation for the alkaloid.</p><p>You can find information and publications relating to ergot toxicity at our website: https://emt.oregonstate.edu/endophyte-lab</p></td></tr></table>";
            break;
          default:
            throw new NotFoundHttpException();
        }
        //    $logo_path = '/'. $module_path .'/images/OSU_vertical_1C_B.png';
        //loading variables directly form node to avoid setting up entity views
        $variables = [
          'theme_hook_original' => '',
          'test_type' => $test_type,
          'test_name' => $test_name,
          'subtitle' => $subtitle,
          'concentration' => $concentration,
          'test_note' => $test_note ."",
          'lab_method' => $lab_method,
          'logo_data' => $logo_data,
          'sample_id' => $node->getTitle(),
          'request_date' => $node->field_sample_date_requested->value,
          'received_date' => $node->field_sample_received_date->value,
          //use test_complete_date, not sample complete
          'completed_date' => $test_complete_date,
          'field' => $node->field_sample_field_lot->value,
          'variety' => $variety,
          'variety_other' => $node->field_sample_variety_other->value,
          'final_calc' => $final_value,
          'group_name' => $group->label(),
        ];

        // Load the Twig theme engine so we can use twig_render_template().
        include_once \Drupal::root() . '/core/themes/engines/twig/twig.engine';
        $markup = twig_render_template(\Drupal::service('extension.list.module')->getPath('osu_endophyte') . "/templates/osu-endophyte-test-certificate.html.twig", $variables);

        /** @var ConvertToPdfInterface $pdf */
        $pdfService = \Drupal::service('pdf_using_mpdf.conversion');
        $pdf_title = $node->getTitle() .'-'. $test_type;
        $settings = array(
          'pdf_set_title' => $pdf_title,
          'pdf_filename' => $pdf_title .'.pdf',
          'pdf_set_author' => 'OSU Endophyte Service Laboratory',
          'pdf_css_file' => $module_path . '/assets/test_certificate.css',
        );
        // $pdfService->convert($html, $settings, $context);
        $pdfService->convert($markup, $settings);

        return new RedirectResponse('/');
      }
    }
    throw new NotFoundHttpException();
  }
}
