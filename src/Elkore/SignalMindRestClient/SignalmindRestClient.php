<?php

namespace Elkore\SignalmindRestClient;

class SignalmindRestClient {

	private $restclients = array();
	private $loyaltyclients = array();

    public function getRestClient($apikey = 'invalidkey'){
		if (!array_key_exists($apikey, $this->restclients)) {
			$this->restclients[$apikey] =  new \Elkore\SignalmindRestClient\SignalmindApiV2($apikey);
		}

		return $this->restclients[$apikey];
    }

    public function getLoyaltyClient($apikey = 'invalidkey', $logfile = null){
		$key = $apikey . '-' . md5($logfile);
		if (!array_key_exists($key, $this->restclients)) {
			$this->loyaltyclients[$key] =  new \Elkore\SignalmindRestClient\HandleLoyalty($apikey, $logfile);
		}

		return $this->loyaltyclients[$key];
    }
}
