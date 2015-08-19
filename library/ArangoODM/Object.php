<?php

namespace ArangoODM;

class Object
{
    protected $properties;
    protected static $objectHandler;
    
    public static function setObjectHandler(ObjectHandler $dh)
    {
        self::$objectHandler = $dh;
    }
    
    /**
     * @return ObjectHandler|boolean
     */
    public function getObjectHandler()
    {
        return $this->objectHandler;
    }
    
    public function __set($property, $value)
    {
        $this->properties[$property] = $value;
    }
    
    public function __get($property)
    {
        if (array_key_exists($property, $this->properties)) {
            return $this->properties[$property];
        } else {
            return null;
        }
    }
    
    public function getId()
    {
        return $this->_id;
    }
    
    public function getRawProperties()
    {
        return $this->properties;
    }
}
