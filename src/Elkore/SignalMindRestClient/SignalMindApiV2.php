<?php

/**
 * Signalmind API interface.
 *
 * Change date: 2016-09-13
 */
namespace Elkore\SignalMindRestClient;

class SignalMindApiV2 extends AApiClient
{
    public $lastResult;

    protected $apiKey = '';
    protected $apiURL = 'https://files.safemobi.net/rest/v2'; /* 'https://files.safemobi.net/rest/v1'; */
    protected $accessToken = '';
    protected $saveTokenCallback;
    protected $loadTokenCallback;
    protected $tmpTokenFilename;

    /**
     * Class constructor.
     *
     * Required arguments:
     * key - Signalmind API Key
     *
     * Optional parameters:
     *  customLoadTokenCallback - function to get previously saved temporary access token
     *  customSaveTokenCallback - function to save temporary access token
     *
     * @param string $key
     * @param void   $customLoadTokenCallback
     * @param void   $customSaveTokenCallback
     */
    public function __construct($key, &$customLoadTokenCallback = null, &$customSaveTokenCallback = null)
    {
        if (strlen($key) > 0) {
            $this->apiKey = $key;
        }
        //filename to store temporary access token
        $this->tmpTokenFilename = sys_get_temp_dir().'/resapi-'.md5($this->apiKey).'.txt';

        //set access token load/save callbacks

        $this->loadTokenCallback = ((isset($customLoadTokenCallback) && is_callable($customLoadTokenCallback)) ? $customLoadTokenCallback : array(&$this, 'loadTokenFromFile'));
        $this->saveTokenCallback = ((isset($customSaveTokenCallback) && is_callable($customSaveTokenCallback)) ? $customSaveTokenCallback : array(&$this, 'saveTokenToFile'));

        $this->getAccessToken(); //load stored access token
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get "Temporary Access Token".
     *
     * @return string TOKEN
     */
    public function getAccessToken()
    {
        $token = call_user_func($this->loadTokenCallback);
        if (strlen($token) > 0) {
            $this->accessToken = $token;
        }
    }
    /**
     * Save "Temporary Access Token" (after call "Authentication").
     *
     * @param string $newToken
     */
    public function setAccessToken($newToken)
    {
        if (strlen($newToken) > 0) {
            $this->accessToken = $newToken;
            @call_user_func($this->saveTokenCallback, $newToken);
        }
    }

    /**
     * Authentication.
     *
     * Authentication method gets the temporary token for API access. Lifetime is 240 minutes.
     *
     *
     * @return none
     */
    public function Authentication()
    {
        $res = $this->_doApiRequest('/authentication/getapitoken?key='.$this->apiKey);
        if ($res['success']) {
            $this->setAccessToken($res['result']->Data->Token);
        }

        return $res;
    }

    public function addLoyaltyMember($accountID, $member = null)
    {
        return  $this->ApiRequest('/loyaltyprogram/members/'.$accountID, 'PUT', json_encode($member));
    }

    public function updateLoyaltyMember($accountID, $member = null)
    {
        return  $this->ApiRequest('/loyaltyprogram/members/'.$accountID, 'POST', json_encode($member));
    }

    public function getMemberByPhone($accountID, $phone)
    {
        $this->info('getMemberByPhone, accoundid='.$accountID.', phone='.$phone);
        $res = $this->ApiRequest('/loyaltyprogram/members/'.$accountID.'/phone/'.$phone);
        $this->info($res);
        $obj = null;
        if ($res['success']) {
            $obj = $res['result']->Data;
        }

        return $obj;
    }

   	public function getLoyaltyMembers($accountId)
	{
		return $this->getCollectionByPath('/loyaltyprogram/members/' . $accountId);
	}

	public function getAccounts()
	{
		return $this->getCollectionByPath('/accounts');
	}

    public function getCollectionByPath($path)
    {
		$skip=0;
		$take = 500;
		$result = array();
        $res = $this->ApiRequest($path . '?skip='.$skip.'&take='.$take);
        $this->lastResult = $res;
        if ($res['success']) {
            $result = $res['result']->Data->Items;
			$allCount = $res['result']->Data->AllCount;
			$fatalExit = false;
			while (($allCount-1) > count($result) && !$fatalExit) {
				$skip = $skip + $take;
				$res = $this->ApiRequest($path . '?skip='.$skip.'&take='.$take);
				if ($res['success']) 
				{
					foreach ($res['result']->Data->Items as $item) 
					{
						$result[] = $item;
					}
				} 
				else
				{
					$fatalExit = true;
				}
			}
        }

        return $result;
    }

	
    public function OLDgetAccounts($skip = 0, $take = 500)
    {
        $xmlObj = array();
        $res = $this->ApiRequest('/accounts?skip='.$skip.'&take='.$take);
        $this->lastResult = $res;
        if ($res['success']) {
            $xmlObj = $res['result']->Data->Items;
        }

        return $xmlObj;
    }


	public function getAccount($accountId)
    {
        $res = $this->ApiRequest('/accounts/'.$accountId);

		return $res['success'] ? $res['result']->Data : null;
    }

    public function setLoyaltyPoints($accountID, $transaction)
    {
        return $this->ApiRequest('/loyaltyprogram/enterpoints/'.$accountID, 'POST', json_encode($transaction));
    }

    public function setLoyaltyAction($accountID, $transaction)
    {
        return $this->ApiRequest('/loyaltyprogram/enteraction/'.$accountID, 'POST', json_encode($transaction));
    }

    public function setLoyaltyRedemption($accountID, $transaction)
    {
        return $this->ApiRequest('/loyaltyprogram/enterredemption/'.$accountID, 'POST', json_encode($transaction));
    }

    public function setLoyaltyCorrection($accountID, $transaction)
    {
        return $this->ApiRequest('/loyaltyprogram/entercorrection/'.$accountID, 'POST', json_encode($transaction));
    }

    /**
     * Make the Signalmind API request.
     *
     * required $url - API URL
     * required $method - API METHOD (GET/POST/DELETE/...etc...)
     * optional $postBody: xml-string (for POST requests)
     *
     * returns the $res array
     * possible $res array keys:
     * 		success: true/false
     * 		result: SimpleXMLElement object
     * 		raw_result: raw xml-response from server
     * 		status: HTTP-response code from server
     * 		errors: array of strings. Each string contain some error description
     *
     *
     * @param string $url
     * @param string $method
     * @param string $postBody
     *
     * @return array $res
     */
    protected function apiRequest($url, $method = 'GET', $postBody = '')
    {
        $res = $this->_doApiRequest($url, $method, $postBody);


        if ((!$res['success'])) {
            $res = $this->Authentication();
            if ($res['success']) {
                $res = $this->_doApiRequest($url, $method, $postBody);
            }
        }

        return $res;
    }

    protected function _doApiRequest($url, $method = 'GET', $postBody = '')
    {
        $res = array('success' => false, 'errors' => array(), 'result' => '');

        $ch = curl_init();
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
        } elseif ($method == 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method == 'PUT') {
            curl_setopt($ch, CURLOPT_PUT, true);

            $fp = fopen('php://temp/maxmemory:256000', 'w');
            if (!$fp) {
                die('could not open temp memory data');
            }
            fwrite($fp, $postBody);
            fseek($fp, 0);

            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_INFILE, $fp); // file pointer
            curl_setopt($ch, CURLOPT_INFILESIZE, strlen($postBody));
        }
        curl_setopt($ch, CURLOPT_URL, $this->apiURL.$url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authentication: '.$this->accessToken,
        //'Content-Length: ' .strlen($postBody)
        )
        );

    //    if (defined('RESAPI_DEBUG') && RESAPI_DEBUG) {
