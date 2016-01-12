<?php
//Script for generating views based on filter
define('DRUPAL_ROOT', '/usr/local/apache2/htdocs/');
require_once 'tei_parser.php';
$_SERVER['REMOTE_ADDR'] = "localhost"; // Necessary if running from command line
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
require_once DRUPAL_ROOT . '/includes/file.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

$tei = new TeiParser($argv[1]);

var_dump($tei->filePath);
