<?php

declare(strict_types=1);

namespace Drupal\time_tracking\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\time_tracking\Entity\TimeLog;

/**
 * Event fired after a time log entity has been created.
 */
class TimeLogCreatedEvent extends Event {

  const EVENT_NAME = 'time_log_created_event';

  public function __construct(
    public readonly TimeLog $timeLog,
  ) {}

}
