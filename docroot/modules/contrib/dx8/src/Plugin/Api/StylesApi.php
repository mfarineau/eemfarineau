<?php

namespace Drupal\cohesion\Plugin\Api;

use Drupal\cohesion\ApiPluginBase;
use Drupal\cohesion\CohesionApiClient;
use Drupal\cohesion_custom_styles\Entity\CustomStyle;
use Drupal\Component\Serialization\Json;

/**
 * Class StyleApi
 *
 * @package Drupal\cohesion
 *
 * @Api(
 *   id = "styles_api",
 *   name = @Translation("Styles send to API"),
 * )
 */
class StylesApi extends ApiPluginBase {

  /**
   * The style forms to be processed by the API.
   *
   * @var array
   */
  private $forms = [];

  public function getForms() {
    return $this->forms;
  }

  public function setForms($forms) {
    $this->forms = $forms;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareData($attach_css = TRUE) {
    parent::prepareData($attach_css);

    // Reorder custom style styles
    $custom_styles = CustomStyle::loadParentChildrenOrdered();
    $style_order = [];
    if ($custom_styles) {
      foreach ($custom_styles as $custom_style) {
        $key = $custom_style->id() . '_' . $custom_style->getConfigItemId();
        $style_order[] = $key;
      }
    }

    $this->data->sort_order = $style_order;
    $this->data->style_group = 'cohesion_custom_style';
  }

  /**
   * {@inheritdoc}
   */
  public function callApi() {
    $this->response = CohesionApiClient::buildStyle($this->data);
  }

  /**
   * @inheritDoc
   */
  protected function processStyles($requestCSSTimestamp) {
    parent::processStyles($requestCSSTimestamp);

    foreach ($this->getData() as $styles) {

      if (isset($styles['css']) && $styles['themeName']) {

        $data = $styles['css'];
        $theme_id = $styles['themeName'];
        $running_dx8_batch = &drupal_static('running_dx8_batch');

        // Check to see if there are actually some stylesheets to process.
        if (isset($data['base']) && !empty($data['base']) && isset($data['theme']) && !empty($data['theme']) && isset($data['master']) && !empty($data['master'])) {

          // Create admin icon library and website settings stylesheet for admin.
          $master = Json::decode($data['master']);

          if (isset($master['cohesion_website_settings']['icon_libraries'])) {
            $destination = $this->localFilesManager->getStyleSheetFilename('icons');
            if (file_unmanaged_save_data($master['cohesion_website_settings']['icon_libraries'], $destination, FILE_EXISTS_REPLACE) && !$running_dx8_batch) {
              \Drupal::logger('cohesion')
                ->notice(t(':name stylesheet has been updated', [':name' => 'icon library']));
            }
          }

          if (isset($master['cohesion_website_settings']['responsive_grid_settings'])) {
            $destination = $this->localFilesManager->getStyleSheetFilename('grid');
            if (file_unmanaged_save_data($master['cohesion_website_settings']['responsive_grid_settings'], $destination, FILE_EXISTS_REPLACE) && !$running_dx8_batch) {
              \Drupal::logger('cohesion')
                ->notice(t(':name stylesheet has been updated', [':name' => 'Responsive grid']));
            }
          }
        }
      }
    }
  }

}
