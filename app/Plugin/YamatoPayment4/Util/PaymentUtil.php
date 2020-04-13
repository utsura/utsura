<?php
namespace Plugin\YamatoPayment4\Util;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Util\StringUtil;

use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Service\Method\Credit;

class PaymentUtil {

    private $eccubeConfig;
    private $yamatoPluginRepository;

    /**
     * コンストラクタ
     *
     * @param $eccubeConfig
     * @param $yamatoPluginRepository
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        ConfigRepository $yamatoPluginRepository
    )
    {
        $this->eccubeConfig = $eccubeConfig;
        $this->yamatoPluginRepository = $yamatoPluginRepository;
    }

    /**
     * クレジット決済画面表示用パラメーターを取得
     * @param Order $order
     * @param array $paymentInfo
     * @return array
     */
    public function getModuleSettings($order, $paymentInfo)
    {
        $moduleSettings = [];

        $listMdlSetting = $this->yamatoPluginRepository->get()->getSubData();

        if($listMdlSetting) {
            $moduleSettings = $listMdlSetting;

            // トークン決済用URL取得 exec_mode = 0 : テスト、1 : 本番
            if($moduleSettings['exec_mode'] == '0') {
                $moduleSettings['tokenUrl'] = $this->eccubeConfig['TOKEN_URL_0'];
            } else {
                $moduleSettings['tokenUrl'] = $this->eccubeConfig['TOKEN_URL_1'];
            }

            // 3Dセキュアの利用およびセキュリティコードの利用によって認証区分を設定
            $moduleSettings['auth_div'] = $paymentInfo['TdFlag'] == '0' ? '2' : '3';
            $moduleSettings['useSecurityCode'] = $this->eccubeConfig['USE_SECURITY_CODE'];
            if($moduleSettings['useSecurityCode'] == '0') {
                if($moduleSettings['auth_div'] == '3') {
                    $moduleSettings['auth_div'] = '1';
                } else {
                    $moduleSettings['auth_div'] = '0';
                }
            }

            // 会員情報とチェックサム取得
            $Customer = $order->getCustomer();
            if($Customer) {
                $customerId = $order->getCustomer()->getId();
            } else {
                $customerId = '0';
            }
            $moduleSettings['member_id'] = '';
            $moduleSettings['authentication_key'] = '';
            $moduleSettings['no_member_check_sum'] = $this->getCheckSumForToken($moduleSettings, $listMdlSetting);
            $moduleSettings['member_id'] = $this->getMemberId($customerId);
            $moduleSettings['authentication_key'] = $this->getAuthenticationKey($customerId);
            $moduleSettings['check_sum'] = $this->getCheckSumForToken($moduleSettings, $listMdlSetting);

            $moduleSettings['saveLimit'] = $this->eccubeConfig['CREDIT_SAVE_LIMIT'];
        }

        return $moduleSettings;
    }

    public function getModuleSettingsNoOrder($Customer, $paymentInfo)
    {
        $moduleSettings = [];

        $listMdlSetting = $this->yamatoPluginRepository->get()->getSubData();

        if($listMdlSetting) {
            $moduleSettings = $listMdlSetting;

            // トークン決済用URL取得 exec_mode = 0 : テスト、1 : 本番
            if($moduleSettings['exec_mode'] == '0') {
                $moduleSettings['tokenUrl'] = $this->eccubeConfig['TOKEN_URL_0'];
            } else {
                $moduleSettings['tokenUrl'] = $this->eccubeConfig['TOKEN_URL_1'];
            }

            // 3Dセキュアの利用およびセキュリティコードの利用によって認証区分を設定
            $moduleSettings['auth_div'] = $paymentInfo['TdFlag'] == '0' ? '2' : '3';
            $moduleSettings['useSecurityCode'] = $this->eccubeConfig['USE_SECURITY_CODE'];
            if($moduleSettings['useSecurityCode'] == '0') {
                if($moduleSettings['auth_div'] == '3') {
                    $moduleSettings['auth_div'] = '1';
                } else {
                    $moduleSettings['auth_div'] = '0';
                }
            }

            // 会員情報とチェックサム取得
            if($Customer) {
                $customerId = $Customer->getId();
            } else {
                $customerId = '0';
            }
            $moduleSettings['member_id'] = '';
            $moduleSettings['authentication_key'] = '';
            $moduleSettings['no_member_check_sum'] = $this->getCheckSumForToken($moduleSettings, $listMdlSetting);
            $moduleSettings['member_id'] = $this->getMemberId($customerId);
            $moduleSettings['authentication_key'] = $this->getAuthenticationKey($customerId);
            $moduleSettings['check_sum'] = $this->getCheckSumForToken($moduleSettings, $listMdlSetting);

            $moduleSettings['saveLimit'] = $this->eccubeConfig['CREDIT_SAVE_LIMIT'];
        }

        return $moduleSettings;
    }

    /**
     * チェックサム取得
     *
     * @param array $arrParam パラメタ
     * @param array $arrMdlSetting モジュール設定
     * @return string
     */
    public static function getCheckSum($arrParam, $arrMdlSetting)
    {
        $authKey = $arrParam['authentication_key'];
        $memberId =$arrParam['member_id'];
        $accessKey = $arrMdlSetting['access_key'];

        $checksum = hash('sha256', $memberId.$authKey.$accessKey);
        return $checksum;
    }

    /**
     * トークン発行時のチェックサム取得
     *
     * @param array $listParam パラメタ
     * @param array $listMdlSetting モジュール設定
     * @return string
     */
    public static function getCheckSumForToken($listParam, $listMdlSetting)
    {
        $memberId = $listParam['member_id'];
        $authKey = $listParam['authentication_key'];
        $accessKey = $listMdlSetting['access_key'];
        $authDiv = $listParam['auth_div'];

        $checksum = hash('sha256', $memberId . $authKey . $accessKey . $authDiv);
        return $checksum;
    }

    /**
     * メンバーID取得
     *
     * @param integer $customer_id
     * @return integer $customer_id
     */
    public static function getMemberId($customer_id)
    {
        return ($customer_id != '0') ? $customer_id : date('YmdHis');
    }

    /**
     * 認証キー取得
     *
     * @param integer $customer_id 会員ID
     * @return string 認証キー
     */
    public static function getAuthenticationKey($customer_id)
    {
        return ($customer_id != '0') ? $customer_id : StringUtil::quickRandom(8);
    }

    public static function adjustOrderStatus($repository, $ignores = [])
    {
        // 対応状況から｢購入処理中｣｢決済処理中｣を除く一覧を取得
        $listOrderStatus = [];
        $orderStatuses = $repository->findAllArray();
        foreach ($orderStatuses as $key => $value) {
            if ($key != OrderStatus::PENDING && $key != OrderStatus::PROCESSING) {
                if(empty($ignores) == false && in_array($key, $ignores)) {
                    continue;
                }
                $listOrderStatus[$key] = $value;
            }
        }

        return $listOrderStatus;
    }
}