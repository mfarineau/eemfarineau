<?php

namespace Drupal\cohesion;

use Drupal\cohesion\Entity\EntityJsonValuesInterface;
use Drupal\cohesion\Services\CohesionUtils;
use Drupal\cohesion_elements\Entity\CohesionLayout;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\token\TokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\cohesion\Services\LocalFilesManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Class ApiPluginBase.
 *
 * @package Drupal\cohesion
 */
abstract class ApiPluginBase extends PluginBase implements ApiPluginInterface, ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManager*/
  protected $streamWrapperManager;

  /**
   * @var \Drupal\Core\Utility\Token*/
  protected $tokenService;

  /**
   * @var \Drupal\cohesion\Services\LocalFilesManager*/
  protected $localFilesManager;

  /**
   * @var \Drupal\Core\Entity\EntityInterface|null*/
  protected $entity;

  /**
   * Whether the entity being processed is a Content entity or a config entity.
   *
   * @var bool
   */
  protected $isContent;

  /**
   * The config installer.
   *
   * @var \Drupal\Core\Config\ConfigInstallerInterface
   */
  protected $configInstaller;

  /**
   * The cohesion utils helper.
   *
   * @var \Drupal\cohesion\Services\CohesionUtils
   */
  protected $cohesionUtils;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * @var \Drupal\Core\Theme\ThemeManager
   */
  protected $themeManager;

  /**
   * The data to be sent to the API.
   *
   * @var object
   */
  protected $data;

  /**
   * Whether the stylesheet should return with a timestamp
   *
   * @var bool
   */
  protected $with_timestamp = TRUE;

  /**
   * @var null
   */
  protected $response = NULL;

  /**
   * Whether to save the data in database / files (templates, stylesheet)
   *
   * @var bool
   */
  protected $saveData = TRUE;

  /**
   * ApiPluginBase constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManager $stream_wrapper_manager
   * @param \Drupal\token\TokenInterface $token_service
   * @param \Drupal\cohesion\Services\LocalFilesManager $local_files_manager
   * @param \Drupal\Core\Config\ConfigInstallerInterface $config_installer
   * @param \Drupal\cohesion\Services\CohesionUtils $cohesion_utils
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, StreamWrapperManager $stream_wrapper_manager, TokenInterface $token_service, LocalFilesManager $local_files_manager, ConfigInstallerInterface $config_installer, CohesionUtils $cohesion_utils, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, ThemeManagerInterface $theme_manager, TranslationInterface $string_translation) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Save the injected services.
    $this->entityTypeManager = $entity_type_manager;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->tokenService = $token_service;
    $this->localFilesManager = $local_files_manager;
    $this->configInstaller = $config_installer;
    $this->cohesionUtils = $cohesion_utils;
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->themeManager = $theme_manager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('stream_wrapper_manager'),
      $container->get('token'),
      $container->get('cohesion.local_files_manager'),
      $container->get('config.installer'),
      $container->get('cohesion.utils'),
      $container->get('module_handler'),
      $container->get('theme_handler'),
      $container->get('theme.manager'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * @param \Drupal\cohesion\Entity\EntityJsonValuesInterface $entity
   */
  public function setEntity(EntityJsonValuesInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Get the data from the API call, if no data return an empty array.
   *
   * @return array
   */
  public function getData() {
    if (isset($this->response['data'])) {
      return $this->response['data'];
    }
    else {
      return [];
    }
  }

  /**
   * Get the styles for a particular type (base, theme) for a specific theme.
   *
   * @param string $style_type
   *   - the style type (base, theme, master)
   * @param string $theme_id
   *
   * @return string|null
   */
  public function getResponseStyles($style_type, $theme_id = 'current') {
    if ($theme_id == 'current') {
      $theme_id = $this->themeManager->getActiveTheme()->getName();
    }

    foreach ($this->getData() as $styles) {
      if ($theme_id == $styles['themeName'] && isset($styles['css'][$style_type])) {
        return $styles['css'][$style_type];
      }
    }

    return NULL;

  }

  /**
   * @param $to_save
   */
  public function setSaveData($to_save) {
    $this->saveData = boolval($to_save);
  }

  /**
   * @return bool
   */
  public function getSaveData() {
    return $this->saveData;
  }

  /**
   * @inheritDoc
   *
   * @param bool $attach_css
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function prepareData($attach_css = TRUE) {
    // Set up the data object that will be sent to the style API endpoint.
    $this->data = new \stdClass();
    $this->data->settings = new \stdClass();
    // Entities to be processed.
    $this->data->settings->forms = $this->getForms();
    $this->data->settings->timestamp = $this->getWithTimestamp();
    // Global website settings needed to build the entities.
    $this->data->settings->website_settings = new \stdClass();

    // Attach the website settings data (this is needed for every request).
    $website_settings_types = [
      'base_unit_settings',
      'responsive_grid_settings',
      'default_font_settings',
    ];

    $website_settings_storage = $this->entityTypeManager->getStorage('cohesion_website_settings');

    foreach ($website_settings_types as $website_settings_type) {
      // If the one of the form being saved is a website settings use it rather then the one
      // from the database as it has the latest data.
      /** @var \Drupal\cohesion_website_settings\Entity\WebsiteSettings $website_settings */
      foreach ($this->data->settings->forms as $form){
        if(isset($form['parent']) && is_object($form['parent']) && property_exists($form['parent'], 'type') && property_exists($form['parent'], 'bundle') &&
          $form['parent']->type == 'website_settings' && $form['parent']->bundle == $website_settings_type) {
          $this->data->settings->website_settings->$website_settings_type = $form['parent'];
        }
      }

      if(!property_exists($this->data->settings->website_settings, $website_settings_type)) {
        // Otherwise, load the entity in and use its json values.
        $website_settings = $website_settings_storage->load($website_settings_type);
        if ($website_settings) {
          $resource_object = $website_settings->getResourceObject();
          $this->data->settings->website_settings->$website_settings_type = $resource_object;
        }
      }
    }

    // Attach icon libraries (as fake website settings).
    $this->data->settings->website_settings->icon_libraries = $this->getIconGroup();
    // Attach the font stacks (as fake website settings).
    $this->data->settings->website_settings->font_libraries = $this->getFontGroup();
    $this->data->settings->website_settings->color_palette = $this->getColorGroup();
    $this->data->settings->website_settings->scss_variables = $this->getSCSSVariableGroup();

    $this->data->settings->style_guides = [];
    $this->data->css = [];
    foreach ($this->cohesionUtils->getCohesionEnabledThemes() as $theme_info) {
      if ($this->moduleHandler->moduleExists('cohesion_style_guide')) {
        // Attach the style guide manager for each theme.
        $style_guide_manager_handler = \Drupal::service('cohesion_style_guide.style_guide_handler');
        $style_guide_tokens = $style_guide_manager_handler->getTokenValues($theme_info);
        // Format the tokens for the API.
        foreach ($style_guide_tokens as &$style_guide_token) {
          $this->cohesionUtils->processTokenForApi($style_guide_token);
        }

        $base_theme = property_exists($theme_info, 'base_theme') ? $theme_info->base_theme : NULL;
        $this->data->settings->style_guides[$theme_info->getName()] = [
          'baseTheme' => $base_theme,
          'tokens' => $style_guide_tokens,
        ];
      }

      // Attach the JSON representation of the stylesheet for each non hidden theme with cohesion enabled to the data object.
      if (property_exists($theme_info, 'info') && is_array($theme_info->info) && (!isset($theme_info->info['hidden']) || $theme_info->info['hidden'] !== TRUE)) {
        if ($attach_css == TRUE) {
          $original_css_path = $this->localFilesManager->getStyleSheetFilename('json', $theme_info->getName());
          $this->data->css[$theme_info->getName()] = file_exists($original_css_path) ? file_get_contents($original_css_path) : '';
        }
        else {
          $this->data->css[$theme_info->getName()] = '';
        }
      }
    }
  }

  /**
   * Get combined icon library for settings and forms.
   *
   * @return array|string
   */
  public function getIconGroup() {
    $return = [
      'title' => 'Icon libraries',
      'type' => 'website_settings',
      'bundle' => 'icon_libraries',
    ];

    $icon_library_values = [];

    try {
      /** @var \Drupal\cohesion_website_settings\Entity\IconLibrary $icon_library_entity */
      foreach ($this->entityTypeManager->getStorage('cohesion_icon_library')
        ->loadMultiple() as $icon_library_entity) {
        $icon_library_values['iconLibraries'][] = ['library' => $this->patchUri($icon_library_entity->getDecodedJsonValues())];
      }
      $return['values'] = $icon_library_values;
    }
    catch (\Exception $e) {
      $return = [];
    }

    return $return;
  }

  /**
   * Get combined font stacks for settings and forms.
   *
   * @return array|string
   */
  public function getFontGroup() {
    $return = [
      'title' => 'Font stacks',
      'type' => 'website_settings',
      'bundle' => 'font_libraries',
    ];

    $font_stack_values = [];
    $font_library_values = [];

    try {
      /** @var \Drupal\cohesion_website_settings\Entity\FontStack $font_stack_entity */
      foreach ($this->entityTypeManager->getStorage('cohesion_font_stack')
        ->loadMultiple() as $font_stack_entity) {
        $font_stack_values[] = ['stack' => $this->patchUri($font_stack_entity->getDecodedJsonValues())];
      }

      $return['values'] = ['fontStacks' => $font_stack_values];

      /** @var \Drupal\cohesion_website_settings\Entity\FontLibrary $font_library_entity */
      foreach ($this->entityTypeManager->getStorage('cohesion_font_library')
        ->loadMultiple() as $font_library_entity) {
        $font_library_values[] = ['library' => $this->patchUri($font_library_entity->getDecodedJsonValues())];
      }

      $return['values']['uploadFonts'] = $font_library_values;
    }
    catch (\Exception $e) {
      $return = [];
    }

    return $return;
  }

  /**
   * Get combined font stacks for settings and forms.
   *
   * @return array|string
   */
  public function getColorGroup() {
    $return = [
      'title' => 'Color palette',
      'type' => 'website_settings',
      'bundle' => 'color_palette',
    ];

    $color_values = [];

    try {
      /** @var \Drupal\cohesion_website_settings\Entity\Color $color_entity */
      foreach ($this->entityTypeManager->getStorage('cohesion_color')
        ->loadMultiple() as $color_entity) {
        $color_values[] = $color_entity->getDecodedJsonValues();
      }
      $return['values'] = ['colors' => $color_values];
    }
    catch (\Exception $e) {
      $return = [];
    }

    return $return;
  }

  /**
   * Get combined scss variables for settings and forms.
   *
   * @return array|string
   */
  public function getSCSSVariableGroup() {
    $return = [
      'title' => 'SCSS variable',
      'type' => 'website_settings',
      'bundle' => 'scss_variable',
    ];

    $scss_variable_values = [];

    try {
      /** @var \Drupal\cohesion_website_settings\Entity\SCSSVariable $scss_variable_entity */
      foreach ($this->entityTypeManager->getStorage('cohesion_scss_variable')
        ->loadMultiple() as $scss_variable_entity) {
        $scss_variable_values[] = $scss_variable_entity->getDecodedJsonValues();
      }
      $return['values'] = ['variables' => $scss_variable_values];

    }
    catch (\Exception $e) {
      $return = [];
    }

    return $return;
  }

  /**
   * Replace URI to relative path for the API to process.
   *
   * @param $object
   *
   * @return mixed
   */
  private function patchUri($object) {
    foreach ($object as &$value) {
      if (is_array($value) || is_object($value)) {
        $value = $this->patchUri($value);
      }
      elseif (strpos($value, '://') !== FALSE) {
        if ($local_stream_wrappers = $this->streamWrapperManager->getWrappers(StreamWrapperInterface::ALL)) {
          foreach ($local_stream_wrappers as $scheme => $scheme_value) {
            $uri = $scheme . '://';
            if (strpos($value, $uri) === 0) {
              $stream_wrapper = $this->streamWrapperManager->getViaScheme($scheme);
              $stream_wrapper->setUri($value);
              $base_path = \Drupal::request()->getSchemeAndHttpHost();
              $value = str_replace($base_path, '', $stream_wrapper->getExternalUrl());
            }
          }
        }
      }
    }
    return $object;
  }

  /**
   * Extract the stylesheets from the $this->response and apply them (check
   * timestamps, etc).
   *
   * @param $requestCSSTimestamp
   *
   * @return bool
   */
  protected function processStyles($requestCSSTimestamp) {
    $running_dx8_batch = &drupal_static('running_dx8_batch');
    $currentCssTimestamp = $this->getStylesheetTimestamp();
    foreach ($this->getData() as $styles) {

      if (isset($styles['css']) && $styles['themeName']) {

        $data = $styles['css'];
        $theme_id = $styles['themeName'];

        // Check to see if there are actually some stylesheets to process.
        if (isset($data['base']) && !empty($data['base']) && isset($data['theme']) && !empty($data['theme']) && isset($data['master']) && !empty($data['master'])) {

          // First check to see if the stylesheets have updated since your request was made.
          if ($currentCssTimestamp != $requestCSSTimestamp) {
            drupal_set_message($this->t('The main stylesheet has been updated by another user since you saved. Please try again.'), 'error');
            return FALSE;
          }

          // Everything was fine, so attempt to apply the stylesheets.
          // Create directory if not exist.
          if (!is_dir(COHESION_CSS_PATH) && !file_exists(COHESION_CSS_PATH)) {
            \Drupal::service('file_system')
              ->mkdir(COHESION_CSS_PATH, 0777, FALSE);
          }

          // Save the main/master stylesheet json.
          if ($stylesheet_json_content = $data['master']) {
            $stylesheet_json_path = $this->localFilesManager->getStyleSheetFilename('json', $theme_id);
            file_unmanaged_save_data($stylesheet_json_content, $stylesheet_json_path, FILE_EXISTS_REPLACE);
          }

          // Smacss categories used by DX8.
          $style_types = [
            'base',
            'theme',
          ];

          foreach ($style_types as $section_name) {

            // Make sure the directory exists.
            $base_path = COHESION_CSS_PATH . '/' . $section_name;
            if (!is_dir($base_path) && !file_exists($base_path)) {
              \Drupal::service('file_system')->mkdir($base_path, 0777, FALSE);
            }

            // The filename...
            $destination = $this->localFilesManager->getStyleSheetFilename($section_name, $theme_id);
            $css_data = str_replace([
              "\r\n",
              "\n\n",
            ], "\n", ltrim($data[$section_name]));

            $css_data = \Drupal::service('twig')
              ->renderInline($css_data)
              ->__toString();

            // Save the file.
            if ($css_data && file_unmanaged_save_data($css_data, $destination, FILE_EXISTS_REPLACE) && !$running_dx8_batch) {
              \Drupal::logger('cohesion')
                ->notice($this->t(':name stylesheet has been updated', [':name' => $section_name]));

              // Get the success message from the class definition.
              if($this->entity){
                drupal_set_message(get_class($this->entity)::STYLES_UPDATED_SAVE_MESSAGE);
              }
            }
          }

          if (!$running_dx8_batch) {
            $this->localFilesManager->refreshCaches();
          }
        }
      }
    }
    // Generate cache busting token for wysiwyg cohesion styles.
    $wysiwyg_cache_token = \Drupal::keyValue('cohesion.wysiwyg_cache_token');
    $wysiwyg_cache_token->set('cache_token', uniqid());
    return TRUE;
  }

  /**
   * Get the (sub second) timestamp of last theme stylesheet that has last been
   * update.
   *
   * @return bool|int
   */
  protected function getStylesheetTimestamp() {
    $stylesheet_timestamp = 0;

    foreach ($this->themeHandler->listInfo() as $theme_info) {
      if ($this->cohesionUtils->themeHasCohesionEnabled($theme_info->getName())) {
        $originalCssPath = $this->localFilesManager->getStyleSheetFilename('json', $theme_info->getName());

        clearstatcache($originalCssPath);
        if (file_exists($originalCssPath) && filemtime($originalCssPath) > $stylesheet_timestamp) {
          $stylesheet_timestamp = filemtime($originalCssPath);
        }
      }
    }

    return $stylesheet_timestamp;
  }

  /**
   * Method performing the call to the cohesion API.
   *
   * @param $type
   *
   * @return bool
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function send() {
    // If in update.php mode, don't send.
    $dx8_no_send_to_api = &drupal_static('dx8_no_send_to_api');
    // Process entity if DX8 is enable && if is an entity, it is enable and we don't want to save the data.
    $cohesion_sync_lock = &drupal_static('cohesion_sync_lock');

    if ($dx8_no_send_to_api || !($this->cohesionUtils->usedx8Status()) ||
      ((isset($this->entity) && method_exists($this->entity, 'status') && !$this->entity->status()) && !$this->getSaveData()) ||
      ($cohesion_sync_lock) || $this->configInstaller->isSyncing()) {
      return TRUE;
    }

    $this->isContent = $this->entity instanceof CohesionLayout;

    // Don't attach the css to inject for content requests.
    $this->prepareData(!$this->isContent);

    // Whether the generated template should have its content translatable in interface translation.
    $this->data->translatable = !$this->isContent;

    $this->data->settings->forms = array_values($this->data->settings->forms);

    // Allow modules to manipulate the data before it's sent.
    $this->moduleHandler->alter('dx8_api_outbound_data', $this->data, $this->entity, $this->isContent);

    // Save the last time the main stylesheet was updated.
    $requestCSSTimestamp = $this->getStylesheetTimestamp();

    // Perform the send (this function exists on the child classes).
    $this->callApi();

    // Process the response.
    if ($this->response && floor($this->response['code'] / 200) == 1) {

      // If this a layout_field, just return the entire request (as it will be store inline in the field).
      if ($this->isContent || !$this->getSaveData()) {
        return TRUE;
      }

      // Attempt to process the stylesheets received back from the API (merge into the existing stylesheet).
      if ($this->processStyles($requestCSSTimestamp)) {
        return TRUE;
      }
      // Timestamp to the CSS is now later than when the request was made.
      else {
        return FALSE;
      }
    }
    else {
      if (isset($this->entity)) {
        if (($this->entity instanceof CohesionLayout) && $this->entity->getParentEntity()) {
          /* @var CohesionLayout $entity */
          $label = $this->entity->getParentEntity()->label();
          $entity_type = $this->entity->getParentEntity()
            ->getEntityType()
            ->getLabel();
        }
        else {
          /* @var \Drupal\Core\Entity\Entity $entity */
          $label = $this->entity->label();
          $entity_type = $this->entity->getEntityType()->getLabel();
        }
        \Drupal::logger('api-call-error')->error(
          $this->t('API Error while trying to save @entity_type - @label', [
            '@entity_type' => $entity_type,
            '@label' => $label,
          ])
        );
      }
    }

    return FALSE;
  }

  /**
   * Send data to be compiled to the API without saving any assets.
   */
  public function sendWithoutSave() {
    $this->setSaveData(FALSE);
    return $this->send();
  }

  /**
   * set build the stylesheets without timestamp prepended
   */
  public function setWithTimestamp($with_timestamp = TRUE) {
    $this->with_timestamp = $with_timestamp;
  }

  /**
   * get build the stylesheets without timestamp prepended
   */
  public function getWithTimestamp() {
    return $this->with_timestamp;
  }

  /**
   * Delete a style/template styles using the API and apply the modified
   * stylesheet.
   *
   * @return bool
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function delete() {
    // Prevent sending data to API if Use DX8 has error.
    if (!($this->cohesionUtils->usedx8Status()) || $this->configInstaller->isSyncing()) {
      return FALSE;
    }

    $this->prepareData();

    if (strstr($this->entity->getEntityTypeId(), '_template')) {
      $this->data->delete_id = $this->entity->getEntityTypeId() . '_' . $this->entity->id();
    }
    else {
      $this->data->delete_id = $this->entity->id() . '_' . $this->entity->getConfigItemId();
    }

    // Save the last time the main stylesheet was updated.
    $requestCSSTimestamp = $this->getStylesheetTimestamp();

    // Call the API to delete the entry from the stylesheet.
    $this->response = CohesionApiClient::buildDeleteStyle($this->data);

    if ($this->response && floor($this->response['code'] / 200) == 1) {

      // Attempt to process the stylesheets received back from the API (merge into the exist stylesheet).
      if ($this->processStyles($requestCSSTimestamp)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
