<?php

namespace Plugin\YamatoPayment4\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Eccube\Entity\Master\OrderStatus;

class YamatoExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new \Twig_Function('getCssWithOrderStatus', [$this, 'getCssWithOrderStatus'], ['is_safe' => ['all']]),
            new \Twig_Function('getYamatoCreditStatusName', [$this, 'getYamatoCreditStatusName'], ['is_safe' => ['all']]),
            new \Twig_Function('getYamatoDefferedStatusName', [$this, 'getYamatoDefferedStatusName'], ['is_safe' => ['all']]),
        ];
    }

    /**
     * OrderStatusに紐づくHTMLクラスを返す
     *
     * @param integer $orderStatus
     * @return string
     */
    public function getCssWithOrderStatus($orderStatus)
    {
        $lists = [
            OrderStatus::NEW => 'badge-ec-blue',
            OrderStatus::CANCEL => 'badge-ec-glay',
            OrderStatus::IN_PROGRESS => 'badge-ec-blue',
            OrderStatus::DELIVERED => 'badge-ec-glay',
            OrderStatus::PAID => 'badge-ec-green',
            OrderStatus::PENDING => 'badge-ec-yellow',
            OrderStatus::PROCESSING => 'badge-ec-yellow',
            OrderStatus::RETURNED => 'badge-ec-glay',
        ];

        return $lists[$orderStatus];
    }

    /**
     * クロネコクレジットカード決済状況名称を返す
     *
     * @param 決済状況ID $ycsId
     * @return string
     */
    public function getYamatoCreditStatusName($ycsId)
    {
        $lists = [
            0 => '決済依頼済み',
            1 => '決済申込完了',
            2 => '入金完了（速報）',
            3 => '入金完了（確報）',
            4 => '与信完了',
            5 => '予約受付完了',
            11 => '購入者都合エラー',
            12 => '加盟店都合エラー',
            13 => '決済機関都合エラー',
            14 => 'その他システムエラー',
            15 => '予約販売与信エラー',
            16 => '決済依頼取消エラー',
            17 => '金額変更NG',
            20 => '決済中断',
            21 => '決済手続き中',
            30 => '精算確定待ち',
            31 => '精算確定',
            40 => '取消',
            50 => '3Dセキュア認証中',
        ];

        return $lists[$ycsId];
    }

    /**
     * クロネコ代金後払い決済状況名称を返す
     *
     * @param 決済状況ID $ycsId
     * @return string
     */
    public function getYamatoDefferedStatusName($ycsId)
    {
        $lists = [
            1 => '承認済み',
            2 => '取消済み',
            3 => '送り状番号登録済み',
            5 => '配送要調査',
            10 => '売上確定',
            11 => '請求書発行済み',
            12 => '入金済み',
        ];

        return $lists[$ycsId];
    }
}
