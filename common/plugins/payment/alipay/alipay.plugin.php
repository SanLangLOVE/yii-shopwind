<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\plugins\payment\alipay;

use yii;
use yii\helpers\Url;

use common\library\Basewind;
use common\library\Language;

use common\plugins\BasePayment;
use common\plugins\payment\alipay\SDK;

/**
 * @Id alipay.plugin.php 2018.6.3 $
 * @author mosir
 */

class Alipay extends BasePayment
{
	/**
     * 支付插件实例
	 * @var string $code
	 */
	protected $code = 'alipay';
	
	/**
     * SDK实例
	 * @var object $client
     */
	private $client = null;
	
	/**
	 * 提交支付请求
	 */
	public function pay($orderInfo = array())
    {
		// 支付网关商户订单号
		$payTradeNo = $this->getPayTradeNo($orderInfo);
		
		$sdk = $this->getClient();
		$sdk->payTradeNo = $payTradeNo;
		$sdk->notifyUrl = $this->createNotifyUrl($payTradeNo);
		$sdk->returnUrl = $this->createReturnUrl($payTradeNo);
		$sdk->terminal = $this->getTerminal();

		$params = $sdk->getPayform($orderInfo);
		if(!$params) {
			$this->errors = $sdk->errors;
			return false;
		}

		if($sdk->terminal == 'APP') {
			$params = ['orderInfo' => $params];
		} else if($sdk->terminal == 'WAP' || Basewind::getCurrentApp() == 'api') {
			$params = ['redirect' => $params];
		} else {
			header("location: $params");
			exit(0);
		}

        return array_merge($params, ['payTradeNo' => $payTradeNo]);
    }

	/**
	 * 提交退款请求（原路退回）
	 */
	public function refund($orderInfo)
	{
		$sdk = $this->getClient();
		$sdk->payTradeNo = $orderInfo['payTradeNo'];

		$result = $sdk->getRefundform($orderInfo);
		if(!$result) {
			$this->errors = $sdk->errors;
			return false;
		}
		return true;
	}

	/**
	 * 提交转账请求
	 * 转账到支付宝钱包（提现）
	 * 请确保服务器环境局配置了SSL(SSL certificate problem: unable to get local issuer certificate)
	 */
	public function transfer($orderInfo = array())
    {
		if($orderInfo['amount'] < 0.1) {
			$this->errors = Language::get('money shall not be less than 0.1');
			return false;
		}

		$sdk = $this->getClient();
		$sdk->payTradeNo = $orderInfo['payTradeNo'];
	
		$result = $sdk->getTransform($orderInfo);
        if($result === false) {
			$this->errors = $sdk->errors;
			return false;
		}

		return $result->order_id;
    }
	
	/* 获取通知地址（不支持带参数） */
    public function createNotifyUrl($payTradeNo = '')
    {
        return Url::toRoute(['paynotify/alipay'], true);
    }

    /* 返回通知结果 */
    public function verifyNotify($orderInfo, $strict = true)
    {
        if (empty($orderInfo)) {
			$this->errors = Language::get('order_info_empty');
            return false;
        }
		
		$notify = $this->getNotify();

        // 验证通知是否可信
        if (!$this->verifySign($notify, $strict))
        {
            // 若本地签名与网关签名不一致，说明签名不可信
            $this->errors = Language::get('sign_inconsistent');
            return false;
        }
		
		$sdk = $this->getClient();
		if(!($result = $sdk->verifyNotify($orderInfo, $notify))) {
			$this->errors = $sdk->errors;
            return false;
		}
		return $result;
    }

    /* 验证签名是否可信 */
    private function verifySign($notify, $strict = true)
    {
		// 验证签名
		if($strict == true) {
			return $this->getClient()->verifySign($notify);
		}
		return true;
    }
	
	public function getNotifySpecificData() {
		$notify = $this->getNotify();
		return array($notify['total_amount'], $notify['trade_no']);
	}

	/**
     * 获取SDK实例
     */
    public function getClient()
    {
        if($this->client === null) {
            $this->client = new SDK($this->config);
        }
        return $this->client;
    }
}