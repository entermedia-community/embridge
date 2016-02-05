<?php
/**
 * @file
 * Contains \Drupal|embridge\EnterMediaDbClient.
 */

namespace Drupal\embridge;


use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystem;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\SessionCookieJar;
use GuzzleHttp\Exception\RequestException;

class EnterMediaDbClient implements EnterMediaDbClientInterface {

  const EMBRIDGE_LOGIN_PATH_DEFAULT = 'mediadb/services/authentication/login';
  const EMBRIDGE_UPLOAD_PATH_DEFAULT = 'mediadb/services/module/asset/create';

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
   * File system helper.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Whether we have logged in or not.
   *
   * @var bool
   */
  protected $loggedIn;

  /**
   * A cookie jar object to use between requests.
   *
   * @var SessionCookieJar
   */
  protected $cookieJar;

  /**
   * Constructs a new \Drupal\entity_pilot\Transport object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $client
   *   The http client service.
   * @param \Drupal\Component\Serialization\SerializationInterface $serializer
   *   The json serializer service.
   * @param \Drupal\Core\File\FileSystem
   *   The file system service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $client, SerializationInterface $serializer, FileSystem $file_system) {
    $this->configFactory = $config_factory;
    $this->jsonEncoder = $serializer;
    $this->httpClient = $client;
    $this->fileSystem = $file_system;
    $this->loggedIn = FALSE;
    $this->cookieJar = new SessionCookieJar('SESSION_STORAGE', TRUE);
  }

  /**
   * Sends a request with the configuration provided.
   *
   * @param string $path
   *   An optional path relative to the base uri in configuration.
   * @param [] $body
   *   An optional body to attach to the request.
   * @param string $method
   *   The method to use on the request, defaults to POST.
   *
   * @return []
   *   An array of the body of the response from the server.
   *
   * @throws \Exception
   *   When something goes wrong with the request (i.e 403).
   */
  protected function doRequest($path = '', $body = [], $method = 'POST') {
    $settings = $this->configFactory->get('embridge.settings');
    $uri = $settings->get('uri');
    $port = $settings->get('port');
    $uri = sprintf('%s/%s', $uri, $path);
    $options = [
      'timeout' => 5,
      'cookies' => $this->cookieJar,
    ];
    if (!empty($body)) {
      $options['_body_as_string'] = TRUE;
      $options['body'] = $this->jsonEncoder->encode($body);
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

    $body = $this->jsonEncoder->decode((string) $response->getBody());

    if (empty($body['response']['status']) || $body['response']['status'] != 'ok') {
      throw new \Exception(sprintf('The request to EnterMedia failed.'));
    }

    return $body;
  }

  /**
   * {@inheritdoc}
   */
  public function login() {
    if ($this->loggedIn) {
      return TRUE;
    }

    $config = $this->configFactory->get('embridge.settings');
    $body = [
      'id' => $config->get('username'),
      'password' => $config->get('password'),
    ];

    $body = $this->doRequest(self::EMBRIDGE_LOGIN_PATH_DEFAULT, $body);

    if (!empty($body['results']['status']) && $body['results']['status'] == 'invalidlogin') {
      return FALSE;
    }

    $this->loggedIn = TRUE;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function upload(EmbridgeAssetEntityInterface $asset) {
    $this->login();

    $body = [
      'catalogid' => 'media/catalogs/public',
      'sourcepath' => 'demo/2016/02/',
      'file' => '@' . $this->fileSystem->realpath($asset->getSourcePath()),
    ];
    $body = $this->doRequest(self::EMBRIDGE_UPLOAD_PATH_DEFAULT, $body);

    $remote_asset = (array) $body['hit'];

    return $asset;
  }
}