<?php

declare(strict_types=1);

namespace Drupal\report_generator;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\report_generator\Attribute\ReportGenerator;

/**
 * Manages the discovery of report generator plugins.
 */
class ReportGeneratorManager extends DefaultPluginManager {

  /**
   * Constructs a ReportGeneratorManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ReportGenerator',
      $namespaces,
      $module_handler,
      ReportGeneratorInterface::class,
      ReportGenerator::class
    );

    $this->alterInfo('report_generator_info');
    $this->setCacheBackend($cache_backend, 'report_generator_plugins');
  }

}
