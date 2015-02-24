<?php

namespace ArangoODM\Adapter;

use ArangoODM\Config;
use ArangoODM\Document;

interface AdapterInterface
{
	const COLLECTION_TYPE_DOCUMENT = 2;
	const COLLECTION_TYPE_EDGE = 3;
	
	function __construct(Config $config);
	function add(Document $document);
	function update(Document $document);
	function delete(Document $document);
	function query($query);
	function findById($id);
	function findBy(Document $document, $limit);
	function findAll($collection, $limit);
	function getNeighbor(Document $document, $edgeCollection, $filter, $limit);
	function getCollections();
}
