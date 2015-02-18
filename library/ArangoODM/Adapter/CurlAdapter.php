<?php

namespace ArangoODM\Adapter;

use ArangoODM\Config;

class CurlAdapter implements AdapterInterface
{
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
	}
	
	function query($query) {
		
	}
	
	function findById($id) {
		return $this->request($this->getBaseUrl() . 'document/' . $id, 'GET');
	}
	
	function findBy(array $properties) {
		
	}
	
	protected function request($url, $method, array $params = null) {
		$handle = curl_init($url);
		curl_setopt_array($handle, [
			CURLOPT_POST => count($params),
			CURLOPT_POSTFIELDS => http_build_query($params),
			CURLOPT_RETURNTRANSFER => 1
		]);
		$result = curl_exec($handle);
		curl_close($handle);
		return $result;
	}
	
	protected function getBaseUrl() {
		return $this->protocol . '://' . $this->ip . ':' . $this->port . '/_db/' . $this->database . '/_api/';
	}
}
