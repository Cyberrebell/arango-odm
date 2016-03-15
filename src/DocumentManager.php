<?php

namespace ArangoOdm;

use ArangoOdm\Adapter\CurlAdapter;

class DocumentManager
{
    const ADAPTER_SOCKET = 'sock';
    const ADAPTER_CURL = 'curl';
    
    protected $config;
    protected $adapter;
    
    protected $documentNamespaces;
    protected $documentNamespaceMap;
    
    public function __construct(array $config = [])
    {
        $this->config = new Config($config);
        Document::setDocumentManager($this);    //all Documents will use this DocumentManager now
    }
    
    /**
     * @return \ArangoODM\Adapter\AdapterInterface
     */
    protected function getAdapter()
    {
        if (!$this->adapter) {
            switch ($this->config->get('adapter')) {
                case self::ADAPTER_SOCKET:
                    $this->adapter = false;
                    break;
                default:
                    $this->adapter = new CurlAdapter($this->config->get('hosts'));
            }
        }
        return $this->adapter;
    }
    
    public function generateAllDocuments(array $namespaceMap)
    {
        $hosts = $this->config->get('hosts');
        foreach ($hosts as $host => $databases) {
            foreach ($databases as $databaseName => $settings) {
                $this->getAdapter()->selectDatabase($databaseName, $host);
                $namespaces = array_keys($settings['documents']);
                foreach ($namespaces as $namespace) {
                    if (array_key_exists($namespace, $namespaceMap)) {
                        $namespaceDirectory = $namespaceMap[$namespace];
                        if (!is_dir($namespaceDirectory)) {
                            mkdir($namespaceDirectory);
                        }
                        foreach ($settings['documents'][$namespace] as $document) {
                            $this->generateDocument($document, $namespace, $namespaceDirectory);
                        }
                    }
                }
            }
        }
    }
    
    public function generateDocument($document, $namespace, $targetDirectory)
    {
        $documentGenerator = new DocumentGenerator($document, $namespace);
        $result = $this->findBy(new Document($document), 1);
        $firstDocument = reset($result);
        foreach ($firstDocument->getRawProperties() as $property => $value) {
            $documentGenerator->addProperty($property);
        }
        
        $collections = $this->getAdapter()->getCollections();
        foreach ($collections as $collectionName => $collectionType) {
            if ($collectionType == Adapter\AbstractAdapter::COLLECTION_TYPE_EDGE) {
//                $documentGenerator->addEdgeProperty($collectionName, $collectionB);
            }
        }
        
        file_put_contents($targetDirectory . DIRECTORY_SEPARATOR . $document . '.php', $documentGenerator->getClass());
    }
    
    protected function getCollectionName(array $document)
    {
        $objectId = $document['_id'];
        return substr($objectId, 0, strpos($objectId, '/'));
    }
    
    protected function getObjectNamespace($collection)
    {
        if (array_key_exists($collection, $this->objectNamespaceMap)) {
            return $this->objectNamespaceMap[$collection];
        } else {
            foreach ($this->objectNamespaces as $namespace) {
                $objectNamespace = $namespace . '\\' . $collection;
                if (class_exists($objectNamespace)) {
                    $this->objectNamespaceMap[$collection] = $objectNamespace;
                    return $objectNamespace;
                }
            }
            return false;
        }
    }
    
    public function add($document)
    {
        $this->getAdapter()->add($document);
    }
    
    public function update($document)
    {
        if (is_array($document)) {
            foreach ($document as $singleDocument) {
                $this->getAdapter()->update($singleDocument);
            }
        } else {
            return $this->getAdapter()->update($document);
        }
    }
    
    public function delete($document)
    {
        if (is_array($document)) {
            foreach ($document as $singleDocument) {
                $this->getAdapter()->delete($singleDocument);
            }
        } else {
            return $this->getAdapter()->delete($document);
        }
    }
    
    public function query($query, $mapResult = true)
    {
        $documents = $this->getAdapter()->query($query);
        if (!$mapResult) {
            return $documents;
        } else if (is_array($documents)) {
            return $this->mapDocuments($documents);
        } else {
            return false;
        }
    }
    
    public function findById($id)
    {
        $document = $this->getAdapter()->findById($id);
        $collectionName = $this->getCollectionName($document);
        $objectNamespace = $this->getObjectNamespace($collectionName);
        $doc = $this->mapDocument($document, $collectionName, $objectNamespace);
        if ($doc) {
            return $doc;
        } else {
            return false;
        }
    }
    
    public function findBy(Document $document, $limit = false)
    {
        $documents = $this->getAdapter()->findBy($document);
        if (is_array($documents)) {
            return $this->mapDocuments($documents);
        } else {
            return false;
        }
    }
    
    public function findAll($collection, $limit = false)
    {
        $documents = $this->getAdapter()->findAll($collection);
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
        $documents = $this->getAdapter()->getNeighbor($document, $edgeCollection, $filter);
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
