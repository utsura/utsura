<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\YamatoPayment4\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\Master\AbstractMasterEntity;

/**
 * PaymentStatus
 *
 * @ORM\Table(name="yamato_payment_status")
 * @ORM\Entity(repositoryClass="Plugin\YamatoPayment4\Repository\YamatoPaymentStatusRepository")
 */
class YamatoPaymentStatus extends AbstractMasterEntity
{
    // 決済ステータス
    const YAMATO_ACTION_STATUS_SEND_REQUEST = 0;           // 決済依頼済み
    const YAMATO_ACTION_STATUS_COMP_REQUEST = 1;           // 決済申込完了
    const YAMATO_ACTION_STATUS_PROMPT_REPORT = 2;          // 入金完了（速報）
    const YAMATO_ACTION_STATUS_DIFINIT_REPORT = 3;         // 入金完了（確報）
    const YAMATO_ACTION_STATUS_COMP_AUTH = 4;              // 与信完了
    const YAMATO_ACTION_STATUS_COMP_RESERVE = 5;           // 予約受付完了
    const YAMATO_ACTION_STATUS_NG_CUSTOMER = 11;           // 購入者都合エラー
    const YAMATO_ACTION_STATUS_NG_SHOP = 12;               // 加盟店都合エラー
    const YAMATO_ACTION_STATUS_NG_PAYMENT = 13;            // 決済機関都合エラー
    const YAMATO_ACTION_STATUS_NG_SYSTEM = 14;             // その他システムエラー
    const YAMATO_ACTION_STATUS_NG_RESERVE = 15;            // 予約販売与信エラー
    const YAMATO_ACTION_STATUS_NG_REQUEST_CANCEL = 16;     // 決済依頼取消エラー
    const YAMATO_ACTION_STATUS_NG_CHANGE_PAYMENT = 17;     // 金額変更NG
    const YAMATO_ACTION_STATUS_NG_TRANSACTION = 20;        // 決済中断
    const YAMATO_ACTION_STATUS_WAIT = 21;                  // 決済手続き中
    const YAMATO_ACTION_STATUS_WAIT_SETTLEMENT = 30;       // 精算確定待ち
    const YAMATO_ACTION_STATUS_COMMIT_SETTLEMENT = 31;     // 精算確定
    const YAMATO_ACTION_STATUS_CANCEL = 40;                // 取消
    const YAMATO_ACTION_STATUS_3D_WAIT = 50;               // 3Dセキュア認証中

    // クロネコ代金後払い用決済ステータス
    const DEFERRED_STATUS_AUTH_OK = 1;              // 承認済み
    const DEFERRED_STATUS_AUTH_CANCEL = 2;          // 取消済み
    const DEFERRED_STATUS_REGIST_DELIV_SLIP = 3;    // 送り状番号登録済み
    const DEFERRED_STATUS_RESEARCH_DELIV = 5;       // 配送要調査
    const DEFERRED_STATUS_SEND_WARNING = 6;         // 警報メール送信済み
    const DEFERRED_STATUS_SALES_OK = 10;            // 売上確定
    const DEFERRED_STATUS_SEND_BILL = 11;           // 請求書発行済み
    const DEFERRED_STATUS_PAID = 12;                // 入金済み
}
