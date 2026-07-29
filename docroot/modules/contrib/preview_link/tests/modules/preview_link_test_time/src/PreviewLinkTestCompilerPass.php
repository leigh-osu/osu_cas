<?php

declare(strict_types=1);

namespace Drupal\preview_link_test_time;

use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to override the service.
 */
class PreviewLinkTestCompilerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $container->getDefinition('datetime.time')
      ->setClass(TimeMachine::class)
      ->setArgument('$state', new ServiceClosureArgument(new Reference(StateInterface::class)));
  }

}
