<?php

namespace ArangoODM;

class Object
{
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
}
