<?php

namespace ArangoODM\Adapter;

use ArangoODM\Config;

interface AdapterInterface
{
	function __construct(Config $config);
	function query($query);
	function findById($id);
	function findBy($collection, array $properties);
	function findAll($collection);
	function count($collection);
}
