<?php

namespace ArangoODM;

abstract class ObjectHandler
{
	protected $config;
	protected $adapter;
	
	protected $objectNamespaces = [];
	protected $objectNamespaceMap = [];
	
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
	
	function generateDocuments($targetDirectory, $namespace, $database = null) {
		$collections = $this->adapter->getCollections();
		$documentCollections = [];
		$edgeCollections = [];
		foreach ($collections as $collectionName => $collectionType) {
			if ($collectionType == AdapterInterface::COLLECTION_TYPE_DOCUMENT) {
				$documentCollections[$collectionName] = new DocumentGenerator($collectionName, $namespace);
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
}
