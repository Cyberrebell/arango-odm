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
		
	}
	
	function update(Document $document) {
	
	}
	
	function delete(Document $document) {
	
	}
	
	function findById($id) {
		return $this->adapter->findById($id);
	}
	
	function findBy(array $properties) {
		return $this->adapter->findBy($properties);
	}
}
