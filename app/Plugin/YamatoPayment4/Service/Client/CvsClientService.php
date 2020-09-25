<?php
namespace Plugin\YamatoPayment4\Service\Client;

use Plugin\YamatoPayment4\Service\Client\BaseClientService;
use Plugin\YamatoPayment4\Util\CommonUtil;
use Eccube\Entity\Order;

class CvsClientService extends BaseClientService
{
    /**
     * コンビニ決済を行う.
     *
     * @param Order $Order
     * @param array $listParam
     * @return bool
     */
    public function doPaymentRequest(Order $Order, $listParam)
    {
        $orderId = $Order->getId();

        // 送信キー
        $sendKey = [
            'function_div',
            'trader_code',
            'device_div',
            'order_no',
            'goods_name',
            'settle_price',
            'buyer_name_kanji',
            'buyer_name_kana',
            'buyer_tel',
            'buyer_email',
        ];

        // コンビニ決済URL
        $function_div = $this->eccubeConfig['CONVENI_FUNCTION_DIV_' . $listParam['cvs']];
        $server_url = $this->getApiUrl($function_div);

        //機能区分
        $listParam['function_div'] = $function_div;
        //決済ステータスを「決済手続き中」で記録
        $listParam['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_WAIT'];

        return $this->sendOrderRequest($server_url, $sendKey, $orderId, $listParam, $Order);
    }
}
