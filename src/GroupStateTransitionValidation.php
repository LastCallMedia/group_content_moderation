<?php

namespace Drupal\group_content_moderation;

use Drupal\content_moderation\StateTransitionValidation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupContentType;

/**
 * Class GroupStateTransitionValidation.
 *
 * Group state transition validation integrated with group.
 */
class GroupStateTransitionValidation extends StateTransitionValidation {

  /**
   * {@inheritdoc}
   */
  public function getValidTransitions(ContentEntityInterface $entity, AccountInterface $user) {

    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
    $current_state = $entity->moderation_state->value ? $workflow->getState($entity->moderation_state->value) : $workflow->getTypePlugin()->getInitialState($workflow, $entity);

    // Define a list of relevant group permissions that will allow the current
    // user to perform various transitions.
    $permissions = [];
    foreach ($current_state->getTransitions() as $transition) {
      $permissions['use ' . $workflow->id() . ' transition ' . $transition->id()] = $transition;
    }

    $core_permissions = parent::getValidTransitions($entity, $user);
    $group_intersections = $this->getUserGroupIntersection($user, $entity);

    if (!$group_intersections) {
      return $core_permissions;
    }

    $group_permissions = $this->getGroupPermissions($user, $permissions, $group_intersections);
    return $core_permissions + $group_permissions;
  }

  /**
   * Get user's permissions for groups shared with content.
   */
  private function getGroupPermissions(AccountInterface $user, array $permissions = [], array $group_intersections = []) {
    $group_permissions = [];
    foreach ($permissions as $permission => $transition) {
      foreach ($group_intersections as $group) {
        if ($group->hasPermission($permission, $user)) {
          $group_permissions[$permission] = $transition;
        }
      }
    }
    return $group_permissions;
  }

  /**
   * Get common groups between user and content.
   */
  private function getUserGroupIntersection(AccountInterface $user, ContentEntityInterface $content_entity) {
    $content_groups = $this->getContentGroups($content_entity);

    // If the node doesn't belong to any groups, it's possible that we're
    // creating a new entity that _will_ belong to a group. If that's the
    // case, try to get the group from the route parameters.
    if (!$content_groups && $group = \Drupal::routeMatch()->getParameter('group')) {
      $content_groups[$group->id()] = $group;
    }

    $user_groups = $this->getUserGroups($user);

    return array_filter($content_groups, function ($group) use ($user_groups) {
      foreach ($user_groups as $user_group) {
        if ($group->id() === $user_group->id()) {
          return TRUE;
        }
      }

      return FALSE;
    });
  }

  /**
   * Get groups content belongs to.
   */
  private function getContentGroups(ContentEntityInterface $content_entity) {
    $entity_type = $content_entity->getEntityType()->id();
    $entity_bundle = $content_entity->bundle();

    $plugin_id = 'group_' . $entity_type . ':' . $entity_bundle;
    $group_content_types = GroupContentType::loadByContentPluginId($plugin_id);

    if (!$group_content_types) {
      return [];
    }

    // Get all of the group content relationship entities.
    $group_contents = \Drupal::entityTypeManager()
      ->getStorage('group_content')
      ->loadByProperties([
        'type' => array_keys($group_content_types),
        'entity_id' => [$content_entity->id()],
      ]);

    /** @var \Drupal\group\Entity\GroupInterface[] $content_groups */
    $content_groups = [];
    foreach ($group_contents as $group_content) {
      /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
      $group = $group_content->getGroup();
      $content_groups[$group->id()] = $group;
    }
    return $content_groups;
  }

  /**
   * Get groups user belongs to.
   */
  private function getUserGroups(AccountInterface $user) {
    $group_content_types = GroupContentType::loadByContentPluginId('group_membership');

    $group_contents = \Drupal::entityTypeManager()
      ->getStorage('group_content')
      ->loadByProperties([
        'type' => array_keys($group_content_types),
        'entity_id' => $user->id(),
      ]);

    /** @var \Drupal\group\Entity\GroupInterface[] $content_groups */
    $content_groups = [];
    foreach ($group_contents as $group_content) {
      /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
      $group = $group_content->getGroup();
      $content_groups[$group->id()] = $group;
    }

    return $content_groups;
  }

}
