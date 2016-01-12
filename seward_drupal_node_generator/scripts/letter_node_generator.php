<?php
//Based on Jeff S.'s node creator

//Setup
// this code bootstraps Drupal
define('DRUPAL_ROOT', '/usr/local/apache2/htdocs/');
define('VOCAB_RECEIVER_NAME', 'receiver' );
define('VOCAB_RECEIVER_ID', 'receiver_id' );
define('VOCAB_SENDER_NAME', 'sender' );
define('VOCAB_SENDER_ID', 'sender_id' );
define('VOCAB_PSN_NID', 'psn_nid');
define('VOCAB_PLACE_NID', 'pla_nid');
define('VOCAB_YEAR_NAME', 'year');


require_once 'tei_parser.php';
$_SERVER['REMOTE_ADDR'] = "localhost"; // Necessary if running from command line
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
require_once DRUPAL_ROOT . '/includes/file.inc';

class TaxonomyUtils {

	public $taxonomyArray;

//Creates term ids if they don't exist and returns an array of term ids

public static function getTermsFromArray($taxonomyArray) {
		$tidArray = array();
		for($i = 0; $i < sizeOf($taxonomyArray); $i++){
			$nid = $taxonomyArray[$i];
			$tid = self::checkTerms(VOCAB_PSN_NID, $nid);
			$tidArray[$i] = array('tid' => $tid);
		}
		return $tidArray;
}

public static function checkTerms($vocabName, $termName){
	/*@param $vocabName
	*@param $termName
	*@return $tid
	*/
	$vocab = taxonomy_vocabulary_machine_name_load($vocabName);
	$vid = $vocab->vid;
	//check term
	$checkTerm = taxonomy_get_term_by_name($termName, $vocabName);
	if (empty($checkTerm)){
		$term = new stdClass();
		$term->name = $termName;
		$term->vid = $vid;
		taxonomy_term_save($term);
		$termArray = taxonomy_get_term_by_name($termName, $vocabName);
		$term = current($termArray);
		$tid = $term->tid;
		return $tid;
	} else {
		$term = current($checkTerm);
		$tid = $term->tid;
		return $tid;
	}
}

}
// override default timeout and set the character encoding

set_time_limit(300);
ini_set('default_charset', 'utf-8');

//The Main Program

define('DIRECTORY', $argv[1]); //make directory constant - root directory where all letter subdirectories are located
$iteratorFlags =  FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;
$directoryIterator = new RecursiveDirectoryIterator(DIRECTORY, $iteratorFlags);
//Iterate over all subdirectories

foreach ($directoryIterator as $directory) {
	  $directoryString = $directory->getRealPath();
		$tei = new TeiParser($directoryString);

		//Create file node first for XML files
		$file = new stdClass();
		//XML file info
		$file->filename = $tei->filename;
		//Check if uri exists - if it does - get the info we need
		$fileArray = entity_load('file', FALSE, array('uri' => $tei->filePath));
		if (empty($fileArray)){
			$file->uri = $tei->filePath;
			$file->filemime = mime_content_type($tei->filePath);
			$file->filesize = filesize($tei->filePath);
			$file->timestamp = time();
			$file->uid = '1';

			//Save it
			$fileObj = file_save($file);

		} else {
			$fileObj = reset($fileArray);
		}


		//Drupal node instantiation
		$node = new stdClass(); // Create a new node object
		// this is where you set the node type, replace with whatever the machine name is for the node in question
		$node->type = "letterupload"; // Or page, or whatever content type you like
		node_object_prepare($node); // Set some default values
		$node->language = LANGUAGE_NONE; // Or e.g. 'en' if locale is enabled
		$node->uid = 1; // UID of the author of the node; or use $node->name

		//Title
		$node->title = $tei->title;

		//XML File
		$node->field_xml['und'][0]['fid'] = $fileObj->fid;
		$node->field_xml['und'][0]['uid'] = $fileObj->uid;
		$node->field_xml['und'][0]['uri'] = $fileObj->uri;
		$node->field_xml['und'][0]['filename'] = $fileObj->filename;
		$node->field_xml['und'][0]['filemime'] = $fileObj->filemime;
		$node->field_xml['und'][0]['filesize'] = $fileObj->filesize;
		$node->field_xml['und'][0]['timestamp'] = $fileObj->timestamp;
		$node->field_xml['und'][0]['display'] = 1;

		//Sender Taxonomy
		$senderTid = TaxonomyUtils::checkTerms(VOCAB_SENDER_NAME, $tei->sender);
		$node->field_sender['und'][0]['tid'] = $senderTid;
		$senderIdTid = TaxonomyUtils::checkTerms(VOCAB_SENDER_ID, $tei->senderId);
		$node->field_sender_id['und'][0]['tid'] = $senderIdTid;

		//Receiver Taxonomy
		$receiverTid = TaxonomyUtils::checkTerms(VOCAB_RECEIVER_NAME, $tei->receiver);
		$node->field_receiver['und'][0]['tid'] = $receiverTid;
		$receiverIdTid = TaxonomyUtils::checkTerms(VOCAB_RECEIVER_ID, $tei->receiverId);
		$node->field_receiver_id['und'][0]['tid'] = $receiverIdTid;

		//Letter People NID Taxonomy
		$psnNodeIdTidArray = TaxonomyUtils::getTermsFromArray($tei->personNodeIds);
		$node->field_person_node['und'] = $psnNodeIdTidArray;

		//Letter Place NID Taxonomy
		$placeNodeIdTidArray = TaxonomyUtils::getTermsFromArray($tei->placeNodeIds);
		$node->field_place_node['und'] = $placeNodeIdTidArray;

		//Year Taxonomy
		$yearTid = TaxonomyUtils::checkTerms(VOCAB_YEAR_NAME, $tei->year);
		$node->field_year['und'][0]['tid'] = $yearTid;

		//Date Field
		$node->field_date['und'][0]['value'] = $tei->date . 'T00:00:00';

		//Alias
		$node->path = array('alias' => $tei->nodeAlias);

		//Save
		$node = node_submit($node);
		node_save($node);

}

?>
