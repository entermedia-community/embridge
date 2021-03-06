<?php
/**
 * @file
 * Contains \Drupal\embridge\EnterMediaAssetHelper.
 */

namespace Drupal\embridge;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser;
use Drupal\embridge\Plugin\Field\FieldType\EmbridgeAssetItem;
use Psr\Log\LoggerInterface;

/**
 * Class EnterMediaAssetHelper.
 *
 * @package Drupal\embridge
 */
class EnterMediaAssetHelper implements EnterMediaAssetHelperInterface {

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager.
   */
  protected $entityTypeManager;

  /**
   * Logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Mime type guesser service.
   *
   * @var \Drupal\Core\File\MimeType\MimeTypeGuesser
   */
  protected $mimeGuesser;

  /**
   * Constructs a new \Drupal\entity_pilot\Transport object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager class.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser $mime_guesser
   *   The mime type guesser service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManager $entity_type_manager, LoggerInterface $logger, MimeTypeGuesser $mime_guesser) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->mimeGuesser = $mime_guesser;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssetConversionUrl(EmbridgeAssetEntityInterface $asset, $application_id, $conversion) {
    $settings = $this->configFactory->get('embridge.settings');
    $uri = $settings->get('uri');

    $url = $uri . '/' . $application_id . '/views/modules/asset/downloads/preview/' . $conversion . '/' . $asset->getSourcePath() . '/thumb.jpg';

    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function searchResultToAsset($result, $catalog_id) {
    /** @var EntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('embridge_asset_entity');

    if ($asset = $this->loadFromAssetId($result['id'], $storage)) {
      return $asset;
    }

    $values = [
      'asset_id' => $result['id'],
      'source_path' => $result['sourcepath'],
      'filename' => $result['name'],
      'filesize' => $result['filesize'],
      'filemime' => $this->mimeGuesser->guess($result['name']),
      'catalog_id' => $catalog_id,
    ];

    /** @var EmbridgeAssetEntityInterface $asset */
    $asset = $storage->create($values);
    $asset->setTemporary();
    $asset->save();

    return $asset;
  }

  /**
   * Returns an asset entity given an asset ID.
   *
   * @param string $asset_id
   *   The asset ID property to check for.
   * @param EntityStorageInterface $storage
   *   The storage to load from.
   *
   * @return EmbridgeAssetEntityInterface|NULL
   *   Null if the asset didn't exist.
   */
  private function loadFromAssetId($asset_id, EntityStorageInterface $storage) {
    $query = $storage->getQuery();
    $query->condition('asset_id', $asset_id);
    $query_result = $query->execute();

    if ($query_result) {
      $id = array_pop($query_result);
      return $storage->load($id);
    }
    return NULL;
  }

  /**
   * Wraps a static function for easy testing.
   *
   * TODO: Do this better.
   *
   * @param array $settings
   *   An array of field settings.
   *
   * @return array
   *   An array of upload validators.
   */
  public function formatUploadValidators(array $settings) {
    return EmbridgeAssetItem::formatUploadValidators($settings);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTemporaryAssets() {
    // TODO: Do we want our own config item?
    $age = $this->configFactory->get('system.file')->get('temporary_maximum_age');

    if (!$age) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('embridge_asset_entity');
    $query = $storage->getQuery();

    $query->condition('status', FILE_STATUS_PERMANENT, '<>');
    $query->condition('changed', time() - $age, '<');
    $query->range(0, 50);
    $ids = $query->execute();

    /** @var EmbridgeAssetEntityInterface[] $assets */
    $assets = $storage->loadMultiple($ids);

    foreach ($assets as $asset) {
      $asset->delete();
      $this->logger->notice('Embridge Asset "%filename" [%id] garbage collected during cron.', ['%filename' => $asset->getFilename(), '%id' => $asset->id()]);
    }
  }

}
