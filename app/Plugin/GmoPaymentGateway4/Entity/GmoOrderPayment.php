<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\Order;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;

/**
 * GmoOrderPayment
 *
 * @ORM\Table(name="plg_gmo_payment_gateway_order_payment")
 * @ORM\Entity(repositoryClass="Plugin\GmoPaymentGateway4\Repository\GmoOrderPaymentRepository")
 */
class GmoOrderPayment
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="order_id", type="integer", options={"unsigned":true})
     */
    private $order_id;

    /**
     * @var text
     *
     * @ORM\Column(name="memo01", type="text", nullable=true)
     */
    private $memo01;

    /**
     * @var text
     *
     * @ORM\Column(name="memo02", type="text", nullable=true)
     */
    private $memo02;

    /**
     * @var text
     *
     * @ORM\Column(name="memo03", type="text", nullable=true)
     */
    private $memo03;

    /**
     * @var text
     *
     * @ORM\Column(name="memo04", type="text", nullable=true)
     */
    private $memo04;

    /**
     * @var text
     *
     * @ORM\Column(name="memo05", type="text", nullable=true)
     */
    private $memo05;

    /**
     * @var text
     *
     * @ORM\Column(name="memo06", type="text", nullable=true)
     */
    private $memo06;

    /**
     * @var text
     *
     * @ORM\Column(name="memo07", type="text", nullable=true)
     */
    private $memo07;

    /**
     * @var text
     *
     * @ORM\Column(name="memo08", type="text", nullable=true)
     */
    private $memo08;

    /**
     * @var text
     *
     * @ORM\Column(name="memo09", type="text", nullable=true)
     */
    private $memo09;

    /**
     * @var text
     *
     * @ORM\Column(name="memo10", type="text", nullable=true)
     */
    private $memo10;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getOrderId()
    {
        return $this->order_id;
    }

    /**
     * @return text
     */
    public function getMemo01()
    {
        return $this->memo01;
    }

    /**
     * @return text
     */
    public function getMemo02()
    {
        return $this->memo02;
    }

    /**
     * @return text
     */
    public function getMemo03()
    {
        return $this->memo03;
    }

    /**
     * @return text
     */
    public function getMemo04()
    {
        return $this->memo04;
    }

    /**
     * @return text
     */
    public function getMemo05()
    {
        return $this->memo05;
    }

    /**
     * @return text
     */
    public function getMemo06()
    {
        return $this->memo06;
    }

    /**
     * @return text
     */
    public function getMemo07()
    {
        return $this->memo07;
    }

    /**
     * @return text
     */
    public function getMemo08()
    {
        return $this->memo08;
    }

    /**
     * @return text
     */
    public function getMemo09()
    {
        return $this->memo09;
    }

    /**
     * @return text
     */
    public function getMemo10()
    {
        return $this->memo10;
    }

    /**
     * @param integer $order_id
     *
     * @return $this;
     */
    public function setOrderId($order_id)
    {
        $this->order_id = $order_id;

        return $this;
    }

    /**
     * @param text $memo01
     *
     * @return $this;
     */
    public function setMemo01($memo01)
    {
        $this->memo01 = $memo01;

        return $this;
    }

    /**
     * @param text $memo02
     *
     * @return $this;
     */
    public function setMemo02($memo02)
    {
        $this->memo02 = $memo02;

        return $this;
    }

    /**
     * @param text $memo03
     *
     * @return $this;
     */
    public function setMemo03($memo03)
    {
        $this->memo03 = $memo03;

        return $this;
    }

    /**
     * @param text $memo04
     *
     * @return $this;
     */
    public function setMemo04($memo04)
    {
        $this->memo04 = $memo04;

        return $this;
    }

    /**
     * @param text $memo05
     *
     * @return $this;
     */
    public function setMemo05($memo05)
    {
        $this->memo05 = $memo05;

        return $this;
    }

    /**
     * @param text $memo06
     *
     * @return $this;
     */
    public function setMemo06($memo06)
    {
        $this->memo06 = $memo06;

        return $this;
    }

    /**
     * @param text $memo07
     *
     * @return $this;
     */
    public function setMemo07($memo07)
    {
        $this->memo07 = $memo07;

        return $this;
    }

    /**
     * @param text $memo08
     *
     * @return $this;
     */
    public function setMemo08($memo08)
    {
        $this->memo08 = $memo08;

        return $this;
    }

    /**
     * @param text $memo09
     *
     * @return $this;
     */
    public function setMemo09($memo09)
    {
        $this->memo09 = $memo09;

        return $this;
    }

    /**
     * @param text $memo10
     *
     * @return $this;
     */
    public function setMemo10($memo10)
    {
        $this->memo10 = $memo10;

        return $this;
    }

    /**
     * AccessID および AccessPass が存在するか確認する
     *
     * @return boolean
     */
    public function isExistsAccessIDAndPass()
    {
        $memo05 = $this->getMemo05();
        if (is_null($memo05)) {
            return false;
        }

        $data = json_decode($memo05, true);
        if (isset($data['AccessID']) && isset($data['AccessPass'])) {
            return true;
        }

        return false;
    }

    /**
     * 決済画面の入力値を取得する
     *
     * @return GmoPaymentInput
     */
    public function getGmoPaymentInput()
    {
        $GmoPaymentInput = new GmoPaymentInput();

        $data = $this->getMemo01();
        if (!is_null($data) && !empty($data)) {
            $GmoPaymentInput->setArrayData(json_decode($data, true));
        }

        return $GmoPaymentInput;
    }

    /**
     * 決済画面の入力値をセットする
     *
     * @param GmoPaymentInput $GmoPaymentInput
     * @return $this
     */
    public function setGmoPaymentInput(GmoPaymentInput $GmoPaymentInput)
    {
        $data = $GmoPaymentInput->getArrayData();
        if (empty($data)) {
            $this->setMemo01(null);
        } else {
            $this->setMemo01(json_encode($data));
        }

        return $this;
    }

    /**
     * GMO-PG インタフェース送信で必要になるOrderIDを返す
     *
     * @return string OrderID
     */
    public function getGmoOrderID()
    {
        $paymentLog = $this->getPaymentLogData();
        if (isset($paymentLog['OrderID']) &&
            !is_null($paymentLog['OrderID']) &&
            !empty($paymentLog['OrderID'])) {
            // 生成済みのOrderIDを返す
            return $paymentLog['OrderID'];
        }

        // 新規に生成して返す
        return $this->getOrderId() . '-' . date('dHis');
    }

    /**
     * GMO-PG インタフェース送受信ログデータを保存する
     *
     * @param array $data 送信受信データ配列
     * @param boolean $isOnlyLog ログのみ保存するどうか
     * @param Order $Order 注文
     * @return GmoOrderPayment
     */
    public function setPaymentLogData
        (array $data, $isOnlyLog, Order $Order = null)
    {
        PaymentUtil::logInfo('GmoOrderPayment::setPaymentLogData start.');

        if (isset($data[0]) && is_array($data[0])) {
            $arrTemp = $data[0];
            unset($data[0]);
            $data = array_merge((array)$data, (array)$arrTemp);
        }

        foreach ($data as $key => $val) {
            if (!$val || is_array($val) || preg_match('/^[\w\s]+$/i', $val)) {
                continue;
            }

            $char_code = "UTF-8";
            $temp = mb_convert_encoding($val, 'sjis-win', $char_code);
            $temp = mb_convert_encoding($temp, $char_code, 'sjis-win');
            if ($val !== $temp) {
                $temp = mb_convert_encoding($val, $char_code, 'sjis-win');
                $temp = mb_convert_encoding($temp, 'sjis-win', $char_code);
                if ($val === $temp) {
                    $data[$key] =
                        mb_convert_encoding($val, $char_code, 'sjis-win');
                } else {
                    $data[$key] = 'unknown encoding strings';
                }
            }
        }

        $paymentLog = [];

        $memo09 = $this->getMemo09();
        if (!is_null($memo09) && !empty($memo09)) {
            $paymentLog = json_decode($memo09, true);
        }

        $paymentLog[] = [date('Y-m-d H:i:s') => $data];
        $this->setMemo09(json_encode($paymentLog));

        if (!$isOnlyLog) {
            $paymentLog = [];

            $memo05 = $this->getMemo05();
            if (!is_null($memo05) && !empty($memo05)) {
                $paymentLog = json_decode($memo05, true);
            }

            foreach ($data as $key => $val) {
                if (empty($val) && !empty($paymentLog[$key])) {
                    if ($key !== 'action_status' &&
                        $key !== 'pay_status') {
                        unset($data[$key]);
                    }
                }
            }

            $paymentLog = array_merge($paymentLog, (array)$data);

            $this->setMemo05(json_encode($paymentLog));

            if (isset($data['pay_status'])) {
                $this->setMemo04($data['pay_status']);
                if (is_null($Order)) {
                    return $this;
                }
                // 注文にもセットする
                $Order->setGmoPaymentGatewayPaymentStatus($data['pay_status']);
                PaymentUtil::logInfo
                    ("Set payment_status = " . $data['pay_status'] .
                     ", order_id = " . $Order->getId());
            }
        }

        PaymentUtil::logInfo('GmoOrderPayment::setPaymentLogData end.');

        return $this;
    }

    /**
     * GMO-PG インタフェース送受信ログデータを取得する
     *
     * @return array 送受信ログデータ配列
     */
    public function getPaymentLogData()
    {
        $results = [];

        $memo05 = $this->getMemo05();
        if (!is_null($memo05) && !empty($memo05)) {
            $results = json_decode($memo05, true);
        }

        $results['payment_log'] = [];
        $memo09 = $this->getMemo09();
        if (!is_null($memo09) && !empty($memo09)) {
            $results['payment_log'] = json_decode($memo09, true);
        }

        if (isset($results[0]) && is_array($results[0])) {
            $arrTemp = $results[0];
            unset($results[0]);
            $results = array_merge((array)$results, (array)$arrTemp);
        }

        return $results;
    }

    /**
     * 購入完了画面向けのメッセージ配列を保存する
     *
     * @param array $data
     * @return $this
     */
    public function setOrderCompleteMessages(array $data)
    {
        $this->setMemo02(json_encode($data));
        return $this;
    }

    /**
     * 購入完了画面向けのメッセージ配列の先頭にマージする
     *
     * @param array $mergeData 先頭にマージするメッセージ配列
     * @return $this
     */
    public function mergeHeadOrderCompleteMessages(array $mergeData)
    {
        $data = [];

        $memo02 = $this->getMemo02();
        if (!empty($memo02)) {
            $data = json_decode($memo02, true);
        }

        $data = array_merge($mergeData, $data);

        return $this->setOrderCompleteMessages($data);
    }

    /**
     * 購入完了画面向けのメッセージ配列を返す
     *
     * @return array メッセージ配列
     */
    public function getOrderCompleteMessages()
    {
        $data = $this->getMemo02();
        if (is_null($data) || empty($data)) {
            return [];
        }

        $messages = [];
        $data = json_decode($data, true);
        if (isset($data['title'])) {
            $messages[] = $data['title']['name'] . "\n";
        }
        foreach ($data as $key => $values) {
            if ($key !== 'title') {
                $message = "";
                if (!empty($values['name'])) {
                    $message .= $values['name'];
                }
                if (!empty($message) && !empty($values['value'])) {
                    $message .= ": ";
                }
                if (!empty($values['value'])) {
                    $message .= $values['value'];
                }
                $messages[] = $message . "\n";
            }
        }

        return $messages;
    }
}
