<?php

namespace ArangoODM;

class Object
{
	protected $properties;
	protected static $objectHandler;
	
	static function setObjectHandler(ObjectHandler $dh) {
		self::$objectHandler = $dh;
	}
	
	/**
	 * @return ObjectHandler|boolean
	 */
	function getObjectHandler() {
		return $this->objectHandler;
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
}
