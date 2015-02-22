<?php

namespace ArangoODM;

use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\PropertyGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Generator\ValueGenerator;

class DocumentGenerator
{
	protected $collection;
	protected $namespace;
	protected $uses = [];
	protected $properties = [];
	protected $edgeProperties = [];
	
	function __construct($collection, $namespace) {
		if ($collection != ucfirst($collection)) {
			throw new \Exception('Collections must start with upper case but ' . $collection . ' does not. Please fix this!');
		}
		$this->collection = $collection;
		$this->namespace = $namespace;
	}
	
	function addProperty($name) {
		$this->properties[$name] = $name;
	}
	
	function addEdgeProperty($edgeCollection, $targetCollection) {
		$this->edgeProperties[$edgeCollection] = $targetCollection;
	}
	
	function getClass() {
		$classGenerator = new ClassGenerator($this->collection, $this->namespace);
		$classGenerator->addUse('ArangoODM\Document', 'ArangoDoc');
		$classGenerator->setExtendedClass('ArangoDoc');
		$classGenerator->addProperty('collectionName', $this->collection, PropertyGenerator::FLAG_PRIVATE);
		$classGenerator->addMethods($this->getMethods());
		return $classGenerator->generate();
	}
	
	protected function getMethods() {
		$methods = [$this->getConstructor()];
		
		foreach ($this->properties as $propertyName) {
			$setterParam = new ParameterGenerator($propertyName);
			$methodGenerator = new MethodGenerator('set' . ucfirst($propertyName), [$setterParam]);
			$methodGenerator->setBody('$this->' . $propertyName . ' = ' . $setterParam->generate() . ';');
			$methods[] = $methodGenerator;
			
			$methodGenerator = new MethodGenerator('get' . ucfirst($propertyName));
			$methodGenerator->setBody('return $this->' . $propertyName . ';');
			$methods[] = $methodGenerator;
		}
		
		foreach ($this->edgeProperties as $edgeCollection => $targetCollection) {
			$setterParam = new ParameterGenerator(strtolower($targetCollection));
			$methodGenerator = new MethodGenerator('add' . $targetCollection, [$setterParam]);
			$methodGenerator->setBody('$this->lazyAddNeighbor($this, \'' . $edgeCollection . '\', ' . $setterParam->generate() . ');');
			$methods[] = $methodGenerator;
			
			$setterParam = new ParameterGenerator(strtolower($targetCollection));
			$methodGenerator = new MethodGenerator('remove' . $targetCollection, [$setterParam]);
			$methodGenerator->setBody('$this->lazyRemoveNeighbor($this, \'' . $edgeCollection . '\', ' . $setterParam->generate() . ');');
			$methods[] = $methodGenerator;
			
			$setterParam = new ParameterGenerator(strtolower($targetCollection));
			$methodGenerator = new MethodGenerator('set' . $targetCollection, [$setterParam]);
			$methods[] = $methodGenerator;
			
			$defaultValue = new ValueGenerator([], ValueGenerator::TYPE_ARRAY);
			$setterParam = new ParameterGenerator('filter', null, $defaultValue);
			$methodGenerator = new MethodGenerator('get' . $targetCollection, [$setterParam]);
			$methodGenerator->setBody('return $this->lazyGetNeighbor(\'' . $edgeCollection . '\', \'' . $targetCollection . '\', $filter);');
			$methods[] = $methodGenerator;
		}
		
		return $methods;
	}
	
	protected function getConstructor() {
		$defaultValue = new ValueGenerator([], ValueGenerator::TYPE_ARRAY);
		$setterParam = new ParameterGenerator('properties', 'array', $defaultValue);
		$methodGenerator = new MethodGenerator('__construct', [$setterParam]);
		$methodGenerator->setBody('$this->properties = $properties;');
		return $methodGenerator;
	}
}
