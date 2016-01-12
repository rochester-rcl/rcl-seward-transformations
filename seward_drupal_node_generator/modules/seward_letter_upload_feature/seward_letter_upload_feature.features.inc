<?php
/**
 * @file
 * seward_letter_upload_feature.features.inc
 */

/**
 * Implements hook_node_info().
 */
function seward_letter_upload_feature_node_info() {
  $items = array(
    'letterupload' => array(
      'name' => t('letterUpload'),
      'base' => 'node_content',
      'description' => '',
      'has_title' => '1',
      'title_label' => t('Title'),
      'help' => '',
    ),
  );
  drupal_alter('node_info', $items);
  return $items;
}
