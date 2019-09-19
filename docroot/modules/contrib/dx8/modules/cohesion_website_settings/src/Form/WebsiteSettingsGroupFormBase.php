<?php

namespace Drupal\cohesion_website_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\cohesion\EntityGroupsPluginManager;
use \Drupal\Core\TempStore\PrivateTempStore;

/**
 * Class WebsiteSettingsGroupFormBase
 *
 * The base for form the color, icon library and font stack bulk edit form.
 *
 * @package Drupal\cohesion_website_settings\Form
 */
abstract class WebsiteSettingsGroupFormBase extends ConfigFormBase {

  const ENTITY_TYPE = NULL;

  const FORM_TITLE = NULL;

  const FORM_ID = NULL;

  const FORM_CLASS = NULL;

  const NG_ID = NULL;

  /**
   * The entity type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|null
   */
  protected $entity_type_definition;

  /**
   * Holds the storage manager for the entity.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The instance of the entity groups plugin.
   *
   * @var object
   */
  protected $entityGroupsPlugin;

  /**
   * @var object
   */
  protected $entityGroupsManager;

  /**
   * @var int
   */
  protected $step = 1;

  /**
   * Holds data between form steps.
   *
   * @var array
   */
  protected $in_use_list;

  /**
   * Holds data between form steps.
   *
   * @var array
   */
  protected $changed_entities;

  /**
   * WebsiteSettingsGroupFormBase constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\cohesion\EntityGroupsPluginManager $entity_groups_manager
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, EntityGroupsPluginManager $entity_groups_manager) {
    parent::__construct($config_factory);
    $this->storage = $entity_type_manager->getStorage(get_class($this)::ENTITY_TYPE);
    $this->entityGroupsManager = $entity_groups_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('entity_type.manager'), $container->get('plugin.manager.entity_groups.processor'));
  }

  /**
   * Return an instance of the entity groups plugin. This is done dynamically
   * because the form can clear this value to avoid serialization problems when
   * switching between form steps.
   *
   * @return object
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getEntityGroupsPlugin() {
    if (!$this->entityGroupsPlugin) {
      // Create an instance of the entity groups plugin to use later on.
      $this->entityGroupsPlugin = $this->entityGroupsManager->createInstance(get_class($this)::PLUGIN_ID);
    }

    return $this->entityGroupsPlugin;
  }

  /**
   * Get the title based on the type of entity passed in.
   *
   * @return string
   */
  public function getTitle() {
    return t(get_class($this)::FORM_TITLE);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cohesion.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return get_class($this)::FORM_ID;
  }

  /**
   * Build the JSON string for the main json_values textarea.
   *
   * @return string
   */
  protected function buildJsonValues() {
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    if ($this->step == 1) {
      // Tell the app this is a website setting (mostly for styling).
      $form['#attributes']['class'][] = 'cohesion-website-settings-form';

      // The app.
      $form['cohesion'] = [
        // Drupal\cohesion\Element\CohesionField.
        '#type' => 'cohesionfield',
        '#json_values' => empty($form_state->getUserInput()) ? $this->getEntityGroupsPlugin()->getGroupJsonValues() : $form_state->getUserInput()['json_values'],
        '#json_mapper' => '{}', //$jsonMapper,
        '#entity' => NULL,
        '#classes' => [
          'cohesion-website-settings-edit-form',
          get_class($this)::FORM_CLASS,
        ],
        //'#entity' => $entity,
        '#ng-init' => [
          'group' => 'website_settings',  // $entity->getAssetGroupId(),
          'id' => get_class($this)::NG_ID,  // $entity->id(),
        ],
      ];

      // Change save button text.
      $form['actions']['submit']['#value'] = t('Save');
    }
    else {
      // Set page title and warning (base_unit_settings only).
      $form['#title'] = $this->t('Are you sure you want to update the website settings?');

      $form['markup'] = [
        '#markup' => t('You are about to change core website settings. This will rebuild styles and templates and flush the render cache.'),
      ];

      // Change save button text.
      $form['actions']['submit']['#value'] = t('Rebuild');
      $form['actions']['submit']['#type_value'] = 'rebuild';

      // Add cancel button.
      $form['actions']['cancel'] = $form['actions']['submit'];
      $form['actions']['cancel']['#value'] = t('Cancel');
      $form['actions']['cancel']['#type_value'] = 'cancel';
      $form['actions']['cancel']['#button_type'] = 'secondary';
      $form['actions']['cancel']['#access'] = TRUE;
      $form['actions']['cancel']['#weight'] = 10;
    }

    // Add the shared attachments.
    _cohesion_shared_page_attachments($form);

    return $form;
  }

  /**
   * Submit handler.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  protected function stepOneSubmit(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    if ($this->step == 1) {
      $this->stepOneSubmit($form, $form_state);
    }
    elseif ($this->step > 1) {
      $triggering_element = $form_state->getTriggeringElement();

      // Cancel button.
      if ($triggering_element['#type_value'] == 'cancel') {
        // Just back to the list.
        $this->step = 1;
        return;
      }
      // Rebuild button.
      elseif ($triggering_element['#type_value'] == 'rebuild') {
        // Save the data to storage.
        /** @var PrivateTempStore $tempstore */
        $tempstore = \Drupal::service('user.private_tempstore')->get('website_settings');
        $tempstore->set('in_use_list', $this->in_use_list);
        $tempstore->set('changed_entities', $this->changed_entities);

        // Redirect to the color rebuild batch.
        $form_state->setRedirect('cohesion_website_settings.inuse_batch_resave');
      }
    }
  }

}