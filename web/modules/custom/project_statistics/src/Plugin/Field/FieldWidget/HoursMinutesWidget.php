<?php

declare(strict_types=1);

namespace Drupal\project_statistics\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Edits a decimal amount of hours as separate hours and minutes inputs.
 */
#[FieldWidget(
  id: 'hours_minutes',
  label: new TranslatableMarkup('Hours and minutes'),
  field_types: ['decimal', 'float'],
)]
final class HoursMinutesWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $value = $items[$delta]->value ?? NULL;
    // Work in whole minutes so that 1.75 splits into exactly 1 h 45 m instead
    // of accumulating a floating point remainder.
    $total_minutes = ($value === NULL || $value === '') ? NULL : (int) round((float) $value * 60);

    $element['#type'] = 'fieldset';
    $element['#attributes']['class'][] = 'hours-minutes-widget';

    $element['hours'] = [
      '#type' => 'number',
      '#title' => $this->t('Hours'),
      '#default_value' => $total_minutes === NULL ? NULL : intdiv($total_minutes, 60),
      '#min' => 0,
      '#step' => 1,
      '#size' => 4,
    ];

    $element['minutes'] = [
      '#type' => 'number',
      '#title' => $this->t('Minutes'),
      '#default_value' => $total_minutes === NULL ? NULL : $total_minutes % 60,
      '#min' => 0,
      '#max' => 59,
      '#step' => 1,
      '#size' => 4,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    foreach ($values as $delta => $value) {
      $hours = $value['hours'] ?? '';
      $minutes = $value['minutes'] ?? '';

      // Both inputs left empty means the field itself is empty, not zero.
      if (($hours === '' || $hours === NULL) && ($minutes === '' || $minutes === NULL)) {
        $values[$delta] = ['value' => NULL];
        continue;
      }

      $total_minutes = (int) $hours * 60 + (int) $minutes;

      // Drop the hours/minutes sub-values: the field item only knows 'value'.
      $values[$delta] = ['value' => round($total_minutes / 60, 2)];
    }

    return $values;
  }

}
