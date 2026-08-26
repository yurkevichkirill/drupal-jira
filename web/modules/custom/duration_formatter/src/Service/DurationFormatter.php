<?php

declare(strict_types=1);

namespace Drupal\duration_formatter\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Renders amounts of hours as human-readable, translatable strings.
 */
class DurationFormatter {

  use StringTranslationTrait;

  /**
   * Constructs a DurationFormatter object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(TranslationInterface $string_translation) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Summarises an estimate against the time already spent on it.
   *
   * @param float $estimate
   *   The original estimate, in hours.
   * @param float $logged
   *   The hours logged so far.
   * @param float $remaining
   *   The estimate minus the logged hours. A negative value means more time
   *   was logged than estimated.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A string such as "8 hours (3 hours written off, 5 hours left)".
   */
  public function formatSummary(float $estimate, float $logged, float $remaining): TranslatableMarkup {
    if ($remaining < 0) {
      return $this->t('@estimate (@logged written off, over estimate by @remaining)', [
        '@estimate' => $this->format($estimate),
        '@logged' => $this->format($logged),
        '@remaining' => $this->format(abs($remaining)),
      ]);
    }

    return $this->t('@estimate (@logged written off, @remaining left)', [
      '@estimate' => $this->format($estimate),
      '@logged' => $this->format($logged),
      '@remaining' => $this->format($remaining),
    ]);
  }

  /**
   * Renders what is left of an estimate as a human-readable string.
   *
   * Shared between the project statistics block and task card (full page
   * load) and the Kanban board's drag-and-drop status update (AJAX), so a
   * remaining/overrun figure always reads the same wherever it is shown.
   *
   * @param float $remaining
   *   The estimate minus the hours already logged. A negative value means
   *   more time was logged than estimated.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The remaining hours, or how far the estimate was overrun.
   */
  public function formatRemaining(float $remaining): TranslatableMarkup {
    if ($remaining < 0) {
      return $this->t('over estimate by @hours', [
        '@hours' => $this->format(abs($remaining)),
      ]);
    }

    return $this->format($remaining);
  }

  /**
   * Renders a number of hours as a human-readable string.
   *
   * @param float $hours
   *   The number of hours to format.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The rounded amount followed by its unit of measurement.
   */
  public function format(float $hours): TranslatableMarkup {
    $rounded = round($hours, 2);
    // Trim the trailing zeros so that 2.50 reads as "2.5" and 3.00 as "3".
    $formatted = rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');

    return $rounded === 1.0
      ? $this->t('@value hour', ['@value' => $formatted])
      : $this->t('@value hours', ['@value' => $formatted]);
  }

}
