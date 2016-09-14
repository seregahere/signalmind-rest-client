<?php

namespace Elkore\SignalMindRestClient;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class HandleLoyalty extends AApiClient
{
    public $accounts = array();
    public $loyaltyAccounts = array();

    private $api;

    public function __construct($apiKey = '', $logFile = '')
    {
        $this->api = new SignalMindApiV2($apiKey);
        if (strlen($logFile)) {
            // create a log channel
            $this->logger = new Logger('HandleLoyalty');
            $this->logger->pushHandler(new StreamHandler($logFile, Logger::INFO));
            $this->api->setLogger($this->logger);
        }
    }

    public function registerMember($member = null)
    {
        $this->info('RegisterMember called');
        $this->info($member);
        if (is_object($member) && $member->RegistrationSourceCode != 'A' && $member->Phone != 'test phone') {
            $this->info('start foreach');
            foreach ($this->getLoyaltySites() as $site) {
                $this->info('checking site '.$site->AccountId.' ['.$site->SiteFullDomainName.']');
                $res = $this->findMember($site->AccountId, $member->Phone);
                if ($res['found']) {
                    $this->info('FOUND');
                    $this->info($res['member']);
                } else {
                    $this->info('NOT FOUND, register new...');
                    $member->IgnoreWebHook = true;
                    $res = $this->api->addLoyaltyMember($site->AccountId, $member);
                    $this->info('Result of registration: ');
                    $this->info($res);
                }
            }
        }
    }

    public function updatePoints($transaction = null)
    {
        $this->info('updatePoints called');
        $this->info($transaction);

        $method = 'setLoyaltyCorrection';

        foreach ($this->getLoyaltySites() as $site) {
            $res = $this->findMember($site->AccountId, $transaction->Member->Phone);

            if ($res['found']) {
                if ($res['member']->Id == $transaction->Member->Id) {
                    $this->info('Skip current ', $res['member']->Id);
                } else {
                    $this->info('Found ', $res['member']->Id);
                    $request = array(
                        'MemberId' => $res['member']->Id,
                        'IgnoreWebHook' => true,
                        'Points' => $transaction->Points,
                        'Amount' => $transaction->Amount,
                    );
                    $this->info('Request: '.json_encode($request));
                    $this->info('Method: '.$method);
                    $res = $this->api->$method($site->AccountId, $request);
                    $this->info('Result: '.json_encode($res));
                }
            }
        }
    }

    public function updateMember($updObj = null)
    {
        $this->info('updateMember called');
        if (is_object($updObj) && is_object($updObj->UpdatedMember) && is_object($updObj->PreviousMemberInfo) && $updObj->PreviousMemberInfo->Phone != 'test phone') {
            $member = $updObj->UpdatedMember;
            $prevMember = $updObj->PreviousMemberInfo;
            $regOnly = ($member == $prevMember);
            $initialID = $prevMember->Id;
            foreach ($this->getLoyaltySites() as $site) {
                $this->info('checking site '.$site->AccountId.' ['.$site->SiteFullDomainName.']');
                $res = $this->findMember($site->AccountId, $prevMember->Phone);

                if ($res['found']) {
                    if ($regOnly) {
                        $this->info('No changes were found, skipped...');
                    } else {
                        if ($initialID != $res['member']->Id) {
                            $this->info('FOUND');
                            $this->info($res['member']);
                            $member->Id = $res['member']->Id;
                            $member->IgnoreWebHook = true;
                            $res = $this->api->updateLoyaltyMember($site->AccountId, $member);
                        } else {
                            $this->info('ID is identical, update not needed');
                            $this->info($res);
                        }
                    }
                } else {
                    $this->info('NOT FOUND, register new...');
                    $newMember = new \stdClass();
                    $newMember->FirstName = $member->FirstName;
                    $newMember->LastName = $member->LastName;
                    $newMember->Phone = $member->Phone;
                    $newMember->Email = $member->Email;
                    $newMember->RegistrationSourceCode = 'A';
                    $newMember->RegistrationSourceCodeDescription = 'API';
                    $newMember->LoyaltyProgramId = $updObj->LoyaltyProgramId;
                    $newMember->ExternalAccountId = $member->ExternalAccountId;
                    $newMember->Birthday = $member->Birthday;
                    $newMember->EventTypeCode = 'S';
                    $newMember->EventTypeDescription = 'Loyalty program user auto-signup';
                    $newMember->IgnoreWebHook = true;
                    $this->info($newMember);
                    $res = $this->api->addLoyaltyMember($site->AccountId, $newMember);
                    $this->info('Result of registration:');
                    $this->info($res);
                }
            }
        }
    }

    public function getRESTClient()
    {
        return $this->api;
    }

    private function _isLoyaltySupported($account)
    {
        $res = false;
        if (is_object($account) && property_exists($account, 'IsLoyaltyProgramAccount') && $account->IsLoyaltyProgramAccount) {
            $res = true;
        }

        return $res;
    }

    public function getLoyaltySites()
    {
        $res = array();
        $this->accounts = $this->api->getAccounts();
        //$this->loyaltyAccounts = $this->getLoyaltySites();
        foreach ($this->accounts as $account) {
            //			if ($account->AccountPlanId == $this->AccountWithLoyalty) :
            if ($this->_isLoyaltySupported($account)) {
                foreach ($account->Sites as $site) {
                    $res[] = $site;
                }
            }
        }

        return $res;
    }

    public function findMember($accountID, $phone)
    {
        $res = array('found' => false, 'member' => null);
        $member = $this->api->getMemberByPhone($accountID, $phone);
        $this->info('Calling getMemberByPhone("'.$accountID.'", "'.$phone.'")');
        if (is_object($member)) {
            $res['found'] = true;
            $res['member'] = $member;
        }

        return $res;
    }
}
