<?php

declare(strict_types=1);

namespace Drupal\report_generator\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines a ReportGenerator attribute for plugin discovery.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ReportGenerator extends Plugin {

  /**
   * Constructs a ReportGenerator attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param string $label
   *   The human readable name of the report.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly string $label,
    public readonly ?string $deriver = NULL,
  ) {}

}
