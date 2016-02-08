<?php
/**
 * @file
 * Contains \Drupal\embridge\Plugin\Field\FieldType\EmbridgeAssetItem
 */

namespace Drupal\embridge\Plugin\Field\FieldType;

use Drupal\Component\Utility\Bytes;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;

/**
 * Plugin implementation of the 'file' field type.
 *
 * @FieldType(
 *   id = "embridge_asset_item",
 *   label = @Translation("Embridge asset item"),
 *   description = @Translation("This field stores the ID of an EnterMedia asset as an integer value"),
 *   category = @Translation("Reference"),
 *   default_widget = "embridge_asset_widget",
 *   default_formatter = "embridge_default",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList",
 *   constraints = {"ReferenceAccess" = {}, "EmbridgeAssetValidation" = {}}
 * )
 */
class EmbridgeAssetItem extends FileItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return array(
      'target_type' => 'embridge_asset_entity',
    ) + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return array(
      'application_id' => '',
    ) + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(
        'target_id' => array(
          'description' => 'The ID of the EM asset entity.',
          'type' => 'int',
          'unsigned' => TRUE,
        ),
        'display' => array(
          'description' => 'Flag to control whether this file should be displayed when viewing content.',
          'type' => 'int',
          'size' => 'tiny',
          'unsigned' => TRUE,
          'default' => 1,
        ),
        'description' => array(
          'description' => 'A description of the file.',
          'type' => 'text',
        ),
      ),
      'indexes' => array(
        'target_id' => array('target_id'),
      ),
      'foreign keys' => array(
        'target_id' => array(
          'table' => 'embridge_asset_entity',
          'columns' => array('target_id' => 'id'),
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = array();
    $settings = $this->getSettings();

    /** @var ConfigEntityStorage $application_storage */
    $application_storage = \Drupal::entityTypeManager()->getStorage('embridge_application');
    $application_query = $application_storage->getQuery();
    $entity_ids = array_keys($application_query->execute());
    $entities = $application_storage->loadMultiple($entity_ids);

    $options = [];
    foreach ($entities as $entity) {
      $options[$entity->id()] = $entity->label();
    }

    $element['application_id'] = array(
      '#type' => 'select',
      '#title' => t('Application'),
      '#default_value' => $settings['application_id'],
      '#options' => $options,
      '#description' => t("Select the Application to source media from for this field."),
      '#weight' => 6,
    );

    return $element + parent::fieldSettingsForm($form, $form_state);
  }


  /**
   * Retrieves the upload validators for a file field.
   *
   * @return array
   *   An array suitable for passing to file_save_upload() or the file field
   *   element's '#upload_validators' property.
   */
  public function getUploadValidators() {
    $validators = array();
    $settings = $this->getSettings();

    // Cap the upload size according to the PHP limit.
    $max_filesize = Bytes::toInt(file_upload_max_size());
    if (!empty($settings['max_filesize'])) {
      $max_filesize = min($max_filesize, Bytes::toInt($settings['max_filesize']));
    }

    // There is always a file size limit due to the PHP server limit.
    $validators['embridge_asset_validate_file_size'] = array($max_filesize);

    // Add the extension check if necessary.
    if (!empty($settings['file_extensions'])) {
      $validators['embridge_asset_validate_file_extensions'] = array($settings['file_extensions']);
    }

    return $validators;
  }

}