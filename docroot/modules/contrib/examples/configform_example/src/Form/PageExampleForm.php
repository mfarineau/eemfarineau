<?php
  
/**
 * @file
 * Contains \Drupal\page_example\Form\PageExampleForm.
 */
  
namespace Drupal\page_example\Form;
  
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
 
class ConfigFormExampleConfigForm extends ConfigFormBase {
    
  /**
   * {@inheritdoc}.
   */
  public function getEditableConfigNames() {
    return ['configform_example.settings'];
  }
    
  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your .com email address.')
    ];
    $form['show'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }
    
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) { 
    if (strpos($form_state->getValue('email'), '.com') === FALSE) {
      $form_state->setErrorByName('email', $this->t('This is not a .com email address.'));
    }
  }
    
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message($this->t('Your email address is @email', ['@email' => $form_state->getValue('email')]));
  }
    
}
 
