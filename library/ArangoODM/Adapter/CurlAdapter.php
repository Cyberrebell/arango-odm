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
    
    protected $hosts = ['127.0.0.1' => ['_system' => ['username' => 'root', 'password' => '']]];    //default arango settings
    protected $port = '8529';
    protected $protocol = 'http';
    protected $queryResultLimit = 10000000;
    protected $selectedHost = '127.0.0.1';
    protected $selectedDatabase = '_system';
    
    public function __construct(Config $config)
    {
        if ($config->get('query_result_limit')) {
            $this->queryResultLimit = $config->get('query_result_limit');
        }
        if ($config->get('port')) {
            $this->port = $config->get('port');
        }
        if ($config->get('protocol')) {
            $this->protocol = $config->get('protocol');
        }
        if ($config->get('hosts')) {
            $this->hosts = $config->get('hosts');
        }
    }
    
    public function selectDatabase($databaseName, $host = null)
    {
        if ($host) {
            if (array_key_exists($host, $this->hosts)) {
                $databases = $this->hosts[$host];
                if (array_key_exists($databaseName, $databases)) {
                    $this->selectedHost = $host;
                    $this->selectedDatabase = $databaseName;
                    return true;
                }
            }
        } else {
            foreach ($this->hosts as $cfgHost => $databases) {
                if (array_key_exists($databaseName, $databases)) {
                    $this->selectedHost = $cfgHost;
                    $this->selectedDatabase = $databaseName;
                    return true;
                }
            }
        }
        return false;
    }
    
    public function add($document)
    {
        if ($document instanceof Document) {
            $result = $this->request($this->getBaseUrl() . 'document?collection=' . $document->getCollectionName(), self::METHOD_POST, $document->getRawProperties());
            if (is_array($result)) {
                $document->_id = $result['_id'];
                $document->_key = $result['_key'];
                $document->_rev = $result['_rev'];
            }
            return $result;
        } else {
            $bulkJson = '';
            foreach ($document as $singleDocument) {
                $bulkJson .= json_encode($singleDocument->getRawProperties(), JSON_FORCE_OBJECT) . "\n";
            }
            $collectionName = reset($document)->getCollectionName();
            return $this->request($this->getBaseUrl() . 'import?type=documents&collection=' . $collectionName, self::METHOD_POST, $bulkJson);
        }
    }
    
    public function update(Document $document)
    {
        return $this->request($this->getBaseUrl() . 'document/' . $document->getId(), self::METHOD_PUT, $document->getRawProperties());
    }
    
    public function delete(Document $document)
    {
        return $this->request($this->getBaseUrl() . 'document/' . $document->getId(), self::METHOD_DELETE);
    }
    
    public function query($query)
    {
        $result = $this->request($this->getBaseUrl() . 'cursor', self::METHOD_POST, ['query' => $query,'options' => ['batchSize' => $this->queryResultLimit]]);
        if (!is_array($result)) {
            throw new \Exception('Request failed!');
        } elseif (array_key_exists('result', $result)) {
            return $result['result'];
        } else {
            throw new \Exception($result['errorMessage']);
        }
    }
    
    public function findById($id)
    {
        return $this->request($this->getBaseUrl() . 'document/' . $id, self::METHOD_GET);
    }
    
    public function findBy(Document $document, $limit = false)
    {
        $result = $this->request($this->getBaseUrl() . 'simple/by-example', self::METHOD_PUT, ['collection' => $document->getCollectionName(), 'example' => $document->getRawProperties()]);
        return $result['result'];
    }
    
    public function findAll($collection, $limit = false)
    {
        if ($limit) {
            $resultLimit = $limit;
        } else {
            $resultLimit = $this->queryResultLimit;
        }
        return $this->query('FOR d IN ' . $collection . ' LIMIT ' . $resultLimit . ' RETURN d');
    }
    
    public function getNeighbor(Document $document, $edgeCollection, $filter, $limit = false)
    {
        if ($limit) {
            $resultLimit = $limit;
        } else {
            $resultLimit = $this->queryResultLimit;
        }
        $query = 'FOR d in ' . $document->getCollectionName() . ' FILTER d._id=="' . $document->getId() . '" FOR n IN NEIGHBORS(' . $document->getCollectionName() . ', ' . $edgeCollection . ', d, "any") ';
        if (!empty($filter)) {
            $query .= 'FILTER ' . $this->filterToAqlFilter($filter, 'n.vertex', true) . ' ';
        }
        $query .= 'LIMIT ' . $resultLimit . ' RETURN n.vertex';
        return $this->query($query);
    }
    
    public function getCollections()
    {
        $collections = $this->request($this->getBaseUrl() . 'collection?excludeSystem=true', self::METHOD_GET);
        $reformedCollections = [];
        foreach ($collections['collections'] as $collection) {
            $reformedCollections[$collection['name']] = $collection['type'];
        }
        return $reformedCollections;
    }
    
    protected function request($url, $method, $params = null)
    {
        $handle = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERPWD => $this->getUsernamePasswordStr()
        ];
        
        if ($method == self::METHOD_POST || $method == self::METHOD_PUT) {
            if (is_array($params)) {
                $jsonParams = json_encode($params, JSON_FORCE_OBJECT);
            } else {
                $jsonParams = $params;
            }
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
    
    protected function getBaseUrl()
    {
        return $this->protocol . '://' . $this->selectedHost . ':' . $this->port . '/_db/' . $this->selectedDatabase . '/_api/';
    }
    
    protected function getUsernamePasswordStr()
    {
        $login = $this->hosts[$this->selectedHost][$this->selectedDatabase];
        return $login['username'] . ':' . $login['password'];
    }
    
    protected function filterToAqlFilter(array $filter, $collectionAlias = 'd', $removeStartingAndSymbol = false)
    {
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
