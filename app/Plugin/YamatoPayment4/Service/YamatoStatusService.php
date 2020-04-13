<?php

namespace Plugin\YamatoPayment4\Service;

use Plugin\YamatoPayment4\Entity\YamatoPaymentStatus;
use Plugin\YamatoPayment4\Util\PluginUtil;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;

/**
 * 決済状況管理クラス
 */
class YamatoStatusService
{
    protected $pluginUtil;
    protected $configRepository;
    protected $yamatoPaymentMethodRepository;

    public function __construct(
        PluginUtil $pluginUtil,
        ConfigRepository $configRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository
    )
    {
        $this->pluginUtil = $pluginUtil;
        $this->configRepository = $configRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
    }

    /**
     * ページネーションされた受注情報を走査して計算した最終与信日を返す。
     *
     * @param PaginatorInterface $pagenation ページネーションされた受注情報
     * @param str $payment_method 決済方法(クレカ or 後払い)
     *
     * @return array $lastAuthDates 最後に与信が下りた日を格納した配列。
     */
    public function calculateLastAuthDates($pagination, $payment_method)
    {
        // 決済方法によって与信OKのステータスコードが違う。
        if($payment_method == 'credit') {
            $status_code_auth_ok = YamatoPaymentStatus::YAMATO_ACTION_STATUS_COMP_AUTH;
        } elseif($payment_method == 'deferred') {
            $status_code_auth_ok = YamatoPaymentStatus::DEFERRED_STATUS_AUTH_OK;
        }

        $lastAuthDates = [];

        $arrPageItems = $pagination->getItems();
        foreach ($arrPageItems as $Order) {
            $orderId = $Order->getId();

            /**
             * $arrYamatoOrderLogs: 1つの受注に対するログの履歴を格納した配列
             * $row ログを表現した連想配列 row['日付'] => ['プロパティ' => 値]
             */
            $arrYamatoOrderLogs = $Order->getYamatoOrder()->get(0)->getMemo09();
            foreach ($arrYamatoOrderLogs as $row) {
                $last_auth = null;
                foreach ($row as $datetime => $col) {
                    if(array_key_exists('action_status', $col) && $col['action_status'] == $status_code_auth_ok) {
                        $last_auth = $datetime;
                        break;
                    }
                }
                if($last_auth !== null) {
                    break;
                }
            }
            $lastAuthDates[$orderId] = $last_auth;
        }

        return $lastAuthDates;
    }

    /**
     * 選択された受注の
     *
     * @param array $Orders 対象受注ID
     * @return array エラーメッセージ
     */
    public function checkErrorOrder($Orders)
    {
        $listErrMsg = [];

        $arrEnableMethodTypeId = $this->configRepository->parseEnablePayments();
        $YamatoEnablePayments = $this->yamatoPaymentMethodRepository->findBy(['memo03' => $arrEnableMethodTypeId]);

        foreach ($YamatoEnablePayments as $YamatoEnablePayment) {
            $enablePaymentIds[] = $YamatoEnablePayment->getPayment()->getId();
        }
        foreach ($Orders as $Order) {
            $orderId = $Order->getId();

            $msg = '注文番号 ' . $orderId . ' : ';

            // 受注ID から 支払方法ID を取得
            $paymentId = $Order->getPayment()->getId();
            // 対応支払方法チェック
            if (in_array($paymentId, $enablePaymentIds, true) === false)
            {
                $listErrMsg[] = $msg . '操作に対応していない決済です。';
                continue;
            }
        }

        return $listErrMsg;
    }
}