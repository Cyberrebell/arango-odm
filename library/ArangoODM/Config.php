<?php

namespace ArangoODM;

class Config
{
    protected $settings = [];
    
    public function __construct(array $config)
    {
        $this->settings = $config;
    }
    
    public function get($key)
    {
        if (array_key_exists($key, $this->settings)) {
            return $this->settings[$key];
        } else {
            return null;
        }
    }
}
