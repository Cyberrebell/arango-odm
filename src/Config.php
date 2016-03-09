<?php

namespace ArangoOdm;

class Config
{
    protected $config = [];
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    public function get($key)
    {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        } else {
            return null;
        }
    }
}
