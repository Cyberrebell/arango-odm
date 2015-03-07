<?php

namespace ArangoODM;

class Document extends Object
{
	private $collectionName;
	
	function __construct($collectionName, array $properties = []) {
		$this->collectionName = $collectionName;
		$this->properties = $properties;
	}
	
	function getCollectionName() {
		$classname = get_class($this);
		$classname = substr($classname, strrpos($classname, '\\') + 1);
		if($classname == 'Document') {
			return $this->collectionName;
		} else {
			return $classname;
		}
	}
	
	protected function lazyGetNeighbor($edgeCollection, $targetCollection, $filter = []) {
		$propertyAndFilterKey = $targetCollection . serialize($filter);
		if (!array_key_exists($propertyAndFilterKey, $this->properties)) {
			$target = $this->getObjectHandler()->getNeighbor($this, $edgeCollection, $filter);
			if ($target) {
				$this->properties[$propertyAndFilterKey] = $target;
			} else {
				return false;
			}
		}
		return $this->properties[$propertyAndFilterKey];
	}
	
	protected function lazyAddNeighbor($document, $edgeCollection, $target) {
		$this->getObjectHandler()->addNeighbor($document, $edgeCollection, $target);
	}
	
	protected function lazyRemoveNeighbor($document, $edgeCollection, $target) {
		$this->getObjectHandler()->removeNeighbor($document, $edgeCollection, $target);
	}
}
