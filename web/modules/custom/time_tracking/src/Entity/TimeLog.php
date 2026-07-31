<?php

declare(strict_types=1);

namespace Drupal\time_tracking\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\time_tracking\Form\TimeLogForm;
use Drupal\time_tracking\Routing\TimeLogHtmlRouteProvider;
use Drupal\time_tracking\TimeLogInterface;
use Drupal\time_tracking\TimeLogListBuilder;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * Defines the time log entity class.
 */
#[ContentEntityType(
  id: 'time_log',
  label: new TranslatableMarkup('Time log'),
  label_collection: new TranslatableMarkup('Time logs'),
  label_singular: new TranslatableMarkup('time log'),
  label_plural: new TranslatableMarkup('time logs'),
  entity_keys: [
    'id' => 'id',
    'label' => 'id',
    'owner' => 'uid',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => TimeLogListBuilder::class,
    'views_data' => EntityViewsData::class,
    'form' => [
      'add' => TimeLogForm::class,
      'edit' => TimeLogForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => TimeLogHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/time-log',
    'add-form' => '/time-log/add',
    'canonical' => '/time-log/{time_log}',
    'edit-form' => '/time-log/{time_log}',
    'delete-form' => '/time-log/{time_log}/delete',
    'delete-multiple-form' => '/admin/content/time-log/delete-multiple',
  ],
  admin_permission: 'administer time_log',
  base_table: 'time_log',
  translatable: FALSE,
  show_revision_ui: FALSE,
  label_count: [
    'singular' => '@count time logs',
    'plural' => '@count time logs',
  ],
  field_ui_base_route: 'entity.time_log.settings',
)]
class TimeLog extends ContentEntityBase implements TimeLogInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['task'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Task'))
      ->setDescription(t('The task the time was spent on.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default:node')
      ->setSetting('handler_settings', ['target_bundles' => ['task' => 'task']])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The user who logged the time.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['hours'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Hours'))
      ->setDescription(t('The number of hours spent.'))
      ->setSetting('precision', 10)
      ->setSetting('scale', 2)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_decimal',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['log_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Log date'))
      ->setDescription(t('The day the time was spent on.'))
      ->setSetting('datetime_type', DateTimeItem::DATETIME_TYPE_DATE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('Free form description of the work done.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'settings' => ['rows' => 4],
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['over_estimate_reason'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Over estimate reason'))
      ->setDescription(t('Why the logged time exceeds the task estimate.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'settings' => ['rows' => 3],
        'weight' => 25,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 25,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
