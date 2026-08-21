<?php

declare(strict_types=1);

namespace Drupal\time_tracking\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\time_tracking\Event\TimeLogCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logs the creation of time log entities.
 */
class TimeLogInsertSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected LoggerChannelFactoryInterface $loggerChannelFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      TimeLogCreatedEvent::EVENT_NAME => 'onTimeLogInsert',
    ];
  }

  /**
   * Logs the time log creation to the time_tracking log channel.
   */
  public function onTimeLogInsert(TimeLogCreatedEvent $event): void {
    $time_log = $event->timeLog;

    $this->loggerChannelFactory->get('time_tracking')->info(
      'Time log created by user @user for @hours hours on task @task.',
      [
        '@user' => $time_log->getOwner()?->getAccountName() ?? $time_log->getOwnerId(),
        '@hours' => $time_log->get('hours')->value,
        '@task' => $time_log->get('task')->entity?->label() ?? $time_log->get('task')->target_id,
      ]
    );
  }

}
