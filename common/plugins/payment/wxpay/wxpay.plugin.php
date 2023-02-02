<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\plugins\payment\wxpay;

use yii;
use yii\helpers\Url;
use Da\QrCode\QrCode;

use common\library\Basewind;
use common\library\Language;

use common\plugins\BasePayment;
use common\plugins\payment\wxpay\SDK;


/**
 * @Id wxpay.plugin.php 2018.6.3 $
 * @author mosir
 */

class Wxpay extends BasePayment
{
	/**
	 * 支付插件实例
	 * @var string $code
	 */
	protected $code = 'wxpay';

	/**
	 * SDK实例
	 * @var object $client
	 */
	private $client = null;

	/**
	 * 提交支付请求
	 * 兼容公众号支付/扫码支付/H5支付
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

		// 公众号
		if (Basewind::isWeixin()) {
			$url = $sdk->createOauthUrlForCode();

			// 公众号打开PC页面
			if ($sdk->terminal == 'PC') {
				header("location:$url");
				exit();
			}

			// 公众号打开H5页面，到H5页去跳转
			$params['redirect'] = $url;
		}

		// H5（非微信浏览器）/扫码
		if (!isset($params['redirect'])) {
			$params = $sdk->getPayform($orderInfo);
			if (!$params) {
				$this->errors = $sdk->errors;
				return false;
			}
			if (isset($params['h5_url'])) {
				$params = ['redirect' => $params['h5_url']];
			}
			if (isset($params['code_url'])) {
				$qrCode = (new QrCode($params['code_url']));
				$params['qrcode'] = $qrCode->writeDataUri();
			}
		}

		return array_merge($params, ['payTradeNo' => $payTradeNo]);
	}

	/**
	 * 针对授权回调后获取的参数
	 */
	public function getParameters($orderInfo, $code = '')
	{
		$sdk = $this->getClient();
		$sdk->payTradeNo = $this->params->payTradeNo;
		$sdk->notifyUrl = $this->createNotifyUrl($this->params->payTradeNo);
		$sdk->returnUrl = $this->createReturnUrl($this->params->payTradeNo);
		$sdk->terminal = $this->getTerminal();
		$sdk->wxcode = $code;

		$params = $sdk->getPayform($orderInfo);
		if (!$params) {
			$this->errors = $sdk->errors;
			return false;
		}

		$result = $sdk->getParameters($params['prepay_id']);
		if (!$result) {
			$this->errors = $sdk->errors;
			return false;
		}

		return $result;
	}

	/**
	 * 提交退款请求（原路退回）
	 */
	public function refund($orderInfo)
	{
		$sdk = $this->getClient();
		$sdk->payTradeNo = $orderInfo['payTradeNo'];
		$sdk->notifyUrl = $this->createNotifyUrl($orderInfo['payTradeNo']);

		$result = $sdk->getRefundform($orderInfo);
		if (!$result) {
			$this->errors = $sdk->errors;
			return false;
		}
		return true;
	}

	/**
	 * 转账到微信零钱（用于提现）
	 */
	public function transfer($orderInfo)
	{
		$sdk = $this->getClient();
		$sdk->payTradeNo = $orderInfo['payTradeNo'];

		$result = $sdk->getTransform($orderInfo);
		if (!$result) {
			$this->errors = $sdk->errors;
			return false;
		}
		return $result->batch_id;
	}

	/**
	 * 查询支付信息
	 * [调试用]
	 */
	public function detail($id)
	{
		$sdk = $this->getClient();
		$sdk->payTradeNo = $id;
		$result = $sdk->getOrderDetail($id);
		if (!$result) {
			$this->errors = $sdk->errors;
			return false;
		}
		return $result;
	}

	/**
	 * 获取返回地址 
	 */
	public function createReturnUrl($payTradeNo = '')
	{
		// for API H5
		if ($this->params->callback) {
			return $this->params->callback . '?payTradeNo=' . $payTradeNo;
		}

		return Url::toRoute(['cashier/wxpay', 'payTradeNo' => $payTradeNo], true);
	}

	/**
	 * 获取通知地址 
	 */
	public function createNotifyUrl($payTradeNo = '')
	{
		return Url::toRoute(['paynotify/wxpay'], true);
	}

	/**
	 * 验证通知结果 
	 */
	public function verifyNotify($orderInfo, $strict = true)
	{
		if (empty($orderInfo)) {
			$this->errors = Language::get('order_info_empty');
			return false;
		}

		$notify = $this->getNotify();

		// 验证通知是否可信
		if (!$this->verifySign($notify, $strict)) {
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

	/**
	 * 应答微信通知
	 */
	public function verifyResult($target = false)
	{
		return $this->getClient()->verifyResult($target);
	}

	/**
	 * 验证签名是否可信 
	 */
	private function verifySign($notify, $strict = true)
	{
		// 异步通知需要严格验签
		if ($strict == true) {
			return $this->getClient()->verifySign($notify);
		}
		return true;
	}

	/**
	 * 获取解密通知信息
	 */
	public function getNotify()
	{
		if (!$this->params) {
			return $this->getClient()->getNotify();
		}
		if (is_string($this->params)) {
			$this->params = json_decode($this->params, true);
		}
		return $this->params;
	}

	public function getNotifySpecificData()
	{
		$notify = $this->getNotify();
		return array(round($notify['amount']['total'] / 100, 2), $notify['transaction_id']);
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
