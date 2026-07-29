<?php

declare(strict_types=1);

namespace Drupal\Core\Command;

use Drupal\Core\DrupalKernel;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contains helper methods for console commands that boot up Drupal.
 */
trait BootableCommandTrait {

  /**
   * The class loader.
   *
   * @var object
   */
  protected object $classLoader;

  /**
   * Boots up a Drupal environment.
   *
   * @param string $site_path
   *   The site path (e.g., `sites/default`).
   *
   * @return \Drupal\Core\DrupalKernelInterface
   *   The Drupal kernel.
   *
   * @throws \Exception
   *   Exception thrown if kernel does not boot.
   */
  protected function boot(string $site_path): DrupalKernelInterface {
    $kernel = new DrupalKernel('prod', $this->classLoader);
    $kernel::bootEnvironment();
    $kernel->setSitePath($site_path);
    Settings::initialize($kernel->getAppRoot(), $kernel->getSitePath(), $this->classLoader);
    $kernel->boot();
    $kernel->preHandle(Request::createFromGlobals());
    return $kernel;
  }

}
