<?php

namespace Drupal\gcommerce_order\Controller;

use Drupal\group\Entity\Controller\GroupContentController;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Drupal\user\PrivateTempStoreFactory;

use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for `group_commerce_order` GroupContent routes.
 *
 * @I Add a base controller that can be reused for all entities
 *    type     : task
 *    priority : normal
 *    labels   : controller, refactoring
 */
class GroupOrderController extends GroupContentController {

  /**
   * The group content enabler plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $pluginManager;

  /**
   * Constructs a new GroupOrderController object.
   *
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $plugin_manager
   *   The group content plugin manager.
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   The private store factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    GroupContentEnablerManagerInterface $plugin_manager,
    PrivateTempStoreFactory $temp_store_factory,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    RendererInterface $renderer
  ) {
    parent::__construct(
      $temp_store_factory,
      $entity_type_manager,
      $entity_form_builder,
      $renderer
    );

    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.group_content_enabler'),
      $container->get('user.private_tempstore'),
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @I Handle create mode for orders
   *    type     : bug
   *    priority : normal
   *    labels   : order
   */
  public function addPage(GroupInterface $group, $create_mode = FALSE) {
    $build = parent::addPage($group, $create_mode);

    // Do not interfere with redirects.
    if (!is_array($build)) {
      return $build;
    }

    // Overwrite the label and description for all of the displayed bundles.
    $storage_handler = $this->entityTypeManager
      ->getStorage('commerce_order_type');
    $bundles = $this->addPageBundles($group, $create_mode);
    foreach ($bundles as $plugin_id => $bundle_name) {
      if (empty($build['#bundles'][$bundle_name])) {
        continue;
      }

      $plugin = $group
        ->getGroupType()
        ->getContentPlugin($plugin_id);

      $bundle_label = $storage_handler
        ->load($plugin->getEntityBundle())
        ->label();
      $build['#bundles'][$bundle_name]['label'] = $bundle_label;

      $t_args = ['%order_type' => $bundle_label];
      if ($create_mode) {
        $build['#bundles'][$bundle_name]['description'] = $this->t(
          'Create a order of type %order_type in the group.',
          $t_args
        );
      }
      else {
        $build['#bundles'][$bundle_name]['description'] = $this->t(
          'Add an existing order of type %order_type to the group.',
          $t_args
        );
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function addPageBundles(GroupInterface $group, $create_mode) {
    $bundles = [];

    // Retrieve all group_commerce_order plugins for the group's type.
    $plugin_ids = $this->pluginManager->getInstalledIds($group->getGroupType());
    foreach ($plugin_ids as $key => $plugin_id) {
      if (strpos($plugin_id, 'group_commerce_order:') !== 0) {
        unset($plugin_ids[$key]);
      }
    }

    // Retrieve all of the responsible group content types, keyed by plugin ID.
    $group_content_types = $this->entityTypeManager
      ->getStorage('group_content_type')
      ->loadByProperties([
        'group_type' => $group->bundle(),
        'content_plugin' => $plugin_ids,
      ]);
    foreach ($group_content_types as $bundle => $group_content_type) {
      $bundles[$group_content_type->getContentPluginId()] = $bundle;
    }

    return $bundles;
  }

}
