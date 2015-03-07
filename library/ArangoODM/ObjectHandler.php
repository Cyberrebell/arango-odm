<?php

namespace ArangoODM;

use ArangoODM\Adapter\AdapterInterface;

abstract class ObjectHandler
{
	protected $config;
	/**
	 * @var \ArangoODM\Adapter\AdapterInterface
	 */
	protected $adapter;
	
	protected $objectNamespaces = [];
	protected $objectNamespaceMap = [];
	protected $defaultNamespace;
	
	function __construct(array $config = []) {
		$this->config = new Config($config);
	
		$this->setupAdapter($this->config);
	
		if (is_array($this->config->get('object_namespaces'))) {
			$this->objectNamespaces = $this->config->get('object_namespaces');
		}
	}
	
	abstract function setupAdapter(Config $config);
	abstract function add($object);
	abstract function update($object);
	abstract function delete($object);
	abstract function query($query);
	abstract function findById($id);
	abstract function findBy(Object $object, $limit = false);
	abstract function findAll($collection, $limit = false);
	abstract function count($collection);
	
	function setDefaultNamespace($namespace) {
		$this->defaultNamespace = $namespace;
	}
	
	function getDefaultNamespace() {
		if (!$this->defaultNamespace) {
			$this->defaultNamespace = reset($this->config->get('object_namespaces'));
		}
		return $this->defaultNamespace;
	}
	
	function generateAllObjects($targetDirectory) {
		$hosts = $this->config->get('hosts');
		foreach ($hosts as $hostname => $host) {
			foreach ($host as $databaseName => $settings) {
				$this->adapter->selectDatabase($databaseName, $hostname);
				$this->generateObjects($targetDirectory);
			}
		}
	}
	
	function generateObjects($targetDirectory) {
		$collections = $this->adapter->getCollections();
		$documentCollections = [];
		$edgeCollections = [];
		foreach ($collections as $collectionName => $collectionType) {
			if ($collectionType == AdapterInterface::COLLECTION_TYPE_DOCUMENT) {
				$documentCollections[$collectionName] = new DocumentGenerator($collectionName, $this->getDefaultNamespace());
				$documents = $this->findAll($collectionName);
				if ($documents) {
					foreach ($documents as $document) {
						foreach ($document->getRawProperties() as $property => $value) {
							$documentCollections[$collectionName]->addProperty($property);
						}
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
	
	protected function getObjectNamespace($collection) {
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
}
