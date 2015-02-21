<?php

namespace ArangoODM\Adapter;

use ArangoODM\Config;
use ArangoODM\Document;

interface AdapterInterface
{
	function __construct(Config $config);
	function add(Document $document);
	function update(Document $document);
	function delete(Document $document);
	function query($query);
	function findById($id);
	function findBy(Document $document);
	function findAll($collection);
	function count($collection);
	function getNeighbor(Document $document, $edgeCollection, $filter);
}
