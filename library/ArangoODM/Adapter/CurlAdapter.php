<?php

namespace ArangoODM\Adapter;

use ArangoODM\Config;

class CurlAdapter implements AdapterInterface
{
	const METHOD_GET = 'GET';
	const METHOD_POST = 'POST';
	
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
	
	function query($query) {
		$result = $this->request($this->getBaseUrl() . 'cursor', self::METHOD_POST, ['query' => $query]);
		return $result['result'];
	}
	
	function findById($id) {
		return $this->request($this->getBaseUrl() . 'document/' . $id, self::METHOD_GET);
	}
	
	function findBy($collection, array $properties) {
		
	}
	
	function findAll($collection) {
		return $this->query('FOR d IN ' . $collection . ' RETURN d');
	}
	
	function count($collection) {
		$result = $this->request($this->getBaseUrl() . 'document/?collection=' . $collection . '&type=id', self::METHOD_GET);
		return count($result['documents']);
	}
	
	protected function request($url, $method, array $params = null) {
		$handle = curl_init($url);
		$options = [
			CURLOPT_RETURNTRANSFER => 1
		];
		
		if ($method == self::METHOD_POST) {                                                                 
			$jsonParams = json_encode($params);
			$options[CURLOPT_CUSTOMREQUEST] = $method;
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
