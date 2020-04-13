<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service;

use Eccube\Entity\Customer;
use Plugin\GmoPaymentGateway4\Entity\GmoMember;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;

/**
 * 会員管理向け決済処理を行うクラス
 */
class PaymentHelperMember extends PaymentHelper
{
    /**
     * GMO-PG 支払方法別のクラス名称を取得する
     *
     * @return string 支払方法別のクラス名称
     */
    protected function getGmoPaymentMethodClass()
    {
        // 特定の支払方法を持たないので null を返却
        // 支払方法別の設定情報は使えないので注意
        return null;
    }

    /**
     * [オーバーライド] 顧客に GMO-PG 情報を付加する
     *
     * @param Eccube\Entity\Customer $Customer
     * @return Eccube\Entity\Customer
     */
    public function prepareGmoInfoForCustomer(Customer $Customer = null)
    {
        $Customer = parent::prepareGmoInfoForCustomer($Customer);
        if (is_null($Customer)) {
            return $Customer;
        }

        $GmoMember = $Customer->getGmoMember();
        if (is_null($GmoMember)) {
            return $Customer;
        }

        // 登録済みのクレジットカードを取得
        $creditCards = $this->searchCard($Customer, [], true);
        $GmoMember->setCreditCards($creditCards);

        return $Customer;
    }

    /**
     * GMO-PG 会員情報を取得する
     *
     * @param Customer $Customer 顧客
     * @return boolean true: exist, false: not exist
     */
    public function isExistGmoMember(Customer $Customer)
    {
        PaymentUtil::logInfo('PaymentHelperMember::isExistGmoMember start.');

        $url = $this->GmoConfig->getServerUrl() . 'SearchMember.idPass';

        $paramNames = [
            'SiteID',
            'SitePass',
            'MemberID',
        ];

        $data = $this->getIfSendData($paramNames, [], null, $Customer);

        $r = $this->sendRequest($url, $data);

        PaymentUtil::logInfo('PaymentHelperMember::isExistGmoMember end.');

        return $r;
    }

    /**
     * GMO-PG 会員登録を行う
     *
     * @param Customer $Customer 顧客
     * @return boolean true: OK, false: NG
     */
    public function saveGmoMember(Customer $Customer)
    {
        PaymentUtil::logInfo('PaymentHelperMember::saveGmoMember start.');

        $url = $this->GmoConfig->getServerUrl() . 'SaveMember.idPass';

        $paramNames = [
            'SiteID',
            'SitePass',
            'MemberID',
            'MemberName',
        ];

        $data = $this->getIfSendData($paramNames, [], null, $Customer);

        $r = $this->sendRequest($url, $data);

        PaymentUtil::logInfo('PaymentHelperMember::saveGmoMember end.');

        return $r;
    }

    /**
     * GMO-PG 会員に登録済みのカードを取得する
     *
     * @param Customer $Customer 顧客
     * @param array $sendData 送信データ
     * @param boolean $isPhysicalSeq 物理モードか論理モードか
     * @return array カード配列|empty
     */
    public function searchCard
        (Customer $Customer, array $sendData = [], $isPhysicalSeq = false)
    {
        PaymentUtil::logInfo('PaymentHelperMember::searchCard start.');

        $url = $this->GmoConfig->getServerUrl() . 'SearchCard.idPass';

        $paramNames = [
            'SiteID',
            'SitePass',
            'MemberID',
            'CardSeq',
        ];

        $data = $this->getIfSendData($paramNames, $sendData, null, $Customer);
        if ($isPhysicalSeq) {
            // get card with SeqCard is physical number, not logical number
            $data['SeqMode'] = 1;
        }

        $r = $this->sendRequest($url, $data);
        if (!$r) {
            return [];
        }

        PaymentUtil::logInfo('PaymentHelperMember::searchCard end.');

        $r = [];
        foreach ($this->results as $result) {
            if ($result['DeleteFlag'] != 0) {
                continue;
            }

            $result['expire_month'] = substr($result['Expire'], 2);
            $result['expire_year'] = substr($result['Expire'], 0, 2);

            $r[] = $result;
        }

        return $r;
    }

    /**
     * GMO-PG 会員にクレジットカードを登録する
     *
     * @param Customer $Customer 顧客
     * @param array $sendData 送信データ
     * @param integer $CardSeq カード登録連番|null
     * @return boolean true: OK, false: NG
     */
    public function saveCard
        (Customer $Customer, array $sendData, $CardSeq = null)
    {
        PaymentUtil::logInfo('PaymentHelperMember::saveCard start.');

        $url = $this->GmoConfig->getServerUrl() . 'SaveCard.idPass';

        $paramNames = [
            'SiteID',
            'SitePass',
            'MemberID',
            'Token',
            'DefaultFlag',
        ];

        if (!array_key_exists('CardSeq', $sendData)) {
            $paramNames[] = 'CardSeq';
        }

        if (!array_key_exists('DefaultFlag', $sendData)) {
            $sendData['DefaultFlag'] = '1';
        }

        $data = $this->getIfSendData($paramNames, $sendData, null, $Customer);
        
        // if add new card, $CardSeq will null
        // if update card, $CardSeq will not null and
        // add it to data array
        if (!is_null($CardSeq)) {   
            $data['SeqMode'] = 1;
            $data['CardSeq'] = $CardSeq;
        }

        $r = $this->sendRequest($url, $data);

        PaymentUtil::logInfo('PaymentHelperMember::saveCard end.');

        return $r;
    }

    /**
     * GMO-PG 会員に登録されたクレジットカードを削除する
     *
     * @param Customer $Customer 顧客
     * @param array $sendData 送信データ
     * @return boolean true: OK, false: NG
     */
    public function deleteCard(Customer $Customer, array $sendData = [])
    {
        PaymentUtil::logInfo('PaymentHelperMember::deleteCard start.');

        $url = $this->GmoConfig->getServerUrl() . 'DeleteCard.idPass';

        $paramNames = [
            'SiteID',
            'SitePass',
            'MemberID',
            'CardSeq',
        ];

        $data = $this->getIfSendData($paramNames, $sendData, null, $Customer);

        //delete card with SeqCard is physical number, not logical
        $data['SeqMode'] = 1;
        
        $r = $this->sendRequest($url, $data);

        PaymentUtil::logInfo('PaymentHelperMember::deleteCard end.');

        return $r;
    }
}
