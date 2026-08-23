<?php

namespace Drupal\styleguide\Plugin\Styleguide;

use Drupal\styleguide\Plugin\StyleguidePluginBase;

/**
 * Views Styleguide items implementation.
 *
 * @Plugin(
 *   id = "views_styleguide",
 *   label = @Translation("Views Styleguide elements"),
 *   provider = "views",
 * )
 */
class ViewsStyleguide extends StyleguidePluginBase {

  /**
   * {@inheritdoc}
   */
  public function items() {
    $items = [];

    $items['views_mini_pager'] = [
      'title' => $this->t('Views mini pager'),
      'content' => ['#theme' => 'views_mini_pager'] + $this->generator->pager(),
      'group' => $this->t('User interface'),
    ];

    return $items;
  }

}
