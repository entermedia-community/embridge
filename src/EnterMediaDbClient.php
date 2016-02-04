<?php
/**
 * @file
 * Contains \Drupal|embridge\EnterMediaDbClient.
 */

namespace Drupal\embridge;


use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\embridge\Entity\EmbridgeAssetEntity;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

class EnterMediaDbClient implements EnterMediaDbClientInterface {

  const EMBRIDGE_LOGIN_PATH_DEFAULT = 'media/services/rest/login.xml';
  const EMBRIDGE_UPLOAD_PATH_DEFAULT = 'media/services/rest/upload.xml';

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * JSON Encoder.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $jsonEncoder;

  /**
   * Whether we have logged in or not.
   *
   * @var bool
   */
  protected $loggedIn;

  /**
   * Constructs a new \Drupal\entity_pilot\Transport object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $client
   *   The http client service.
   * @param \Drupal\Component\Serialization\SerializationInterface $serializer
   *   The json serializer service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $client, SerializationInterface $serializer) {
    $this->configFactory = $config_factory;
    $this->jsonEncoder = $serializer;
    $this->httpClient = $client;
    $this->loggedIn = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function doRequest($path = '', $body = [], $method = 'POST') {
    $settings = $this->configFactory->get('embridge.settings');
    $uri = $settings->get('uri');
    $port = $settings->get('port');
    $uri = sprintf('%s:%s/%s', $uri, $port, $path);
    $options = [
      'timeout' => 5,
      'cookies' => $this->cookieJar,
    ];
    if (!empty($body)) {
      $options['form_params'] = $body;
    }

    try {
      $response = $this->httpClient->request($method, $uri, $options);
    }

    catch (RequestException $e) {
      $response = $e->getResponse();
      if ($response === NULL) {
        throw new \Exception('Error connecting to EMDB backend');
      }
      if ($response->getStatusCode() == 403) {
        throw new \Exception('Failed to authenticate with EMDB, please check your settings.');
      }
    }
    if ($response->getStatusCode() != '200') {
      throw new \Exception('An unexpected response was returned from the Entity Pilot backend');
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function login() {
    if ($this->loggedIn) {
      return TRUE;
    }

    $config = $this->configFactory->get('embridge.settings');
    $query_params = [
      'catalogid' => 'media',
      'accountname' => $config->get('username'),
      'password' => $config->get('password'),
    ];
    $query = UrlHelper::buildQuery($query_params);
    $login_path = self::EMBRIDGE_LOGIN_PATH_DEFAULT . '?' . $query;

    $response = $this->doRequest($login_path);

    $body = (string) $response->getBody();
    $xml_obj = simplexml_load_string($body);
    $xml_arr = (array) $xml_obj;
    if (!empty($xml_arr['@attributes']['stat']) && $xml_arr['@attributes']['stat'] == 'ok') {
      $this->loggedIn = TRUE;
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function upload(EmbridgeAssetEntityInterface $asset) {
    $this->login();

    $response = '';
    return $response;
  }
}