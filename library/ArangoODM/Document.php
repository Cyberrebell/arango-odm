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
	
	protected function lazyGetNeighbor($edgeCollection, $targetCollection, $filter = []) {
		$propertyAndFilterKey = $targetCollection . serialize($filter);
		if (!array_key_exists($propertyAndFilterKey, $this->properties)) {
			$target = $this->getDocumentHandler()->getNeighbor($this, $edgeCollection, $filter);
			if ($target) {
				$this->properties[$propertyAndFilterKey] = $target;
			} else {
				return false;
			}
		}
		return $this->properties[$propertyAndFilterKey];
	}
	
	protected function lazyAddNeighbor($document, $edgeCollection, $target) {
		$this->getDocumentHandler()->addNeighbor($document, $edgeCollection, $target);
	}
	
	protected function lazyRemoveNeighbor($document, $edgeCollection, $target) {
		$this->getDocumentHandler()->removeNeighbor($document, $edgeCollection, $target);
	}
}
