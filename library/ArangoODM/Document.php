<?php

namespace ArangoODM;

class Document
{
	protected static $documentHandler;
	protected $properties;
	protected $collectionName;
	
	function __construct($collectionName, array $properties = []) {
		$this->collectionName = $collectionName;
		$this->properties = $properties;
	}
	
	function getCollectionName() {
		return $this->collectionName;
	}
	
	static function setDocumentHandler(DocumentHandler $dh) {
		self::$documentHandler = $dh;
	}
	
	/**
	 * @return DocumentHandler|boolean
	 */
	function getDocumentHandler() {
		if (self::$documentHandler instanceof DocumentHandler) {
			return self::$documentHandler;
		} else {
			return false;
		}
	}
	
	function __set($property, $value) {
		$this->properties[$property] = $value;
	}
	
	function __get($property) {
		if (array_key_exists($property, $this->properties)) {
			return $this->properties[$property];
		} else {
			return null;
		}
	}
	
	function getId() {
		return $this->_id;
	}
	
	function getRawProperties() {
		return $this->properties;
	}
	
	protected function lazyGetNeighbor($targetCollectionName, $edgeCollectionName) {
		if (!array_key_exists($targetCollectionName, $this->properties)) {
			$target = $this->getDocumentHandler()->getNeighbor($this, $edgeCollectionName);
			if ($target) {
				$this->properties[$targetCollectionName] = $target;
			} else {
				return false;
			}
		}
		return $this->properties[$targetCollectionName];
	}
}
