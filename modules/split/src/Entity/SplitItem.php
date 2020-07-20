<?php

namespace Drupal\gcommerce_split\Entity;

use Drupal\user\EntityOwnerTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the default order item split item entity class.
 *
 * @ContentEntityType(
 *   id = "gcommerce_split_item",
 *   label = @Translation("Order split item"),
 *   label_singular = @Translation("order split item"),
 *   label_plural = @Translation("order split items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count order split item",
 *     plural = "@count order split items",
 *   ),
 *   base_table = "gcommerce_split_item",
 *   admin_permission = "administer commerce_order",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "owner" = "uid",
 *   },
 * )
 */
class SplitItem extends ContentEntityBase implements SplitItemInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getOrderItem() {
    return $this->get('order_item_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrderItemId() {
    return $this->get('order_item_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuantity() {
    return (string) $this->get('quantity')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setQuantity($quantity) {
    $this->set('quantity', (string) $quantity);
    $this->recalculateTotalPrice();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrice() {
    if (!$this->get('total_price')->isEmpty()) {
      return $this->get('total_price')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(
    EntityTypeInterface $entity_type
  ) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields['uid']
      ->setLabel(t('Customer'))
      ->setDescription(t('The customer for this split item.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['order_item_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Order item'))
      ->setDescription(t('The parent order item.'))
      ->setSetting('target_type', 'commerce_order_item')
      ->setReadOnly(TRUE);

    // The quantity of the purchased entity for the split item is defined either
    // as a quantity number, or as a measurement (area, length, volume,
    // weight). We only need to store the quantity and based on that we can
    // calculate the price; we can then use field widgets/formatters to render
    // the field as desired i.e. in the same format as the purchased entity's
    // field. Otherwise we would have to create 5 different fields, which
    // data-wise is unnecessary.
    // @I Add product variation type setting for configuring the split field
    //    type     : bug
    //    priority : high
    //    labels   : split
    $fields['quantity'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Quantity'))
      ->setDescription(t('The number of purchased units.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setSetting('min', 0)
      ->setDefaultValue(1)
      ->setDisplayOptions('form', [
        'type' => 'commerce_quantity',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['price'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Price'))
      ->setDescription(t('The price of the split item.'))
      ->setReadOnly(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the split item was created.'))
      ->setRequired(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the split item was last updated.'))
      ->setRequired(TRUE);

    return $fields;
  }

}
