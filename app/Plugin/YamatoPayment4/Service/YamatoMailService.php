<?php

namespace Plugin\YamatoPayment4\Service;

use Eccube\Repository\BaseInfoRepository;
use Eccube\Service\MailService;

class YamatoMailService extends MailService
{

    /**
     * 請求書再発行通知メール送信
     *
     * @param Order $Order 注文情報
     */
    public function sendInvoiceReissueMail($Order, $container, $userSettings)
    {
        $MailTemplate = $this->mailTemplateRepository->find($this->eccubeConfig['eccube_order_mail_template_id']);

        $body = $this->twig->render($MailTemplate->getFileName(), [
            'Order' => $Order,
        ]);

        $baseInfoRepository = $container->get(BaseInfoRepository::class);
        $BaseInfo = $baseInfoRepository->get();

        $body = <<<__EOS__
{$userSettings['ycf_invoice_reissue_mail_header']}

{$body}

{$userSettings['ycf_invoice_reissue_mail_footer']}
__EOS__;
        
        $message = (new \Swift_Message())
            ->setSubject('【クロネコ代金後払いサービス】請求書再発行のお知らせ')
            ->setFrom([$BaseInfo->getEmail03() => $BaseInfo->getShopName()])
            ->setTo([$userSettings['ycf_invoice_reissue_mail_address']])
            ->setBody($body)
        ;

        $transport = $container->get('swiftmailer.mailer.default.transport.real');
        $mailer = new \Swift_Mailer($transport);
        $mailer->send($message);
    }
}