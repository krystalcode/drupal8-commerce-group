<?php

namespace Drupal\gcommerce_split\Hook;

/**
 * Holds methods implementing hooks provided by the `inline_entity_form` module.
 */
class InlineEntityForm {

  /**
   * Implements hook_inline_entity_form_table_fields_alter().
   *
   * Configure the table for inline entity form widgets referencing Split Items.
   *
   * @param array $fields
   *   The fields, keyed by field name.
   * @param array $context
   *   An array with the following keys:
   *   - parent_entity_type: The type of the parent entity.
   *   - parent_bundle: The bundle of the parent entity.
   *   - field_name: The name of the reference field on which IEF is operating.
   *   - entity_type: The type of the referenced entities.
   *   - allowed_bundles: Bundles allowed on the reference field.
   *
   * @see \Drupal\inline_entity_form\InlineFormInterface::getTableFields()
   *
   * @I Disable draggable behavior on split item inline entity form widget
   *    type     : bug
   *    priority : low
   *    labels   : form, ux
   */
  public function tableFieldsAlter(array &$fields, array $context) {
    if ($context['entity_type'] !== 'gcommerce_split_item') {
      return;
    }

    $fields['uid'] = [
      'type' => 'field',
      'label' => t('Customer'),
      'weight' => 0,
    ];
    $fields['quantity'] = [
      'type' => 'field',
      'label' => t('Quantity'),
      'weight' => 1,
    ];
    $fields['price'] = [
      'type' => 'field',
      'label' => t('Price'),
      'weight' => 2,
    ];

    unset($fields['label']);
  }

}
