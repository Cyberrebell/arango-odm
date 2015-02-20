<?php

namespace ArangoODM\Adapter;

use ArangoODM\Config;
use ArangoODM\Document;

class CurlAdapter implements AdapterInterface
{
	const METHOD_GET = 'GET';
	const METHOD_POST = 'POST';
	const METHOD_PUT = 'PUT';
	const METHOD_DELETE = 'DELETE';
	
	protected $ip = '127.0.0.1';
	protected $port = '8529';
	protected $protocol = 'http';
	protected $username = 'root';
	protected $password = '';
	protected $database = '_system';
	
	function __construct(Config $config) {
		if ($config->get('ip')) {
			$this->ip = $config->get('ip');
		}
		if ($config->get('port')) {
			$this->port = $config->get('port');
		}
		if ($config->get('protocol')) {
			$this->protocol = $config->get('protocol');
		}
		if ($config->get('username')) {
			$this->username = $config->get('username');
		}
		if ($config->get('password')) {
			$this->password = $config->get('password');
		}
		if ($config->get('database')) {
			$this->database = $config->get('database');
		}
	}
	
	function add(Document $document) {
		return $this->request($this->getBaseUrl() . 'document?collection=' . $document->getCollectionName(), self::METHOD_POST, $document->getRawProperties());
	}
	
	function update(Document $document) {
		return $this->request($this->getBaseUrl() . 'document/' . $document->getId(), self::METHOD_PUT, $document->getRawProperties());
	}
	
	function delete(Document $document) {
		return $this->request($this->getBaseUrl() . 'document/' . $document->getId(), self::METHOD_DELETE);
	}
	
	function query($query) {
		$result = $this->request($this->getBaseUrl() . 'cursor', self::METHOD_POST, ['query' => $query]);
		return $result['result'];
	}
	
	function findById($id) {
		return $this->request($this->getBaseUrl() . 'document/' . $id, self::METHOD_GET);
	}
	
	function findBy(Document $document) {
		$result = $this->request($this->getBaseUrl() . 'simple/by-example', self::METHOD_PUT, ['collection' => $document->getCollectionName(), 'example' => $document->getRawProperties()]);
		return $result;
	}
	
	function findAll($collection) {
		return $this->query('FOR d IN ' . $collection . ' RETURN d');
	}
	
	function count($collection) {
		$result = $this->request($this->getBaseUrl() . 'document?collection=' . $collection . '&type=id', self::METHOD_GET);
		return count($result['documents']);
	}
	
	protected function request($url, $method, array $params = null) {
		$handle = curl_init($url);
		$options = [
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_CUSTOMREQUEST => $method
		];
		
		if ($method == self::METHOD_POST || $method == self::METHOD_PUT) {
			$jsonParams = json_encode($params, JSON_FORCE_OBJECT);
			$options[CURLOPT_POSTFIELDS] = $jsonParams;
			$options[CURLOPT_HTTPHEADER] = [
				'Content-Type: application/json',
				'Content-Length: ' . strlen($jsonParams)
			];
		}
		
		curl_setopt_array($handle, $options);
		$result = curl_exec($handle);
		curl_close($handle);
		
		return json_decode($result, true);
	}
	
	protected function getBaseUrl() {
		return $this->protocol . '://' . $this->ip . ':' . $this->port . '/_db/' . $this->database . '/_api/';
	}
}
