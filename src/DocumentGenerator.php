<?php

namespace ArangoOdm;

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
    
    protected static $skipProperties = ['_id', '_rev', '_key'];
    
    public function __construct($collection, $namespace)
    {
        if ($collection != ucfirst($collection)) {
            throw new \Exception('Collections must start with upper case but ' . $collection . ' does not. Please fix this!');
        }
        $this->collection = $collection;
        $this->namespace = $namespace;
    }
    
    public function addProperty($name)
    {
        if (!in_array($name, self::$skipProperties)) {
            $this->properties[$name] = $name;
        }
    }
    
    public function addEdgeProperty($edgeCollection, $targetCollection)
    {
        $this->edgeProperties[$edgeCollection] = $targetCollection;
    }
    
    public function getClass()
    {
        $classGenerator = new ClassGenerator($this->collection, $this->namespace);
        $classGenerator->addUse('ArangoOdm\Document', 'ArangoDoc');
        $classGenerator->setExtendedClass('ArangoDoc');
        $classGenerator->addMethods($this->getMethods());
        return '<?php' . "\n\n" . $classGenerator->generate();
    }
    
    protected function getMethods()
    {
        $methods = [$this->getConstructor()];
        
        foreach ($this->properties as $propertyName) {
            $setterParam = new ParameterGenerator($propertyName);
            $methodGenerator = new MethodGenerator('set' . ucfirst($propertyName), [$setterParam]);
            $methodGenerator->setBody('$this->' . $propertyName . ' = ' . $setterParam->generate() . ';' . PHP_EOL . PHP_EOL . 'return $this;');
            $methods[] = $methodGenerator;
            
            $methodGenerator = new MethodGenerator('get' . ucfirst($propertyName));
            $methodGenerator->setBody('return $this->' . $propertyName . ';');
            $methods[] = $methodGenerator;
        }
        
        foreach ($this->edgeProperties as $edgeCollection => $targetCollection) {
            $setterParam = new ParameterGenerator(lcfirst($edgeCollection));
            $methodGenerator = new MethodGenerator('add' . $edgeCollection, [$setterParam]);
            $methodGenerator->setBody('$this->lazyAddNeighbor($this, \'' . $edgeCollection . '\', ' . $setterParam->generate() . ');');
            $methods[] = $methodGenerator;
            
            $setterParam = new ParameterGenerator(lcfirst($edgeCollection));
            $methodGenerator = new MethodGenerator('remove' . $edgeCollection, [$setterParam]);
            $methodGenerator->setBody('$this->lazyRemoveNeighbor($this, \'' . $edgeCollection . '\', ' . $setterParam->generate() . ');');
            $methods[] = $methodGenerator;
            
            $setterParam = new ParameterGenerator(lcfirst($edgeCollection));
            $methodGenerator = new MethodGenerator('set' . $edgeCollection, [$setterParam]);
            $methodGenerator->setBody('$this->lazySetNeighbor($this, \'' . $edgeCollection . '\', ' . $setterParam->generate() . ');');
            $methods[] = $methodGenerator;
            
            $defaultValue = new ValueGenerator([], ValueGenerator::TYPE_ARRAY);
            $setterParam = new ParameterGenerator('filter', null, $defaultValue);
            $methodGenerator = new MethodGenerator('get' . $edgeCollection, [$setterParam]);
            $methodGenerator->setBody('return $this->lazyGetNeighbor(\'' . $edgeCollection . '\', \'' . $targetCollection . '\', $filter);');
            $methods[] = $methodGenerator;
        }
        
        return $methods;
    }
    
    protected function getConstructor()
    {
        $defaultValue = new ValueGenerator([], ValueGenerator::TYPE_ARRAY);
        $setterParam = new ParameterGenerator('properties', 'array', $defaultValue);
        $methodGenerator = new MethodGenerator('__construct', [$setterParam]);
        $methodGenerator->setBody('$this->properties = $properties;');
        return $methodGenerator;
    }
}
