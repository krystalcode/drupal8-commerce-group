<?php

namespace Drupal\gcommerce_context\Plugin\Field\FieldType;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a field type for referencing shopping contexts.
 *
 * @FieldType(
 *   id = "gcommerce_context_reference",
 *   label = @Translation("Shopping context reference"),
 *   description = @Translation("An entity field referencing shopping context(s)."),
 *   category = @Translation("Reference"),
 *   default_widget = "gcommerce_context_select",
 *   default_formatter = "entity_reference_label",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList",
 * )
 */
class ContextReferenceItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'target_type' => 'group',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'handler' => 'gcommerce:context',
      'handler_settings' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(
    array &$form,
    FormStateInterface $form_state,
    $has_data
  ) {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * Follows the parent method but it only makes available the context selection
   * entity reference selection plugin as handler.
   */
  public function fieldSettingsForm(
    array $form,
    FormStateInterface $form_state
  ) {
    $field = $form_state->getFormObject()->getEntity();

    $selection_plugin = \Drupal::service('plugin.manager.entity_reference_selection')
      ->getDefinitions()['gcommerce:context'];
    $handlers_options = [
      'gcommerce:context' => Html::escape($selection_plugin['label']),
    ];

    $form = [
      '#type' => 'container',
      '#process' => [[get_class($this), 'fieldSettingsAjaxProcess']],
      '#element_validate' => [[get_class($this), 'fieldSettingsFormValidate']],
    ];
    $form['handler'] = [
      '#type' => 'details',
      '#title' => t('Reference type'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#process' => [[get_class($this), 'formProcessMergeParent']],
    ];

    $form['handler']['handler'] = [
      '#type' => 'select',
      '#title' => t('Reference method'),
      '#options' => $handlers_options,
      '#default_value' => $field->getSetting('handler'),
      '#required' => TRUE,
      '#ajax' => TRUE,
      '#limit_validation_errors' => [],
      '#disabled' => TRUE,
    ];
    $form['handler']['handler_submit'] = [
      '#type' => 'submit',
      '#value' => t('Change handler'),
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => ['js-hide'],
      ],
      '#submit' => [[get_class($this), 'settingsAjaxSubmit']],
    ];

    $form['handler']['handler_settings'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity_reference-settings']],
    ];

    $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionHandler($field);
    $form['handler']['handler_settings'] += $handler->buildConfigurationForm([], $form_state);

    return $form;
  }

}
