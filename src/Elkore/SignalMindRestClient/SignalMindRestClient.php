<?php

namespace Elkore\SignalMindRestClient;

use Elkore\SignalMindRestClient\HandleLoyalty;
use Elkore\SignalMindRestClient\SignalMindApiV2;

class SignalMindRestClient {

	private static $instance;
	private $restclients = array();
	private $loyaltyclients = array();

	public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }

	protected function __construct()
    {
    }

	private function __clone()
    {
    }

    public function getRestClient($apikey = 'invalidkey'){
		if (!array_key_exists($apikey, $this->restclients)) {
			$this->restclients[$apikey] =  new SignalMindApiV2($apikey);
		}

		return $this->restclients[$apikey];
    }

    public function getLoyaltyClient($apikey = 'invalidkey', $logfile = null){
		$key = $apikey . '-' . md5($logfile);
		if (!array_key_exists($key, $this->restclients)) {
			$this->loyaltyclients[$key] =  new HandleLoyalty($apikey, $logfile);
		}

		return $this->loyaltyclients[$key];
    }
}
