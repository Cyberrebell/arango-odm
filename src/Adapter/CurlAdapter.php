<?php

namespace ArangoOdm\Adapter;

use ArangoOdm\Document;

class CurlAdapter extends AbstractAdapter
{
    protected function getBaseUrl()
    {
        return $this->protocol . '://' . $this->selectedHost . '/_db/' . $this->selectedDatabase . '/_api/';
    }
    
    protected function getLogin()
    {
        if (!$this->login) {
            $login = $this->hosts[$this->selectedHost][$this->selectedDatabase];
            $this->login = $login['username'] . ':' . $login['password'];
        }
        return $this->login;
    }
    
    protected function request($url, $method, $params = null)
    {
        $handle = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERPWD => $this->getLogin()
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
    
    public function add($document, $allowBulk)
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
            if ($allowBulk) {
                $bulkJson = '';
                foreach ($document as $singleDocument) {
                    $bulkJson .= json_encode($singleDocument->getRawProperties(), JSON_FORCE_OBJECT) . "\n";
                }
                $collectionName = reset($document)->getCollectionName();
                return $this->request($this->getBaseUrl() . 'import?type=documents&collection=' . $collectionName, self::METHOD_POST, $bulkJson);
            } else {
                foreach ($document as $doc) {
                    $this->add($doc, false);
                }
            }
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
        $result = $this->request($this->getBaseUrl() . 'cursor', self::METHOD_POST, ['query' => $query, 'options' => ['batchSize' => self::RESULT_LIMIT]]);
        if (!is_array($result)) {
            throw new \ArangoOdm\Exception\ResultException('Request failed!');
        } elseif (array_key_exists('result', $result)) {
            return $result['result'];
        } else {
            throw new \ArangoOdm\Exception\ResultException($result['errorMessage']);
        }
    }
    
    public function findById($id)
    {
        return $this->request($this->getBaseUrl() . 'document/' . $id, self::METHOD_GET);
    }
    
    public function findBy(Document $document, $limit = false)
    {
        $result = $this->request($this->getBaseUrl() . 'simple/by-example', self::METHOD_PUT, ['collection' => $document->getCollectionName(), 'example' => $document->getRawProperties()]);
        if (array_key_exists('result', $result)) {
            return $result['result'];
        } else {
            throw new \ArangoOdm\Exception\ResultException($result['errorMessage']);
        }
    }
    
    public function findAll($collection, $limit = false)
    {
        if ($limit) {
            $resultLimit = $limit;
        } else {
            $resultLimit = self::RESULT_LIMIT;
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
