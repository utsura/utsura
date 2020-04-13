<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Controller;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Customer;
use Plugin\GmoPaymentGateway4\Entity\GmoConfig;
use Plugin\GmoPaymentGateway4\Entity\GmoPaymentInput;
use Plugin\GmoPaymentGateway4\Form\Type\MypageCardType;
use Plugin\GmoPaymentGateway4\Repository\GmoConfigRepository;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperCredit;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperMember;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MypageCardController extends AbstractController
{
    /**
     * @var Plugin\GmoPaymentGateway4\Entity\GmoConfig
     */
    protected $GmoConfig;

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperCredit
     */
    protected $PaymentHelperCredit;

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperMember
     */
    protected $PaymentHelperMember;

    /**
     * MypageCardController constructor.
     *
     * @param GmoConfigRepository $GmoConfigRepository
     * @param PaymentHelperCredit $PaymentHelperCredit
     * @param PaymentHelperMember $PaymentHelperMember
     */
    public function __construct(
        GmoConfigRepository $GmoConfigRepository,
        PaymentHelperCredit $PaymentHelperCredit,
        PaymentHelperMember $PaymentHelperMember
    ) {
        $this->GmoConfig = $GmoConfigRepository->get();
        $this->PaymentHelperCredit = $PaymentHelperCredit;
        $this->PaymentHelperMember = $PaymentHelperMember;
    }

    /**
     * カード情報一覧/編集画面を表示する.
     *
     * @Route("/mypage/gmo_card_edit", name="gmo_mypage_card_edit")
     * @Template("@GmoPaymentGateway4/mypage_card.twig")
     */
    public function index(Request $request)
    {
        PaymentUtil::logInfo('MypageCardController::index start.');

        if (!$this->PaymentHelperCredit->isAvailableCardEdit()) {
            PaymentUtil::logError('Edit your card is not supported.');
            throw new NotFoundHttpException();
        }

        $form = $this->createForm(MypageCardType::class);
        $form->handleRequest($request);

        $Customer = $this->getUser();

        $cardList = [];

        // GMO-PG 会員登録済みかどうかを確認
        if (!$this->PaymentHelperMember->isExistGmoMember($Customer)) {
            // GMO-PG 非会員の場合は登録する
            if (!$this->PaymentHelperMember->saveGmoMember($Customer)) {
                $this->setErrorMessage();
                goto finish;
            }
        }

        // 登録済みクレジットカードを検索
        $cardList =
            $this->PaymentHelperMember->searchCard($Customer, [], true);

        // 登録／修正
        if ($form->isSubmitted() && $form->isValid()) {
            $GmoPaymentInput = new GmoPaymentInput();
            $GmoPaymentInput->setFormData($form);
            $sendData = $GmoPaymentInput->getArrayData();
            if (!$this->PaymentHelperMember
                ->saveCard($Customer, $sendData, $sendData['CardSeq'])) {
                $this->setErrorMessage();
                goto finish;
            }

            // フォームを初期化
            $form = $this->createForm(MypageCardType::class);

            // 登録済みクレジットカードを再検索
            $cardList =
                $this->PaymentHelperMember->searchCard($Customer, [], true);

            $msg = 'gmo_payment_gateway.mypage.card_edit.append.success';
            if ($sendData['CardSeq'] != "") {
                $msg = 'gmo_payment_gateway.mypage.card_edit.modify.success';
            }
            $this->addSuccess(trans($msg));
        }

    finish:

        PaymentUtil::logInfo('MypageCardController::index end.');

        return [
            'form' => $form->createView(),
            'cardList' => $cardList,
            'GmoConfig' => $this->GmoConfig,
        ];
    }

    /**
     * クレジットカード削除
     *
     * @Route("/mypage/gmo_card_edit/delete", name="gmo_mypage_card_edit_delete")
     */
    public function delete(Request $request)
    {
        PaymentUtil::logInfo('MypageCardController::delete start.');

        if (!$this->PaymentHelperCredit->isAvailableCardEdit()) {
            PaymentUtil::logError('Edit your card is not supported.');
            throw new NotFoundHttpException();
        }

        $form = $this->createForm(MypageCardType::class);
        $form->handleRequest($request);

        // 削除処理
        if ($form->isSubmitted() && $form->isValid()) {
            $delete_card_seq = $request->get('delete_card_seq');
            PaymentUtil::logInfo($delete_card_seq);
            if (!is_array($delete_card_seq)) {
                $msg = 'Delete CardSeq is not array.';
                PaymentUtil::logError($msg);
                $this->addDanger($msg);
            } else {
                $Customer = $this->getUser();
                foreach ($delete_card_seq as $card_seq) {
                    $sendData = ['CardSeq' => $card_seq];
                    if (!$this->PaymentHelperMember
                        ->deleteCard($Customer, $sendData)) {
                        $this->setErrorMessage();
                    }
                }
                $this->addSuccess(trans('gmo_payment_gateway.' .
                                        'mypage.card_edit.delete.success'));
            }
        }

        PaymentUtil::logInfo('MypageCardController::delete end.');

        return $this->redirect($this->generateUrl('gmo_mypage_card_edit'));
    }

    /**
     * 処理エラーのメッセージを作成する
     */
    private function setErrorMessage()
    {
        $errors = $this->PaymentHelperMember->getError();
        foreach ($errors as $errMess) {
            $this->addDanger($errMess);
            PaymentUtil::logError($errMess);
        }
    }
}
