<?php

namespace Drupal\group_content_moderation;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Class GroupContentModerationServiceProvider.
 *
 * Replaces content_moderation's state transition validator.
 */
class GroupContentModerationServiceProvider extends ServiceProviderBase {

  /**
   * Replace content_moderation transition validator with a group friendly one.
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('content_moderation.state_transition_validation');
    $definition->setClass(GroupStateTransitionValidation::class);
  }

}
