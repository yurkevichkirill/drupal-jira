<?php

declare(strict_types=1);

namespace Drupal\time_tracking;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the time log entity type.
 */
final class TimeLogListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['task'] = $this->t('Task');
    $header['uid'] = $this->t('Author');
    $header['hours'] = $this->t('Hours');
    $header['log_date'] = $this->t('Log Date');
    $header['notes'] = $this->t('Notes');
    $header['over_estimate_reason'] = $this->t('Over estimate reason');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\time_tracking\TimeLogInterface $entity */
    $row['id'] = $entity->id();
    $row['task']['data'] = $entity->get('task')->view(['label' => 'hidden']);
    $row['uid']['data'] = $entity->get('uid')->view(['label' => 'hidden']);
    $row['hours']['data'] = $entity->get('hours')->view(['label' => 'hidden']);
    $row['log_date']['data'] = $entity->get('log_date')->view(['label' => 'hidden']);
    $row['notes']['data'] = $entity->get('notes')->view(['label' => 'hidden']);
    $row['over_estimate_reason']['data'] = $entity->get('over_estimate_reason')->view(['label' => 'hidden']);
    return $row + parent::buildRow($entity);
  }

}
