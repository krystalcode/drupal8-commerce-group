<?php

namespace Drupal\gcommerce_context\Context;

use Drupal\group\Entity\GroupInterface;

/**
 * Defines the interface for the context manager.
 *
 * The context manager is responsible for setting and detecting the current
 * context for the current user.
 */
interface ManagerInterface {

  /**
   * Detects and returns the current context for the current user.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The current context for the current user's, or NULL if none was
   *   detected.
   */
  public function get();

  /**
   * Detects and returns the default context for the current user.
   *
   * The default context is stored in a user entity field.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The default group for the user or NULL if it is not set.
   */
  public function getDefault();

  /**
   * Sets the current context for the current user.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The current context group entity.
   */
  public function set(GroupInterface $group);

  /**
   * Unsets the current context for the current user.
   */
  public function unset();

}
