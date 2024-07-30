<?php

namespace Drupal\dhl_api\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Render\Markup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * Configure DHL API sync form.
 *
 * @package Drupal\dhl_api\Form
 */
class DhlSyncForm extends ConfigFormBase {

  const DHL_API = 'dhl_api.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return self::DHL_API;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [self::DHL_API];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $dhl_api_settings = $this->config(self::DHL_API);

    $form['eo_settings'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#title' => $this->t('Settings'),
      '#open' => TRUE,
    ];

    $form['eo_settings']['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter Country Name'),
      '#maxlength' => 255,
      '#default_value' => $dhl_api_settings->get('country'),
      '#required' => TRUE,
    ];

    $form['eo_settings']['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter City name'),
      '#maxlength' => 255,
      '#default_value' => $dhl_api_settings->get('city'),
      '#required' => TRUE,
    ];

    $form['eo_settings']['pin'] = [
      '#type' => 'number',
      '#title' => $this->t('Enter Pin Code'),
      '#maxlength' => 255,
      '#default_value' => $dhl_api_settings->get('pin'),
      '#required' => FALSE,
      '#min' => 0,
      '#max' => 999999,
    ];

    $store = \Drupal::service('tempstore.private')->get('dhl_api');
    $data = $store->get('yml_render');
    $markup = NULL;
    if (!empty($data)) {
      $form['avalilable location'] = [
        '#type' => 'details',
        '#tree' => TRUE,
        '#title' => $this->t('API Output'),
        '#open' => TRUE,
      ];
      
      foreach ($data as $key => $value) {
        $form['avalilable location'][$key]['code'] = [
          '#type' => 'textarea',
          '#default_value' => "<code>{$value}</code>\n\n",
          '#weight' => $key,
          '#size' => 100,
        ];
      }
    }


    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eo_settings = $form_state->getValue('eo_settings');
    $this->config(self::DHL_API)
      ->set('country', $eo_settings['country'])
      ->set('city', $eo_settings['city'])
      ->set('pin', $eo_settings['pin'])
      ->save();
    $query_string = "countryCode={$eo_settings['country']}&addressLocality={$eo_settings['city']}&postalCode={$eo_settings['pin']}";
    if ($query_string !== "") {
      if ($knpi_exact_online_webshop_token_service = \Drupal::service('dhl_api.eo_client')) {
        $knpi_exact_online_webshop_token_service->countryLookupBycode($query_string);
        $form['markup'] = ["#markup" => "DONE"];
      }
    }
    parent::submitForm($form, $form_state);
  }
}

