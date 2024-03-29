<?php

namespace Drupal\cohesion\Entity;

/**
 * Interface UsagePluginInterface.
 *
 * @package Drupal\cohesion
 */
interface EntityJsonValuesInterface {

  /**
   * Return json_values field data for angular form.
   *
   * @return string
   *   Return the JSON values data.
   */
  public function getJsonValues();

  /**
   * Return the decoded JSON values data.
   *
   * @param bool $as_object
   *
   * @return mixed
   */
  public function getDecodedJsonValues($as_object = FALSE);

  /**
   * Set the json_values field data for angular form.
   *
   * @param string $json_values
   *   The json_values field from the angular form.
   *
   * @return string
   *   Return the JSON values data that was set.
   */
  public function setJsonValue($json_values);

  /**
   * Process entity to be validated.
   *
   * @return array
   *   Return processed values or errors
   */
  public function jsonValuesErrors();

  /**
   * Process json_values to the API.
   *
   * @return \Drupal\cohesion\SendToApiBase
   */
  public function process();

  /**
   * Determine if the config is a layout canvas the returns a template.
   *
   * @return bool
   */
  public function isLayoutCanvas();

  /**
   * Get the LayoutCanvas entity for this entity.
   *
   * @return \Drupal\cohesion\LayoutCanvas\LayoutCanvas|bool
   */
  public function getLayoutCanvasInstance();

  /**
   * Return the API plugin instance for this entity
   *
   * @return \Drupal\cohesion\ApiPluginInterface
   */
  public function getApiPluginInstance();

}
