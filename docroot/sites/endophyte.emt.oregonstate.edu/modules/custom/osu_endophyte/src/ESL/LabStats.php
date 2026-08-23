<?php

namespace Drupal\osu_endophyte\ESL;

use Drupal\views\Views;

class LabStats
{
  public function getStats()
  {
    $view = Views::getView('lab_tests_ergot');
    $view->execute('block_3');
    $count = $view->total_rows;
    $rowcount['ergot-pending'] = $count;

    $view = Views::getView('lab_tests_ergot');
    $view->execute('block_4');
    $count = $view->total_rows;
    $rowcount['ergot-complete'] = $count;

    $view = Views::getView('lab_tests_ergovaline');
    $view->execute('block_5');
    $count = $view->total_rows;
    $rowcount['ergovaline-rush-pending'] = $count;

    $view = Views::getView('lab_tests_ergovaline');
    $view->execute('block_6');
    $count = $view->total_rows;
    $rowcount['ergovaline-pending'] = $count;

    $view = Views::getView('lab_tests_ergovaline');
    $view->execute('block_7');
    $count = $view->total_rows;
    $rowcount['ergovaline-rush-complete'] = $count;

    $view = Views::getView('lab_tests_ergovaline');
    $view->execute('block_8');
    $count = $view->total_rows;
    $rowcount['ergovaline-complete'] = $count;

    $view = Views::getView('lab_tests_lolitrem_b');
    $view->execute('block_5');
    $count = $view->total_rows;
    $rowcount['lolitrem_b-rush-pending'] = $count;

    $view = Views::getView('lab_tests_lolitrem_b');
    $view->execute('block_6');
    $count = $view->total_rows;
    $rowcount['lolitrem_b-pending'] = $count;

    $view = Views::getView('lab_tests_lolitrem_b');
    $view->execute('block_7');
    $count = $view->total_rows;
    $rowcount['lolitrem_b-rush-complete'] = $count;

    $view = Views::getView('lab_tests_lolitrem_b');
    $view->execute('block_8');
    $count = $view->total_rows;
    $rowcount['lolitrem_b-complete'] = $count;

    $view = Views::getView('lab_tests_pellet');
    $view->execute('block_3');
    $count = $view->total_rows;
    $rowcount['pellet-pending'] = $count;

    $view = Views::getView('lab_tests_pellet');
    $view->execute('block_4');
    $count = $view->total_rows;
    $rowcount['pellet-complete'] = $count;

    return $rowcount;
  }
}
