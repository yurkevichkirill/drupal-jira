<?php

declare(strict_types=1);

namespace Drupal\report_generator;

use Drupal\node\NodeInterface;

/**
 * Defines the contract every project report plugin implements.
 */
interface ReportGeneratorInterface {

  /**
   * Builds the report data for a project.
   *
   * @param \Drupal\node\NodeInterface $project
   *   The project the report is generated for.
   *
   * @return array
   *   The report payload. The shape is defined by each implementation.
   */
  public function generate(NodeInterface $project): array;

  /**
   * Returns the human-readable name of the report.
   *
   * @return string
   *   The report label, as declared in the plugin attribute.
   */
  public function getLabel(): string;

}
