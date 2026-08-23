<?php

namespace Drupal\styleguide\Plugin;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\styleguide\StyleguideInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Styleguide plugins.
 */
abstract class StyleguidePluginBase extends PluginBase implements StyleguideInterface, ContainerFactoryPluginInterface {

  /**
   * The styleguide generator service.
   *
   * @var \Drupal\styleguide\Generator
   */
  protected $generator;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->generator = $container->get('styleguide.generator');
    $instance->formBuilder = $container->get('form_builder');
    return $instance;
  }

  /**
   * Build a link.
   *
   * @param string $text
   *   Text displayed in the link.
   * @param string $uri
   *   Url used in the link.
   *
   * @return string
   *   The renderable array.
   */
  public function buildLink($text, $uri) {
    $url = Url::fromUserInput($uri);
    $link = Link::fromTextAndUrl($text, $url);
    return $link->toRenderable();
  }

  /**
   * Build a link from a given route name and parameters.
   *
   * @param string $text
   *   Text displayed in the link.
   * @param string $route_name
   *   The name of the route.
   * @param array $route_parameters
   *   (optional) An associative array of parameter names and values.
   * @param array $options
   *   (optional) An associative array of additional options.
   *
   * @return array
   *   The renderable array.
   */
  public function buildLinkFromRoute($text, $route_name, array $route_parameters = [], array $options = []) {
    $link = Link::createFromRoute($text, $route_name, $route_parameters, $options);
    return $link->toRenderable();
  }

}
