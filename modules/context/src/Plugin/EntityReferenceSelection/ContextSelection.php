<?php

namespace Drupal\gcommerce_context\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection as Database;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Entity\Query\Sql\Condition as QueryCondition;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a plugin for selecting a user's shopping context.
 *
 * @EntityReferenceSelection(
 *   id = "gcommerce:context",
 *   label = @Translation("Shopping context"),
 *   group = "gcommerce",
 *   weight = 0
 * )
 *
 * @I Validate that the context group type is the same as here
 *    type     : bug
 *    priority : low
 *    labels   : config, context, validation
 */
class ContextSelection extends DefaultSelection {

  /**
   * The module's configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new ContextSelection object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    AccountInterface $current_user,
    EntityFieldManagerInterface $entity_field_manager = NULL,
    EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    EntityRepositoryInterface $entity_repository = NULL,
    ConfigFactoryInterface $config_factory,
    Database $database
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $module_handler,
      $current_user,
      $entity_field_manager,
      $entity_type_bundle_info,
      $entity_repository
    );

    $this->config = $config_factory->get('gcommerce_context.settings');
    $this->database = $database;

    // The module's configuration is not available yet when the parent
    // constructor of the `SelectPluginBase` class calls the `setConfiguration`
    // method. We call it again to properly get the default configuration.
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.repository'),
      $container->get('config.factory'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $target_bundles = [];
    if ($this->config) {
      $target_bundles[] = $this->config->get('group_context.group_type');
    }

    return [
      'target_type' => 'group',
      'target_bundles' => $target_bundles,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Target bundles are not configurable; only the bundle that is configured
    // to act as the context provider is allowed.
    unset($form['target_bundles']);

    // We disable the ability to create a group through the field. It may be
    // good to support it but we need to look a bit more into it; if for example
    // the group is not set to add the creator as a member, or if the creator is
    // not the user that the field belongs to, there would be errors or
    // unexpected behaviors.
    //
    // @I Support context group auto-create in entity reference selection
    //    type     : improvement
    //    priority : low
    //    labels   : context
    unset($form['auto_create']);
    unset($form['auto_create_bundle']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities(
    $match = NULL,
    $match_operator = 'CONTAINS',
    $limit = 0
  ) {
    $target_type = $this->getConfiguration()['target_type'];

    $query = $this->buildDatabaseQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute()->fetchAllKeyed(0, 0);

    if (empty($result)) {
      return [];
    }

    $options = [];
    $entities = $this->entityTypeManager
      ->getStorage($target_type)
      ->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $options[$bundle][$entity_id] = Html::escape(
        $this->entityRepository->getTranslationFromContext($entity)->label()
      );
    }

    return $options;
  }

  /**
   * Builds a database query to get referenceable entities.
   *
   * The referenceable entities are groups of the type that is configured to act
   * as shopping context and that the user is member of.
   *
   * @param string|null $match
   *   (Optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (Optional) The operation the matching should be done with. Defaults
   *   to "CONTAINS".
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The query object with the basic conditions and sorting applied to
   *   it.
   */
  protected function buildDatabaseQuery(
    $match = NULL,
    $match_operator = 'CONTAINS'
  ) {
    $configuration = $this->getConfiguration();

    $target_bundle = $this->config->get('group_context.group_type');
    if (!$target_bundle) {
      throw new \InvalidConfigurationException(
        'A group type needs to be configured to act as a shopping context provider.'
      );
    }

    $target_type = $configuration['target_type'];
    $entity_type = $this->entityTypeManager->getDefinition($target_type);

    $group_data_table = $this->entityTypeManager
      ->getDefinition('group')
      ->getDataTable();
    $query = $this->database->select($group_data_table, 'g');
    $query->fields('g', ['id']);
    $query->condition(
      'g.' . $entity_type->getKey('bundle'),
      $target_bundle
    );

    $content_data_table = $this->entityTypeManager
      ->getDefinition('group_content')
      ->getDataTable();
    $query->leftJoin(
      $content_data_table,
      'gcfd',
      'g.id = gcfd.gid'
    );

    $query->condition('gcfd.type', $this->getPluginIds(), 'IN');
    $query->condition('gcfd.entity_id', $configuration['entity']->id());

    if (isset($match) && $label_key = $entity_type->getKey('label')) {
      $condition = [
        'value' => $match,
        'operator' => $match_operator,
      ];

      // @I Load case sensitivity from the tables definition
      //    type     : bug
      //    priority : low
      //    labels   : context
      //    notes    : See \Drupal\Core\Entity\Query\Sql\Condition::compile().
      QueryCondition::translateCondition($condition, $query, FALSE);
      $query->condition(
        'g.' . $label_key,
        $condition['value'],
        $condition['operator']
      );
    }

    // Add entity-access tag.
    $query->addTag($target_type . '_access');

    // Add the Selection handler for system_query_entity_reference_alter().
    $query->addTag('entity_reference');
    $query->addMetaData('entity_reference_selection_handler', $this);

    // Add the sort option.
    if ($configuration['sort']['field'] !== '_none') {
      $query->orderBy(
        'g.' . $configuration['sort']['field'],
        $configuration['sort']['direction']
      );
    }

    return $query;
  }

  /**
   * Loads the plugin IDs for user memberships.
   *
   * We only load plugin IDs that define group content types for the group type
   * configured to provide contexts. Also, we assume the default user
   * membership plugin. It is extremely rare to have other plugins for the
   * `user` entity, and if there are they would most likely serve a different
   * purpose, so it's not worth making things more complicated.
   *
   * @return array
   *   An array containing the user membership plugin IDs.
   */
  protected function getPluginIds() {
    $group_type_id = $this->config->get('group_context.group_type');
    $group_content_types = $this->entityTypeManager
      ->getStorage('group_content_type')
      ->loadByProperties(
        ['group_type' => $group_type_id]
      );

    $plugin_ids = [];
    foreach ($group_content_types as $group_content_type) {
      $plugin_id = $group_content_type->getContentPluginId();
      if ($plugin_id !== 'group_membership') {
        continue;
      }

      $plugin_ids[] = $group_content_type->id();
    }

    if (!$plugin_ids) {
      throw new InvalidConfigurationException(
        sprintf(
          'Users are not configured to be available as group content for the `%s` group type.',
          $group_type_id
        )
      );
    }

    return $plugin_ids;
  }

}
