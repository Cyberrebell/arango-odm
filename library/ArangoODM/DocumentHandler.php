<?php

namespace ArangoODM;

use ArangoODM\Adapter\CurlAdapter;

class DocumentHandler
{
	const CONNECTOR_SOCKET = 'sock';
	const CONNECTOR_CURL = 'curl';
	
	protected $config;
	protected $adapter;
	
	function __construct(array $config = []) {
		$this->config = new Config($config);
		
		switch ($this->config->get('connector')) {
			case $this::CONNECTOR_SOCKET:
				$this->adapter = false;
				break;
			default:
				$this->adapter = new CurlAdapter($this->config);
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
		$docs = $this->mapDocuments($documents);
		if ($docs) {
			return $docs;
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
	
	function findBy(Document $document) {
		$documents = $this->adapter->findBy($document);
		return $this->mapDocuments($documents);
	}
	
	function findAll($collection) {
		$documents = $this->adapter->findAll($collection);
		$docs = $this->mapDocuments($documents);
		if ($docs) {
			return $docs;
		} else {
			return false;
		}
	}
	
	function count($collection) {
		return $this->adapter->count($collection);
	}
	
	function getNeighbor(Document $document, $edgeCollection, $filter = []) {
		$documents = $this->adapter->getNeighbor($document, $edgeCollection, $filter);
		$docs = $this->mapDocuments($documents);
		if ($docs) {
			return $docs;
		} else {
			return false;
		}
	}
	
	function addNeighbor($document, $edgeCollection, $target) {
		if (is_array($document)) {
			$source = $document;
		} else if ($document instanceof Document) {
			$source = [$document];
		} else {
			return false;
		}
		
		if (is_array($target)) {
			$destination = $target;
		} else if ($target instanceof Document) {
			$destination = [$target];
		} else {
			return false;
		}
		
		$this->ensureEdge($source, $edgeCollection, $destination);
	}
	
	function removeNeighbor($document, $edgeCollection, $target, $deleteNeighbor = false) {
	
	}
	
	function setNeighbor($document, $edgeCollection, $target) {
	
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
	
	protected function mapDocument(array $document) {
		if (array_key_exists('_id', $document)) {
			$docId = $document['_id'];
			$collectionName = substr($docId, 0, strpos($docId, '/'));	//get collection-name of result
			return new Document($collectionName, $document);
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
		
		$sourceArray = '[';
		foreach ($source as $src) {
			$sourceArray .= '"' . $src->getId() . '",';
		}
		$sourceArray = substr($sourceArray, 0, -1) . ']';
		
		$targetArray = '[';
		foreach ($target as $tar) {
			$targetArray .= '"' . $tar->getId() . '",';
		}
		$targetArray = substr($targetArray, 0, -1) . ']';
		
		$this->query('FOR s IN ' . $sourceArray . ' FOR d IN ' . $targetArray . ' LET matches = (FOR x IN ' . $edgeCollection . ' FILTER x._from == s && x._to == d RETURN s._id) FILTER s._id NOT IN matches INSERT { _from: s, _to: d } IN ' . $edgeCollection);
	}
}