//            curl_setopt($ch, CURLOPT_VERBOSE, 1);
   //     }

        curl_setopt($ch, CURLOPT_FAILONERROR, false);
    //	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 100); // times out after 10s

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $result = curl_exec($ch); // run the whole process

        if (empty($result)) {
            // some kind of an error happened
            curl_close($ch); // close cURL handler
            $res['errors'][] = curl_error($ch);
        } else {
            $info = curl_getinfo($ch);
            curl_close($ch); // close cURL handler
            if (empty($info['http_code'])) {
                $res['errors'][] = 'No HTTP code was returned';
            } elseif ($info['http_code'] !== 200) {
                $res['errors'][] = 'Server returned bad status code: '.$info['http_code'];
                $res['status'] = $info['http_code'];
                $res['raw_result'] = $result;
                //$res['errors'][] = $result;
                $res['result'] = json_decode($result);
            } else {
                $res['result'] = json_decode($result);
                $res['success'] = (is_object($res['result']) && $res['result']->Success) ? true : false;
                $res['raw_result'] = $result;
            }
        }

        return $res;
    }

    private function loadTokenFromFile()
    {
        return file_exists($this->tmpTokenFilename) ? trim(file_get_contents($this->tmpTokenFilename)) : '';
    }

    private function saveTokenToFile($token)
    {
        file_put_contents($this->tmpTokenFilename, $token, LOCK_EX);
    }
}
