<?php

namespace ArangoODM;

use ArangoODM\Adapter\CurlAdapter;
use ArangoODM\Adapter\AdapterInterface;

class DocumentHandler
{
	const CONNECTOR_SOCKET = 'sock';
	const CONNECTOR_CURL = 'curl';
	
	protected $config;
	protected $adapter;
	protected $documentNamespaces = [];
	
	function __construct(array $config = []) {
		$this->config = new Config($config);
		
		switch ($this->config->get('connector')) {
			case $this::CONNECTOR_SOCKET:
				$this->adapter = false;
				break;
			default:
				$this->adapter = new CurlAdapter($this->config);
		}
		
		if (is_array($this->config->get('document_namespaces'))) {
			$this->documentNamespaces = $this->config->get('document_namespaces');
		}
		
		Document::setDocumentHandler($this);
	}
	
	function add(Document $document) {
		return $this->adapter->add($document);
	}
	
	function update(Document $document) {
		return $this->adapter->update($document);
	}
	
	function delete(Document $document) {
		return $this->adapter->delete($document);
	}
	
	function query($query) {
		$documents = $this->adapter->query($query);
		if ($documents) {
			return $this->mapDocuments($documents);
		} else {
			return false;
		}
	}
	
	function findById($id) {
		$document = $this->adapter->findById($id);
		$doc = $this->mapDocument($document);
		if ($doc) {
			return $doc;
		} else {
			return false;
		}
	}
	
	function findBy(Document $document, $limit = false) {
		$documents = $this->adapter->findBy($document);
		return $this->mapDocuments($documents);
	}
	
	function findAll($collection, $limit = false) {
		$documents = $this->adapter->findAll($collection);
		$docs = $this->mapDocuments($documents);
		if ($docs) {
			return $docs;
		} else {
			return false;
		}
	}
	
	function count($collection) {
		$result = $this->query('RETURN LENGTH(' . $collection . ')');
		if (is_array($result)) {
			$result = reset($result);
		}
		return (int) $result;
	}
	
	function getNeighbor(Document $document, $edgeCollection, $filter = [], $limit = false) {
		$documents = $this->adapter->getNeighbor($document, $edgeCollection, $filter);
		$docs = $this->mapDocuments($documents);
		if ($docs) {
			return $this->mapDocuments($documents);
		} else {
			return false;
		}
	}
	
	function addNeighbor($document, $edgeCollection, $target) {
		$source = $this->ensureArray($document);
		$destination = $this->ensureArray($target);
				
		$this->ensureEdge($source, $edgeCollection, $destination);
	}
	
	function removeNeighbor($document, $edgeCollection, $target, $deleteNeighbor = false) {
		$source = $this->ensureArray($document);
		$destination = $this->ensureArray($target);
		
		$this->ensureNoEdge($source, $edgeCollection, $destination);
	}
	
	function setNeighbor($document, $edgeCollection, $target) {
		//todo
	}
	
	function generateDocuments($targetDirectory, $namespace) {
		$collections = $this->adapter->getCollections();
		$documentCollections = [];
		$edgeCollections = [];
		foreach ($collections as $collectionName => $collectionType) {
			if ($collectionType == AdapterInterface::COLLECTION_TYPE_DOCUMENT) {
				$documentCollections[$collectionName] = new DocumentGenerator($collectionName, $namespace);
				foreach ($this->findAll($collectionName) as $document) {
					foreach ($document->getRawProperties() as $property => $value) {
						$documentCollections[$collectionName]->addProperty($property);
					}
				}
			} else if ($collectionType == AdapterInterface::COLLECTION_TYPE_EDGE) {
				$edgeCollections[] = $collectionName;
			}
		}
		foreach ($edgeCollections as $collectionName) {
			$splitted = explode('_', $collectionName);
			$collectionA = reset($splitted);
			$collectionB = end($splitted);
			if (array_key_exists($collectionA, $documentCollections) && array_key_exists($collectionB, $documentCollections)) {
				$documentCollections[$collectionA]->addEdgeProperty($collectionName, $collectionB);
				$documentCollections[$collectionB]->addEdgeProperty($collectionName, $collectionA);
			}
		}
		$targetDirectoryPath = $targetDirectory;
		if (substr($targetDirectoryPath, -1, 1) != DIRECTORY_SEPARATOR) {
			$targetDirectoryPath .= DIRECTORY_SEPARATOR;
		}
		foreach ($documentCollections as $collectionName => $documentGenerator) {
			file_put_contents($targetDirectoryPath . $collectionName . '.php', $documentGenerator->getClass());
		}
	}
	
	protected function mapDocuments(array $documents) {
		$docs = [];
		foreach ($documents as $document) {
			$doc = $this->mapDocument($document);
			if ($doc) {
				$docs[$document['_id']] = $doc;
			} else {
				return false;	//break mapping if one document is invalid
			}
		}
		return $docs;
	}
	
	protected function mapDocument($document) {
		if (!is_array($document)) {
			return $document;
		} else if (array_key_exists('_id', $document)) {
			$docId = $document['_id'];
			$collectionName = substr($docId, 0, strpos($docId, '/'));	//get collection-name of result
			$documentClass = $this->getDocumentNamespace($collectionName);
			if ($documentClass) {
				return new $documentClass($document);
			} else {
				return new Document($collectionName, $document);
			}
		} else {
			return false;
		}
	}
	
	protected function getDocumentNamespace($collection) {
		foreach ($this->documentNamespaces as $namespace) {
			$documentNamespace = $namespace . '\\' . $collection;
			if (class_exists($documentNamespace)) {
				return $documentNamespace;
			}
		}
		return false;
	}
	
	protected function ensureArray($document) {
		if (is_array($document)) {
			return $document;
		} else if ($document instanceof Document) {
			return [$document];
		} else {
			return false;
		}
	}
	
	protected function ensurePresence($document) {
		$documentsToAdd = [];
		foreach ($document as $singleDoc) {
			if (!$singleDoc->getId()) {
				$documentsToAdd[] = $singleDoc;
			}
		}
		
		foreach ($documentsToAdd as $doc) {	//todo bulk
			$this->add($doc);
		}
	}
	
	protected function ensureEdge($source, $edgeCollection, $target) {
		$this->ensurePresence($source);
		$this->ensurePresence($target);
		
		$sourceArray = $this->getJsonIdArray($source);
		$targetArray = $this->getJsonIdArray($target);
		
		$this->query('FOR s IN ' . $sourceArray . ' FOR d IN ' . $targetArray . ' LET matches = (FOR x IN ' . $edgeCollection . ' FILTER x._from == s && x._to == d RETURN s._key) FILTER s._key NOT IN matches INSERT { _from: s, _to: d } IN ' . $edgeCollection);
	}
	
	protected function ensureNoEdge($source, $edgeCollection, $target) {
		$sourceArray = $this->getJsonIdArray($source);
		$targetArray = $this->getJsonIdArray($target);
		
		$this->query('FOR s IN ' . $sourceArray . ' FOR d IN ' . $targetArray . ' LET matches = (FOR x IN ' . $edgeCollection . ' FILTER x._from == s && x._to == d RETURN x._key) REMOVE { _key: matches[0] } IN ' . $edgeCollection);
	}
	
	protected function getJsonIdArray(array $documents) {
		$documentArray = '[';
		foreach ($documents as $doc) {
			$documentArray .= '"' . $doc->getId() . '",';
		}
		return substr($documentArray, 0, -1) . ']';
	}
}
