<?php

namespace Drupal\cohesion_elements\Plugin\Usage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\cohesion\UsagePluginBase;

/**
 * Class ComponentUsage
 *
 * @package Drupal\cohesion_elements\Plugin\Usage
 *
 * @Usage(
 *   id = "cohesion_component_usage",
 *   name = @Translation("Component usage"),
 *   entity_type = "cohesion_component",
 *   scannable = TRUE,
 *   scan_same_type = TRUE,
 *   group_key = "category",
 *   group_key_entity_type = "cohesion_component_category",
 *   exclude_from_package_requirements = FALSE,
 *   exportable = TRUE
 * )
 */
class ComponentUsage extends ElementUsageBase {

}
