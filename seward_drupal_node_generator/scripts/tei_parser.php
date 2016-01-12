<?php
//Class for parsing a TEI file - specific to the Seward Family Papers Project
//Turn off !$^!& warnings
libxml_use_internal_errors(false);
class TeiParser {
	/*
	*@var $filePath
	*@var $filename
	*@var $sender
	*@var $senderId
	*@var $receiver
	*@var $receiverId
	*@var $title
	*@var $year
	*@var $date
	*@var $nodeAlias
	*@var $personNodeIds
	*/

	public $filePath; // file field uri
	public $filename; // file field filename
	public $sender;
	public $senderId;
	public $receiver;
	public $receiverId;
	public $title;
	public $year;
	public $date;
	public $nodeAlias;
	public $personNodeIds;

	public function __construct($filePath) {
		$xmlArray = self::xmlIterator($filePath);
		$realPath = $xmlArray[0]->getRealPath(); //Leave this for now, but we will need to change this if there's more than one xml file in a directory
		$this->nodeAlias = self::nodeAlias($realPath);
		$this->filename = $xmlArray[0]->getFilename();
		$props = self::getTeiProps($xmlArray);

		//Assign all properties in constructor based on $props
		$this->sender = $props['sender'];
		$this->senderId = $props['senderId'];
		$this->receiver = $props['receiver'];
		$this->receiverId = $props['receiverId'];
		$this->personNodeIds = $props['personNodeIds'];
		$this->placeNodeIds = $props['placeNodeIds'];
		$this->title = $props['title'];
		$this->year = $props['year'];
		$this->date = $props['date'];

		//Set up streamwrap for attached file
		$this->filePath = self::streamWrapURI($realPath, $this->year);
	}

	public function __destruct() {
		echo "Killing " . $this->filename . " Tei Parser object \n";
	}

	public static function nodeAlias($path){
		$chunks = explode('/',$path);
		$sliced = array_slice($chunks, -2, 1);
		$alias = $sliced[0];
		return $alias;
	}

	public static function streamWrapURI($path, $year){
		$chunks = explode('/',$path);
		$sliced = array_slice($chunks, -2, 2);
		$streamWrap = 'letters://';
		$uri = $streamWrap . $year . '/' . $sliced[0] . '/' . $sliced[1];
		echo $uri;
		return $uri;
	}

	public static function xmlIterator($xmlPath) {
		/*@param $xmlPath - unix-style path to xml directory
		*@return $xmlArray - array of unix-style paths to xml files
		*/
		//Set up directory iterator
		$path = $xmlPath;
		$iteratorFlags =  FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;
		$fileIterator = new RecursiveDirectoryIterator($path, $iteratorFlags);
		$xmlArray = array(); // in case there are multiple xml files in one directory, which I don't think there are

		$i = 0;
		foreach ($fileIterator as $file) {
			if ($file->isFile()) {
					$extension = $file->getExtension();
				if ($extension == 'xml'){
					$xmlArray[$i] = $file;
					$i += 1;
				}
			}
		}
		echo "Found " . (string)$i . ' Files';
		if (sizeof($xmlArray > 0)) {
			return $xmlArray;
		} else {
			echo "Couldn't find any xml files - quitting";
			exit;
		}
	}

	public static function getTeiElementNIDs($teiElementArray, $attrPrefix){
		$nodeArray = array();
		$idUnknown = $prefix . 'unknown';
		$i = 0;
		foreach($teiElementArray as $nodes){
			$ref = $nodes['ref'];
			if ($ref != $idUnknown){
				$refSplit = explode('_', $ref);
				$nid = $refSplit[1];
				$nodeArray[$i] = $nid;
				$i ++;
			}
		}
		return $nodeArray;
	}

	public static function getTeiProps($xmlArray) {
		/*@param $xmlArray - array of unix-style paths to xml files
		*@return $propsArray - array of TEI properties
		*/
		$namespace = array('prefix' => 'tei', 'url' => 'http://www.tei-c.org/ns/1.0');
		$personsXML = simplexml_load_file('http://seward.lib.rochester.edu/tei/persons.xml');
		$personsXML->registerXPathNamespace($namespace['prefix'], $namespace['url']);
		$propsArray = array();
		foreach ($xmlArray as $xmlPath) {

				//Setup
			  $xmlFile = simplexml_load_file($xmlPath);
				$xmlFile->registerXPathNamespace($namespace['prefix'], $namespace['url']);

				//Title
				$title = $xmlFile->xpath("//tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:title");
				$propsArray['title'] = (string)$title[0];

				//Date
				$date = $xmlFile->xpath("//tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:msDesc/tei:history/tei:origin/tei:date/@when");
				$propsArray['date'] = (string)$date[0];

				//Year
				$year = explode("-",(string)$date[0]);
				$propsArray['year'] = $year[0];

			  //Sender info
				$sender = $xmlFile->teiHeader->profileDesc->correspDesc->correspAction[0]->persName->attributes();
				$senderRef = explode(":",$sender["ref"]);
				$senderName = $personsXML->xpath("//tei:text/tei:body/tei:div/tei:listPerson/tei:person[@xml:id = '" . $senderRef[1] . "']/tei:persName");
				$senderString = (string)$senderName[0]->surname . ', ' . (string)$senderName[0]->forename . ' ' . (string)$senderName[0]->forename[1];;
				$propsArray['sender'] = $senderString;
				$propsArray['senderId'] = $senderRef[1];

				//Receiver info
				$receiver = $xmlFile->teiHeader->profileDesc->correspDesc->correspAction[1]->persName->attributes();
				$receiverRef = explode(":",$receiver["ref"]);
				$receiverName = $personsXML->xpath("//tei:text/tei:body/tei:div/tei:listPerson/tei:person[@xml:id = '" . $receiverRef[1] . "']/tei:persName");
				$receiverString = (string)$receiverName[0]->surname . ', ' . (string)$receiverName[0]->forename[0] . ' ' . (string)$receiverName[0]->forename[1];
				$propsArray['receiver'] = $receiverString;
				$propsArray['receiverId'] = $receiverRef[1];

				/*get all node ids of people mentioned in the body of the letter - to be linked w/ the existing nodes on the public site. Write another script to
				* assign taxonomy term to the person node by loading in the node and setting up its nid as a taxonomy term.
				*/
				//node ids of all people in the letter
				$personPrefix = 'psn:';
				$personNodes = $xmlFile->text->body->ab->persName;
				$personNodeArray = TeiParser::getTeiElementNIDs($personNodes, $personPrefix);
				$propsArray['personNodeIds'] = $personNodeArray;

				//node ids of all places in the letter
				$placePrefix = 'pla:';
				$placeNodes = $xmlFile->text->body->ab->placeName;
				$placeNodeArray = TeiParser::getTeiElementNIDs($placeNodes, $placePrefix);
				$propsArray['placeNodeIds'] = $placeNodeArray;

			}
			return $propsArray;
		}
}
