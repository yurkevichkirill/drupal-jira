<?php

declare(strict_types=1);

namespace Drupal\time_tracking;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a time log entity type.
 */
interface TimeLogInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
