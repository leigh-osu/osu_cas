<?php

namespace Drupal\views_date_format_sql\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument\NumericArgument;
use Drupal\views_date_format_sql\Plugin\ViewsDateFormatSqlPluginDefinition;
use Drupal\views_date_format_sql\Plugin\ViewsDateFormatSqlPluginInterface;

/**
 * An argument that filters entity timestamp field data.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("views_date_format_sql_argument")
 */
class ViewsDateFormatSqlArgument extends NumericArgument implements ViewsDateFormatSqlPluginInterface {

  /**
   * The format.
   *
   * @var string
   */
  private $format;

  /**
   * The format string.
   *
   * @var string
   */
  private $formatString;

  /**
   * Returns SQL date formula.
   */
  public function getFormula() {
    $formula = $this->getDateFormat($this->options['format_string']);
    return $formula;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['format_date_sql'] = ['default' => FALSE];
    $options['format_string'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {

    $form['format_date_sql'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use SQL to format date'),
      '#description' => $this->t('Use the SQL database to format the date. This enables date values to be used in grouping aggregation.'),
      '#default_value' => $this->options['format_date_sql'],
    ];
    $form['format_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date Format'),
      '#description' => $this->t('Use the SQL database to format the date. This enables date values to be used in grouping aggregation.'),
      '#default_value' => $this->options['format_string'],
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * If sql date formatting isn't chosen.
   */
  public function query($group_by = FALSE) {
    if (!$this->options['format_date_sql']) {
      return parent::query();
    }

    $this->ensureMyTable();
    // Now that our table is secure, get our formula.
    $placeholder = $this->placeholder();
    $formula = $this->getFormula() . ' = ' . $placeholder;
    $placeholders = [
      $placeholder => $this->argument,
    ];
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere(0, $formula, $placeholders, 'formula');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    if ($this->options['format_date_sql']) {
      $dependencies['module'][] = 'views_date_format_sql';
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {

    if (is_array($this->pluginDefinition)) {
      $this->pluginDefinition = new ViewsDateFormatSqlPluginDefinition($this->getPluginId(), self::class);
    }
    return $this->pluginDefinition;
  }

}
