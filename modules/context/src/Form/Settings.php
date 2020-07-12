<?php

namespace Drupal\gcommerce_context\Form;

use Drupal\commerce\EntityHelper;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure shopping context-related settings.
 */
class Settings extends ConfigFormBase {

  /**
   * The group type storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $groupTypeStorage;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->setConfigFactory($config_factory);
    $this->groupTypeStorage = $entity_type_manager->getStorage('group_type');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gcommerce_context_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'gcommerce_context.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('gcommerce_context.settings');

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable shopping context'),
      '#description' => $this->t(
        'Enabling shopping context will activate cart isolation per context'
      ),
      '#default_value' => $config->get('status'),
    ];

    $form['personal_context'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow personal shopping context'),
      '#description' => $this->t(
        'Allowing personal shopping context will permit users to make purchases
        for themselves i.e. outside of the context of a group.'
      ),
      '#default_value' => $config->get('personal_context.status'),
      '#states' => [
        'visible' => [
          ':input[data-drupal-selector="edit-status"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $group_types = $this->groupTypeStorage->loadMultiple();
    $form['group_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Shopping context group type'),
      '#description' => $this->t(
        'The groups that will be the available shopping contexts for an
        authenticated user will be the ones that are of the selected group type
        and that the user is a member of.'
      ),
      '#options' => EntityHelper::extractLabels($group_types),
      '#default_value' => $config->get('group_context.group_type'),
      '#states' => [
        'visible' => [
          ':input[data-drupal-selector="edit-status"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->configFactory->getEditable('gcommerce_context.settings');

    if (!$values['status']) {
      $config->set('status', FALSE)->save();
      parent::submitForm($form, $form_state);
      return;
    }

    $config->set('status', TRUE)
      ->set('group_context.group_type', $values['group_type'])
      ->set(
        'personal_context.status',
        $values['personal_context'] ? TRUE : FALSE
      )
      ->save();

    parent::submitForm($form, $form_state);
  }

}
