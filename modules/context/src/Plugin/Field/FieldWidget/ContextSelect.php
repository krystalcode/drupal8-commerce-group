<?php

namespace Drupal\gcommerce_context\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;

/**
 * Widget for selecting shopping contexts.
 *
 * @FieldWidget(
 *   id = "gcommerce_context_select",
 *   label = @Translation("Shopping context select list"),
 *   field_types = {
 *     "gcommerce_context_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
class ContextSelect extends OptionsSelectWidget {

}
