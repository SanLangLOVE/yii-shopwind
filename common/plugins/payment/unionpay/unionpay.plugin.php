<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\plugins\payment\unionpay;

use yii;

use common\library\Language;

use common\plugins\BasePayment;
use common\plugins\payment\unionpay\SDK;

/**
 * @Id unionpay.plugin.php 2018.6.3 $
 * @author mosir
 */

class Unionpay extends BasePayment
{
	/**
	 * 支付插件实例
	 * @var string $code
	 */
	protected $code = 'unionpay';

	/**
	 * SDK实例
	 * @var object $client
	 */
	private $client = null;

	/* 获取支付表单 */
	public function pay($orderInfo = array())
	{
		// 支付网关商户订单号
		$payTradeNo = $this->getPayTradeNo($orderInfo);

		$sdk = $this->getClient();
		$sdk->payTradeNo = $payTradeNo;
		$sdk->notifyUrl = $this->createNotifyUrl($payTradeNo);
		$sdk->returnUrl = $this->createReturnUrl($payTradeNo);

		return $this->createPayform($sdk->getPayform($orderInfo), $this->gateway, 'post');
	}

	/* 返回通知结果 */
	public function verifyNotify($orderInfo, $strict = false)
	{
		if (empty($orderInfo)) {
			$this->errors = Language::get('order_info_empty');
			return false;
		}

		$notify = $this->getNotify();

		// 验证通知是否可信
		if (!($sign_result = $this->verifySign($notify, $strict))) {
			// 若本地签名与网关签名不一致，说明签名不可信
			$this->errors = Language::get('sign_inconsistent');
			return false;
		}

		$sdk = $this->getClient();
		if (!($result = $sdk->verifyNotify($orderInfo, $notify))) {
			$this->errors = $sdk->errors;
			return false;
		}
		return $result;
	}

	/* 验证签名是否可信 */
	private function verifySign($notify, $strict = false)
	{
		// 验证签名
		if ($strict == true) {
			return $this->getClient()->verifySign($notify);
		}
		return true;
	}

	public function verifyResult($target = false)
	{
		return $this->verifyResult($target);
	}

	public function getNotifySpecificData()
	{
		$notify = $this->getNotify();
		return array($notify['txnAmt'] / 100, $notify['queryId']);
	}

	/**
	 * 获取SDK实例
	 */
	public function getClient()
	{
		if ($this->client === null) {
			$this->client = new SDK($this->config);
		}
		return $this->client;
	}
}
