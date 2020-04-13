<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4;

use Eccube\Event\TemplateEvent;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperCredit;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GmoPaymentGateway4Event implements EventSubscriberInterface
{
    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperCredit
     */
    protected $PaymentHelperCredit;

    /**
     * コンストラクタ
     *
     * @param PaymentHelperCredit $PaymentHelperCredit
     */
    public function __construct(
        PaymentHelperCredit $PaymentHelperCredit
    ) {
        $this->PaymentHelperCredit = $PaymentHelperCredit;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            '@admin/Setting/Shop/payment_edit.twig'
                => 'onAdminSettingShopPaymentEditTwig',
            '@admin/Order/edit.twig' => 'onAdminOrderEditTwig',
            '@admin/Customer/edit.twig' => 'onAdminCustomerEditTwig',
            'Shopping/index.twig' => 'onDefaultShoppingIndexTwig',
            'Shopping/confirm.twig' => 'onDefaultShoppingConfirmTwig',
            'Shopping/complete.twig' => 'onDefaultShoppingCompleteTwig',
            'Mypage/index.twig' => 'onDefaultMypageNaviTwig',
            'Mypage/history.twig' => 'onDefaultMypageNaviTwig',
            'Mypage/favorite.twig' => 'onDefaultMypageNaviTwig',
            'Mypage/change.twig' => 'onDefaultMypageNaviTwig',
            'Mypage/change_complete.twig' => 'onDefaultMypageNaviTwig',
            'Mypage/delivery.twig' => 'onDefaultMypageNaviTwig',
            'Mypage/delivery_edit.twig' => 'onDefaultMypageNaviTwig',
            'Mypage/withdraw.twig' => 'onDefaultMypageNaviTwig',
            '@GmoPaymentGateway4/mypage_card.twig'
                => 'onDefaultMypageNaviTwig',
        ];
    }

    /**
     * 管理画面 -> 設定 -> 店舗設定 -> 支払方法設定
     */
    public function onAdminSettingShopPaymentEditTwig(TemplateEvent $event)
    {
        $event->addSnippet('@GmoPaymentGateway4/admin/payment_edit.twig');
    }

    /**
     * 管理画面 -> 受注管理 -> 受注一覧 -> 受注修正
     */
    public function onAdminOrderEditTwig(TemplateEvent $event)
    {
        $event->addSnippet('@GmoPaymentGateway4/admin/order_edit.twig');
    }

    /**
     * 管理画面 -> 会員管理 -> 会員一覧 -> 会員修正
     */
    public function onAdminCustomerEditTwig(TemplateEvent $event)
    {
        $event->addSnippet('@GmoPaymentGateway4/admin/customer_edit.twig');
    }

    /**
     * フロント -> 商品購入
     */
    public function onDefaultShoppingIndexTwig(TemplateEvent $event)
    {
        $event->addSnippet('@GmoPaymentGateway4/payment.twig');
    }

    /**
     * フロント -> 商品購入(確認)
     */
    public function onDefaultShoppingConfirmTwig(TemplateEvent $event)
    {
        $event->addSnippet('@GmoPaymentGateway4/payment_confirm.twig');
    }

    /**
     * フロント -> 商品購入(完了)
     */
    public function onDefaultShoppingCompleteTwig(TemplateEvent $event)
    {
        $event->addSnippet('@GmoPaymentGateway4/payment_complete.twig');
    }

    /**
     * フロント -> マイページ -> ナビ
     */
    public function onDefaultMypageNaviTwig(TemplateEvent $event)
    {
        $event->setParameter
            ('isAvailableCardEdit',
             $this->PaymentHelperCredit->isAvailableCardEdit());
        $event->addSnippet('@GmoPaymentGateway4/mypage_navi.twig');
    }
}
