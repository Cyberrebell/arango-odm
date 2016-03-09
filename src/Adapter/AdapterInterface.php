<?php

namespace ArangoOdm\Adapter;

use ArangoOdm\Config;
use ArangoOdm\Document;

interface AdapterInterface
{
    const COLLECTION_TYPE_DOCUMENT = 2;
    const COLLECTION_TYPE_EDGE = 3;
    
    public function __construct(Config $config);
    public function selectDatabase($databaseName, $host = null);
    public function add(Document $document);
    public function update(Document $document);
    public function delete(Document $document);
    public function query($query);
    public function findById($id);
    public function findBy(Document $document, $limit);
    public function findAll($collection, $limit);
    public function getNeighbor(Document $document, $edgeCollection, $filter, $limit);
    public function getCollections();
}
