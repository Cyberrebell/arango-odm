<?php

namespace ArangoOdm;

class Document
{
    private $collectionName;
    protected $properties;
    protected static $documentManager;
    
    public static function setDocumentManager(DocumentManager $dm)
    {
        self::$documentManager = $dm;
    }
    
    /**
     * @return DocumentManager|boolean
     */
    public function getDocumentManager()
    {
        if (!self::$documentManager instanceof DocumentManager) {
            throw new Exception\WrongUsageException('The DocumentManager was never initialized!');
        }
        return self::$documentManager;
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
    
    public function __construct($collectionName, array $properties = [])
    {
        $this->collectionName = $collectionName;
        $this->properties = $properties;
    }
    
    public function getCollectionName()
    {
        $classname = get_class($this);
        $classname = substr($classname, strrpos($classname, '\\') + 1);
        if ($classname == 'Document') {
            return $this->collectionName;
        } else {
            return $classname;
        }
    }
    
    protected function lazyGetNeighbor($edgeCollection, $targetCollection, $filter = [])
    {
        $propertyAndFilterKey = $targetCollection . serialize($filter);
        if (!array_key_exists($propertyAndFilterKey, $this->properties)) {
            $target = $this->getDocumentManager()->getNeighbor($this, $edgeCollection, $filter);
            if ($target) {
                $this->properties[$propertyAndFilterKey] = $target;
            } else {
                return false;
            }
        }
        return $this->properties[$propertyAndFilterKey];
    }
    
    protected function lazyAddNeighbor($document, $edgeCollection, $target)
    {
        $this->getDocumentManager()->addNeighbor($document, $edgeCollection, $target);
    }
    
    protected function lazyRemoveNeighbor($document, $edgeCollection, $target)
    {
        $this->getDocumentManager()->removeNeighbor($document, $edgeCollection, $target);
    }
}
