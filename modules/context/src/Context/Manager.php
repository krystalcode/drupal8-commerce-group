<?php

namespace Drupal\gcommerce_context\Context;

use Drupal\gcommerce_context\MachineName\Field\User as UserField;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Default implementation of the context manager.
 */
class Manager implements ManagerInterface {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $membershipLoader;

  /**
   * The private user temp store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * Constucts a new Manager object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user account proxy.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\GroupMembershipLoaderInterface $membership_loader
   *   The group membership loader.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private user temp store factory.
   */
  public function __construct(
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    GroupMembershipLoaderInterface $membership_loader,
    PrivateTempStoreFactory $temp_store_factory
  ) {
    $this->currentUser = $current_user->getAccount();
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipLoader = $membership_loader;
    $this->tempStore = $temp_store_factory->get('gcommerce_context');
  }

  /**
   * {@inheritdoc}
   */
  public function get() {
    // Context is not currently supported for anonymous users.
    if ($this->currentUser->isAnonymous()) {
      return;
    }

    // If there is a context in the temp store, consider that as the current
    // context. Otherwise we load the user's default.
    $group = NULL;
    $group_id = $this->tempStore->get('group_id');
    if ($group_id === NULL) {
      $group = $this->getDefault();
    }
    else {
      $group = $this->entityTypeManager->getStorage('group')->load($group_id);
    }

    if (!$group) {
      return;
    }

    // We validate that the user is member of the group. If not, the context is
    // unset. We do not throw an exception here because a user could have been
    // member of a group before and set it as their default context, and then
    // left from the group. In that case we unset the context and the user can
    // select another one.
    $validate = $this->validateGroup($group);
    if (!$validate) {
      $this->unset();
      return;
    }

    return $group;
  }

  /**
   * {@inheritdoc}
   */
  public function set(GroupInterface $group) {
    // Currently, you have to be an authenticated user to have a shopping
    // context.
    // @I Support context for anonymous users
    //    type     : feature
    //    priority : normal
    //    labels   : config, context
    //    notes    : A global setting can be added that will define the default
    //               context for anonymous users.
    if ($this->currentUser->isAnonymous()) {
      throw new \InvalidArgumentException(
        'Setting the context for anonymous users is not supported.'
      );
    }

    // @I Validate that the user is member of the group when setting context
    //    type     : bug
    //    priority : normal
    //    labels   : context
    // @I Validate that the group is of the configured type when setting context
    //    type     : bug
    //    priority : normal
    //    labels   : context
    // @I Review whether we need to clear cart cache in cart provider
    //    type     : bug
    //    priority : normal
    //    labels   : context
    $this->tempStore->set('group_id', $group->id());
  }

  /**
   * {@inheritdoc}
   */
  public function unset() {
    $this->tempStore->delete('group_id');
  }

  /**
   * {@inheritdoc}
   */
  public function getDefault() {
    if ($this->currentUser->isAnonymous()) {
      return;
    }

    $user = $this->getCurrentUser();

    if (!$user->hasField(UserField::DEFAULT_CONTEXT)) {
      throw new InvalidConfigurationException(
        sprintf(
          'The default context `%s` field does not exist in the user entity.',
          UserField::DEFAULT_CONTEXT
        )
      );
    }

    // If the user has set to not remember the last context, then the default
    // context field functions as the general default context for the user.
    // If the user has set to remember the last context, then the default
    // context field functions to store the last selected context.
    // In both cases, we detect the context from the same field.
    $default_context_field = $user->get(UserField::DEFAULT_CONTEXT);
    if ($default_context_field->isEmpty()) {
      return;
    }

    return $default_context_field->entity;
  }

  /**
   * Gets the current user.
   *
   * In some case the current user service contains a `UserSession` object.
   *
   * @return \Drupal\user\UserInterface
   *   The current user's user entity.
   */
  protected function getCurrentUser() {
    if ($this->currentUser instanceof UserInterface) {
      return $this->currentUser;
    }

    $this->currentUser = $this->entityTypeManager
      ->getStorage('user')
      ->load($this->currentUser->id());

    return $this->currentUser;
  }

  /**
   * Validates that given group is a valid context for the current user.
   *
   * Currently, validates that the current user has a membership to the given
   * group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to validate.
   *
   * @return bool
   *   TRUE if the group is a valid context for the current user, FALSE
   *   otherwise.
   */
  protected function validateGroup(GroupInterface $group) {
    return $this->membershipLoader->load($group, $this->getCurrentUser()) ?
      TRUE :
      FALSE;
  }

}
