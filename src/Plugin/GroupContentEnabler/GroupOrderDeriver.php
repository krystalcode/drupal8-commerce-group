<?php

namespace Drupal\commerce_group\Plugin\GroupContentEnabler;

use Drupal\Component\Plugin\Derivative\DeriverBase;

use Drupal\Core\Entity\EntityTypeManagerInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a deriver for the GroupOrder entity.
 *
 * @package Drupal\commerce_group\Plugin\GroupContentEnabler
 */
class GroupOrderDeriver extends DeriverBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new GroupOrder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $order_types = $this
      ->entityTypeManager
      ->getStorage('commerce_order_type')
      ->loadMultiple();
    foreach ($order_types as $name => $order_type) {
      $label = $order_type->label();

      $this->derivatives[$name] = [
        'entity_bundle' => $name,
        'label' => t('Group order (@type)', ['@type' => $label]),
        'description' => t('Adds %type order content to groups both publicly and privately.', ['%type' => $label]),
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
