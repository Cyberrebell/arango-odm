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
		if (array_key_exists('result', $result)) {
			return $result['result'];
		} else {
			throw new \Exception($result['errorMessage']);
		}
	}
	
	function findById($id) {
		return $this->request($this->getBaseUrl() . 'document/' . $id, self::METHOD_GET);
	}
	
	function findBy(Document $document) {
		$result = $this->request($this->getBaseUrl() . 'simple/by-example', self::METHOD_PUT, ['collection' => $document->getCollectionName(), 'example' => $document->getRawProperties()]);
		return $result['result'];
	}
	
	function findAll($collection) {
		return $this->query('FOR d IN ' . $collection . ' RETURN d');
	}
	
	function count($collection) {
		$result = $this->request($this->getBaseUrl() . 'document?collection=' . $collection . '&type=id', self::METHOD_GET);
		return count($result['documents']);
	}
	
	function getNeighbor(Document $document, $edgeCollection, $filter) {
		$query = 'FOR d in ' . $document->getCollectionName() . ' FILTER d._id=="' . $document->getId() . '" FOR n IN NEIGHBORS(' . $document->getCollectionName() . ', ' . $edgeCollection . ', d, "any") ';
		if (!empty($filter)) {
			$query .= 'FILTER ' . $this->filterToAqlFilter($filter, 'n.vertex', true) . ' ';
		}
		$query .= 'RETURN n.vertex';
		return $this->query($query);
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
	
	protected function filterToAqlFilter(array $filter, $collectionAlias = 'd', $removeStartingAndSymbol = false) {
		if (empty($filter)) {
			return false;
		} else {
			$filterStr = '';
			foreach ($filter as $property => $value) {
				$filterStr .= ' && ' . $collectionAlias . '.' . $property . ' == "' . $value . '"';
			}
			if ($removeStartingAndSymbol) {
				$filterStr = substr($filterStr, 4);
			}
			return $filterStr;
		}
	}
}
