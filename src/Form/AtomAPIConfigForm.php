<?php

namespace Drupal\atom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class AtomAPIConfigForm.
 */
class AtomAPIConfigForm extends ConfigFormBase
{
  private $noTags = 1;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return [
      'atom.atomapiconfig',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'atom_api_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('atom.atomapiconfig');

    $form['container'] = array(
      '#type' => 'container',
      '#prefix' => $this->t('<div class="clearfix">'),
      '#suffix' => $this->t('</div>')
    );

    $form['container']['api-config'] = array(
      '#type' => 'fieldset',
      '#title' => 'API Configuration',
      '#attributes' => ['class' => ['layout-column layout-column--half'], 'style' => "width:45% !important"],
    );

    $form['container']['api-config']['atom-host'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Atom Host:'),
      '#required' => TRUE,
      '#default_value' => ($config->get("host") !== null) ? $config->get("host") : ""
    );

    $form['container']['api-config']['atom-api-key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('REST API key:'),
      '#required' => TRUE,
      '#default_value' => ($config->get("atom-api-key") !== null) ? $config->get("atom-api-key") : ""
    );

    $form['container']['api-config']['atom-repoid'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Repo ID(s):'),
      '#description' => $this->t('<p>Separate multiple repo IDs with a comma ",".</p>'),
      '#pattern' => '^\d+(,\d+)*$',
      '#default_value' => ($config->get("atom-repoid") !== null) ? $config->get("atom-repoid") : ""
    );

    $form['container']['api-config']['submit-save-config'] = array(
      '#type' => 'submit',
      '#name' => "submit-save-config",
      '#value' => "Save",
      '#attributes' => ['class' => ["button button--primary"]],
      '#submit' => array([$this, 'submitForm'])
    );

    $form['container']['manually'] = array(
      '#type' => 'fieldset',
      '#title' => 'Manually Download Holdings',
      '#attributes' => ['class' => ['layout-column layout-column--half'], 'style' => "left: 10px !important"],
    );
    $form['container']['manually']['description'] = array(
      '#markup' => $this->t('<p>Download Holdings process will be run when the <a href="admin/config/system/cron">scheduled cron</a> runs. However, it can be run immediately by clicking the Download button below.</p>')
    );

    $form['container']['manually']['submit-manually-download-holdings'] = array(
      '#type' => 'submit',
      '#name' => "submit-manually-download",
      '#value' => "Download",
      '#attributes' => ['class' => ["button button--primary"]],
      '#submit' => array([$this, 'submitFormManuallyDownloadHoldings'])
    );

    return $form;
    //return parent::buildForm($form, $form_state);
  }

  /**
   * Submit handler clear session variables
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function submitFormReset(array &$form, FormStateInterface $form_state)
  {
    $tempstore = \Drupal::service('tempstore.private')->get('atom.api.testing');
    $tempstore->set('output_accesstoken', print_r("", true));
    $tempstore->set('output_holdings', print_r("", true));
    $tempstore->set('output_holdings', print_r("", true));
    $tempstore->set('testing-atom-repoid', "");
  }

  /**
   * Submit handler manually download Holdings from Atom
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function submitFormManuallyDownloadHoldings(array &$form, FormStateInterface $form_state)
  {
    startDownloadAtomHoldings();

    $messenger = \Drupal::messenger();
    $messenger->addMessage('Successfully downloaded Discover Archive holdings.');
  }

  /**
   * Submit handler Post request to obtain access token
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function submitFormAccessToken(array &$form, FormStateInterface $form_state)
  {
    $service = \Drupal::service('atom.download');
    $result = $service->postAccessToken();

    $tempstore = \Drupal::service('tempstore.private')->get('atom.api.testing');
    $tempstore->set('output_accesstoken', print_r($result, true));
  }

  /**
   * Submit handler download Holdings data
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function submitFormHolding(array &$form, FormStateInterface $form_state)
  {
    $service = \Drupal::service('atom.download');
    $result = $service->get("holdings");

    $tempstore = \Drupal::service('tempstore.private')->get('atom.api.testing');
    $tempstore->set('output_holdings', print_r($result, true));
  }

  /**
   * Submit handler download Holdings data
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function submitFormHoldings(array &$form, FormStateInterface $form_state)
  {

    $service = \Drupal::service('atom.download');
    $result = $service->get($form_state->getValues()['atom-repoid'])->holdings;
    $tempstore = \Drupal::service('tempstore.private')->get('atom.api.testing');
    $tempstore->set('output_holdings', print_r($result, true));
    $tempstore->set('testing-atom-repoid', $form_state->getValues()['atom-repoid']);

    // process holdings data to Holding nodes
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $configFactory = $this->configFactory->getEditable('atom.atomapiconfig');
    $configFactory
      ->set('host', $form_state->getValues()['atom-host'])
      ->set('atom-api-key', $form_state->getValues()['atom-api-key'])
      ->set('atom-repoid', $form_state->getValues()['atom-repoid']);



    $configFactory->save();
    parent::submitForm($form, $form_state);
  }

}
