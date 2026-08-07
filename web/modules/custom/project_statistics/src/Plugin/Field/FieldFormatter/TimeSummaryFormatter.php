<?php

declare(strict_types=1);

namespace Drupal\project_statistics\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\task_stat\Service\TaskStatService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows an estimate together with the logged and the remaining time.
 */
#[FieldFormatter(
  id: 'time_summary',
  label: new TranslatableMarkup('Time summary'),
  field_types: ['decimal', 'float'],
)]
final class TimeSummaryFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a TimeSummaryFormatter object.
   *
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\task_stat\Service\TaskStatService $taskStatService
   *   The task statistics service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected readonly TaskStatService $taskStatService,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('drupaljira.task_stat'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $entity = $items->getEntity();
    // The formatter is offered for every decimal field, but the logged time is
    // only defined for task nodes.
    $is_task = $entity instanceof NodeInterface && $entity->bundle() === 'task';

    foreach ($items as $delta => $item) {
      $estimate = (float) $item->value;

      if (!$is_task) {
        $elements[$delta] = ['#markup' => $this->formatHours($estimate)];
        continue;
      }

      $logged = $this->taskStatService->getLoggedHours($entity);
      $remaining = $this->taskStatService->getRemainingEstimate($entity);

      if ($remaining < 0) {
        $summary = $this->t('@estimate (@logged written off, over estimate by @over)', [
          '@estimate' => $this->formatHours($estimate),
          '@logged' => $this->formatHours($logged),
          '@over' => $this->formatHours(abs($remaining)),
        ]);
      }
      else {
        $summary = $this->t('@estimate (@logged written off, @remaining left)', [
          '@estimate' => $this->formatHours($estimate),
          '@logged' => $this->formatHours($logged),
          '@remaining' => $this->formatHours($remaining),
        ]);
      }

      $elements[$delta] = ['#markup' => $summary];
    }

    if ($is_task) {
      // The summary changes whenever time is logged against the task.
      $elements['#cache']['tags'][] = 'time_log_list';
    }

    return $elements;
  }

  /**
   * Renders an amount of hours as a human readable string.
   *
   * @param float $hours
   *   The amount of hours.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A string such as "1 hour" or "2.5 hours".
   */
  private function formatHours(float $hours): TranslatableMarkup {
    $rounded = round($hours, 2);
    // Trim the trailing zeros so that 2.50 reads as "2.5" and 3.00 as "3".
    $formatted = rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');

    if ($rounded === 1.0) {
      return $this->t('@value hour', ['@value' => $formatted]);
    }

    return $this->t('@value hours', ['@value' => $formatted]);
  }

}
