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
		$this->adapter->add($document);
	}
	
	function update(Document $document) {
		$this->adapter->update($document);
	}
	
	function delete(Document $document) {
		$this->adapter->delete($document);
	}
	
	function query($query) {
		$documents = $this->adapter->query($query);
		return $this->mapDocuments($documents);
	}
	
	function findById($id) {
		$document = $this->adapter->findById($id);
		if (empty($document)) {
			return false;
		} else {
			return $this->mapDocument($document);
		}
	}
	
	function findBy(Document $document) {
		return $this->adapter->findBy($document);
	}
	
	function findAll($collection) {
		$documents = $this->adapter->findAll($collection);
		return $this->mapDocuments($documents);
	}
	
	function count($collection) {
		return $this->adapter->count($collection);
	}
	
	protected function mapDocuments(array $documents) {
		$docs = [];
		foreach ($documents as $document) {
			$docs[$document['_id']] = $this->mapDocument($document);
		}
		return $docs;
	}
	
	protected function mapDocument(array $document) {
		$docId = $document['_id'];
		$collectionName = substr($docId, 0, strpos($docId, '/'));	//get collection-name of result
		return new Document($collectionName, $document);
	}
}
