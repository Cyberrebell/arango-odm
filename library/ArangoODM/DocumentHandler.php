<?php

namespace ArangoODM;

use ArangoODM\Adapter\CurlAdapter;
use ArangoODM\Adapter\AdapterInterface;

class DocumentHandler extends ObjectHandler
{
    const CONNECTOR_SOCKET = 'sock';
    const CONNECTOR_CURL = 'curl';
    
    public function setupAdapter(Config $config)
    {
        switch ($config->get('connector')) {
            case $this::CONNECTOR_SOCKET:
                $this->adapter = false;
                break;
            default:
                $this->adapter = new CurlAdapter($this->config);
        }
        
        Document::setObjectHandler($this);
    }
    
    public function add($document)
    {
        $this->adapter->add($document);
    }
    
    public function update($document)
    {
        if (is_array($document)) {
            foreach ($document as $singleDocument) {
                $this->adapter->update($singleDocument);
            }
        } else {
            return $this->adapter->update($document);
        }
    }
    
    public function delete($document)
    {
        if (is_array($document)) {
            foreach ($document as $singleDocument) {
                $this->adapter->delete($singleDocument);
            }
        } else {
            return $this->adapter->delete($document);
        }
    }
    
    public function query($query)
    {
        $documents = $this->adapter->query($query);
        if (is_array($documents)) {
            return $this->mapDocuments($documents);
        } else {
            return false;
        }
    }
    
    public function findById($id)
    {
        $document = $this->adapter->findById($id);
        $collectionName = $this->getCollectionName($document);
        $objectNamespace = $this->getObjectNamespace($collectionName);
        $doc = $this->mapDocument($document, $collectionName, $objectNamespace);
        if ($doc) {
            return $doc;
        } else {
            return false;
        }
    }
    
    public function findBy(Object $object, $limit = false)
    {
        $documents = $this->adapter->findBy($object);
        if (is_array($documents)) {
            return $this->mapDocuments($documents);
        } else {
            return false;
        }
    }
    
    public function findAll($collection, $limit = false)
    {
        $documents = $this->adapter->findAll($collection);
        if (is_array($documents)) {
            return $this->mapDocuments($documents);
        } else {
            return false;
        }
    }
    
    public function count($collection)
    {
        $result = $this->query('RETURN LENGTH(' . $collection . ')');
        if (is_array($result)) {
            $result = reset($result);
        }
        return (int) $result;
    }
    
    public function getNeighbor(Document $document, $edgeCollection, $filter = [], $limit = false)
    {
        $documents = $this->adapter->getNeighbor($document, $edgeCollection, $filter);
        if (is_array($documents)) {
            return $this->mapDocuments($documents);
        } else {
            return false;
        }
    }
    
    public function addNeighbor($document, $edgeCollection, $target)
    {
        $source = $this->ensureArray($document);
        $destination = $this->ensureArray($target);
                
        $this->ensureEdge($source, $edgeCollection, $destination);
    }
    
    public function removeNeighbor($document, $edgeCollection, $target, $deleteNeighbor = false)
    {
        $source = $this->ensureArray($document);
        $destination = $this->ensureArray($target);
        
        $this->ensureNoEdge($source, $edgeCollection, $destination);
    }
    
    public function setNeighbor($document, $edgeCollection, $target)
    {
        //todo
    }
    
    protected function mapDocuments(array $documents)
    {
        if (empty($documents)) {
            return $documents;
        }
        $docs = [];
        $firstDocId = reset($documents);
        $firstDocId = $firstDocId['_id'];
        $collectionName = substr($firstDocId, 0, strpos($firstDocId, '/'));
        $collectionNamespace = $this->getObjectNamespace($collectionName);
        foreach ($documents as $document) {
            $doc = $this->mapDocument($document, $collectionName, $collectionNamespace);
            if ($doc) {
                $docs[$document['_id']] = $doc;
            } else {
                return false;   //break mapping if one document is invalid
            }
        }
        return $docs;
    }
    
    protected function mapDocument($document, $collection = false, $documentNamespace = false)
    {
        if (is_array($document)) {
            if ($documentNamespace) {
                return new $documentNamespace($document);
            } else {
                return new Document($collection, $document);
            }
        } else {
            return $document;
        }
    }
    
    protected function ensureArray($document)
    {
        if (is_array($document)) {
            return $document;
        } elseif ($document instanceof Document) {
            return [$document];
        } else {
            return false;
        }
    }
    
    protected function ensurePresence($document)
    {
        $documentsToAdd = [];
        foreach ($document as $singleDoc) {
            if (!$singleDoc->getId()) {
                $documentsToAdd[] = $singleDoc;
            }
        }
        
        foreach ($documentsToAdd as $doc) {     //todo bulk
            $this->add($doc);
        }
    }
    
    protected function ensureEdge($source, $edgeCollection, $target)
    {
        $this->ensurePresence($source);
        $this->ensurePresence($target);
        
        $sourceArray = $this->getJsonIdArray($source);
        $targetArray = $this->getJsonIdArray($target);
        
        $this->query('FOR s IN ' . $sourceArray . ' FOR d IN ' . $targetArray . ' LET matches = (FOR x IN ' . $edgeCollection . ' FILTER x._from == s && x._to == d RETURN s._key) FILTER s._key NOT IN matches INSERT { _from: s, _to: d } IN ' . $edgeCollection);
    }
    
    protected function ensureNoEdge($source, $edgeCollection, $target)
    {
        $sourceArray = $this->getJsonIdArray($source);
        $targetArray = $this->getJsonIdArray($target);
        
        $this->query('FOR s IN ' . $sourceArray . ' FOR d IN ' . $targetArray . ' LET matches = (FOR x IN ' . $edgeCollection . ' FILTER x._from == s && x._to == d RETURN x._key) REMOVE { _key: matches[0] } IN ' . $edgeCollection);
    }
    
    protected function getJsonIdArray(array $documents)
    {
        $documentArray = '[';
        foreach ($documents as $doc) {
            $documentArray .= '"' . $doc->getId() . '",';
        }
        return substr($documentArray, 0, -1) . ']';
    }
}
