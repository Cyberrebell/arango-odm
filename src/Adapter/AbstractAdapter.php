<?php

namespace ArangoOdm\Adapter;

use ArangoOdm\Document;

abstract class AbstractAdapter
{
    const COLLECTION_TYPE_DOCUMENT = 2;
    const COLLECTION_TYPE_EDGE = 3;
    const DEFAULT_PORT = 8529;
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';
    const RESULT_LIMIT = 10000000;
    
    protected $hosts = ['127.0.0.1:8529' => ['_system' => ['username' => 'root', 'password' => '', 'documents' => []]]];    //default arango settings
    protected $protocol = 'http';
    protected $selectedHost;
    protected $selectedDatabase;
    protected $login;

    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->hosts = $config;
        }
        $this->selectDatabase();
    }

    public function selectDatabase($databaseName = null, $ip = null, $port = null)
    {
        if ($ip) {
            $host = $ip;
            if (strstr($host, ':')) {
                //host contains port
            } elseif ($port) {
                $host .= ':' . $port;
            } else {
                $host .= ':' . self::DEFAULT_PORT;
            }
            if (!array_key_exists($host, $this->hosts)) {
                throw new \Exception('The selected database ' . $databaseName . '@' . $ip . ':' . $port . ' is not provided in config!');
            }
        } elseif ($this->selectedHost) {
            $host = $this->selectedHost;
        } elseif (count($this->hosts) == 1) {
            $hosts = array_keys($this->hosts);
            $host = reset($hosts);
        } else {
            throw new \ArangoOdm\Exception\WrongUsageException('need a ip to selectDatabase()');
        }
        
        $databases = $this->hosts[$host];
        if ($databaseName) {
            if (array_key_exists($databaseName, $databases)) {
                $this->selectedHost = $host;
                $this->selectedDatabase = $databaseName;
                $this->login = null;
                return true;
            }
        } else if (count($databases) == 1) {
            $this->selectedHost = $host;
            $databaseCfgName = array_keys($databases);
            $this->selectedDatabase = reset($databaseCfgName);
            $this->login = null;
            return true;
        }
        return false;
    }
    
    public function getConfigCollections()
    {
        $dbConfig = $this->hosts[$this->selectedHost][$this->selectedDatabase];
        if (array_key_exists('documents', $dbConfig)) {
            return $dbConfig['documents'];
        } else {
            return false;
        }
    }
    
    /**
     * @param Document|array $document Could be an array for bulk insert
     */
    public abstract function add($document);
    public abstract function update(Document $document);
    public abstract function delete(Document $document);
    public abstract function query($query);
    public abstract function findById($id);
    public abstract function findBy(Document $document, $limit);
    public abstract function findAll($collection, $limit);
    public abstract function getNeighbor(Document $document, $edgeCollection, $filter, $limit);
    public abstract function getCollections();
    
    protected abstract function getLogin();
}
