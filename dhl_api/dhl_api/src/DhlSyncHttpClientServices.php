<?php

namespace Drupal\dhl_api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\dhl_api\Form\DhlSyncForm;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Yaml\Yaml;

/**
 * Http client for making DHL API request.
 */
class DhlSyncHttpClientServices {

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Private storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStore;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Create a KeyCloakHttpClient object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstore
   *   The private storage.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection object.
   */
  public function __construct(ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory, PrivateTempStoreFactory $tempstore, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, AccountInterface $account, MailManagerInterface $mail_manager, Connection $connection) {
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
    $this->privateTempStore = $tempstore;
    $this->configFactory = $config_factory;
    $this->dhlSettings = $this->configFactory->get(DhlSyncForm::DHL_API);
    $this->systemConfig = $config_factory->get('system.site');
    $this->entityTypeManager = $entity_type_manager;
    $this->account = $account;
    $this->mailManager = $mail_manager;
    $this->connection = $connection;
  }

  /**
   * Get the Product by Code.
   *
   * @param string $query
   *   Location as query
   */
  public function countryLookupBycode(string $query) {
    $this->privateTempStore->get('dhl_api')->set("yml_render",[]);
    try {
      $request_options = [
        'headers' => [
          'DHL-API-Key' => 'demo-key',
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
      ];

      $request = $this->httpClient->get("https://api.dhl.com/location-finder/v1/find-by-address?{$query}", $request_options);

      if ($data = Json::decode($request->getBody())) {
        $locations_filtered = [];
        foreach ($data['locations'] as $eo_key => $eo_value) {
          if ($this->checkOpeningHours($eo_value)) {
            if ($this->workOnWeekends($eo_value, $eo_value['openingHours'])) {
              $locations_filtered[$eo_key] =  $eo_value;
              $url_parts = array_values(array_filter(explode("/", $eo_value["url"])));
              if (!empty($url_parts[1])) {
                $get_location_numeric_part = (int) preg_replace("/[a-zA-Z]/", "",$url_parts[1]);
                //Skip odd numbers
                if ($get_location_numeric_part % 2 !== 0) {
                  continue;
                }
              }
              $res = preg_replace("/[^0-9]/", "",$eo_value["url"]);
              $yml_render[$eo_key] = $this->parseToYml($eo_value);
            }
          }
        }
        if (!empty($yml_render)) {
          $this->privateTempStore->get('dhl_api')->set("yml_render",$yml_render);
        }
      }

      
    }
    catch (RequestException $e) {
      $this->loggerFactory->get('dhl_api')->error($e->getMessage());
    }
  }

  public function checkOpeningHours(array $hours) {
    return (array_key_exists('openingHours', $hours) && !empty($hours['openingHours'])) ? TRUE : FALSE;
  }

  public function workOnWeekends(array $hours, array $working_hours) {
    if ($this->checkOpeningHours($hours)) {
      return count($working_hours) === 7 ? TRUE : FALSE;
    }
    return FALSE;
  }

  public function parseToYml(array $filtered_hours) {
    if (!empty($filtered_hours)) {
      return Yaml::dump($filtered_hours, 2,4);
    }
  }


}
