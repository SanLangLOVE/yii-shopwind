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

use common\library\Def;
use common\library\Language;

use common\plugins\payment\alipay\lib\AopClient;
use common\plugins\payment\alipay\lib\AopCertClient;
use common\plugins\payment\alipay\lib\request\AlipayTradePagePayRequest;
use common\plugins\payment\alipay\lib\request\AlipayTradeWapPayRequest;
use common\plugins\payment\alipay\lib\request\AlipayTradeAppPayRequest;
use common\plugins\payment\alipay\lib\request\AlipayTradeRefundRequest;
use common\plugins\payment\alipay\lib\request\AlipayFundTransUniTransferRequest;

/**
 * @Id SDK.php 2018.7.19 $
 * @author mosir
 *
 * docs: 手机网站支付 https://docs.open.alipay.com/203/107090/
 * docs: 电脑网站支付 https://docs.open.alipay.com/270/105900/
 * docs: APP支付 https://opendocs.alipay.com/open/204/105297/
 * docs: 转账到支付宝：https://opendocs.alipay.com/open/309/106235/
 * docs：退款接口：https://opendocs.alipay.com/open/02ivbx
 */

class SDK
{
	/**
	 * 网关地址
	 * @var string $gateway
	 */
	public $gateway = 'https://openapi.alipay.com/gateway.do';

	/**
	 * 商户ID
	 * @var string $appId
	 */
	public $appId;

	/**
	 * 支付宝公钥
	 * @var string $alipayrsaPublicKey
	 */
	public $alipayrsaPublicKey;

	/**
	 * 支付宝公钥证书
	 */
	public $alipayCertPath;

	/**
	 * 支付宝根证书
	 */
	public $rootCertPath;

	/**
	 * 应用公钥
	 * @var string $rsaPublicKey
	 */
	public $rsaPublicKey;

	/**
	 * 应用公钥证书
	 */
	public $appCertPath;

	/**
	 * 应用私钥
	 * @var string $rsaPrivateKey
	 */
	public $rsaPrivateKey;

	/**
	 * 签名类型
	 * @var string $signType
	 */
	public $signType = 'RAS2';

	/**
	 * 支付交易号
	 * @var string $payTradeNo
	 */
	public $payTradeNo;

	/**
	 * 异步通知地址
	 * @var string $notifyUrl
	 */
	public $notifyUrl;

	/**
	 * 同步通知地址
	 * @var string $returnUrl
	 */
	public $returnUrl;

	/**
	 * 支付终端
	 * @var string $terminal
	 * @return string APP|WAP|PC|MP
	 */
	public $terminal;

	/**
	 * 抓取错误
	 */
	public $errors;

	/**
	 * 构造函数
	 */
	public function __construct(array $config)
	{
		foreach ($config as $key => $value) {

			if (substr($value, -3) == 'crt') {
				$value = dirname(__FILE__) . DIRECTORY_SEPARATOR . $value;
			}
			$this->$key = $value;
		}
	}

	/**
	 * 订单支付
	 */
	public function getPayform($orderInfo = array())
	{
		try {

			$aop = $this->getInstance();
			if (!$aop) {
				return false;
			}

			$biz_content = array(
				'subject'       => $orderInfo['title'],
				'out_trade_no'  => $this->payTradeNo,
				'total_amount'  => $orderInfo['amount'],
			);

			if ($this->terminal == 'WAP') {
				$request = new AlipayTradeWapPayRequest();
				$biz_content['product_code'] = 'QUICK_WAP_WAY';
			} else if ($this->terminal == 'APP') {
				$request = new AlipayTradeAppPayRequest();
				$biz_content['product_code'] = 'QUICK_MSECURITY_PAY';
			} else {
				$request = new AlipayTradePagePayRequest();
				$biz_content['product_code'] = 'FAST_INSTANT_TRADE_PAY';
			}

			$request->setBizContent(json_encode($biz_content));
			$request->setReturnUrl($this->returnUrl);
			$request->setNotifyUrl($this->notifyUrl);

			if ($this->terminal == 'APP') {
				return $aop->sdkExecute($request);
			}

			return $aop->pageExecute($request, 'get');
		} catch (\Exception $e) {
			$this->errors = $e->getMessage();
			return false;
		}
	}

	/**
	 * 订单退款
	 * 由支付宝处理原路返回策略
	 */
	public function getRefundform($orderInfo = array())
	{
		try {

			$aop = $this->getInstance();
			if (!$aop) {
				return false;
			}

			$biz_content = array(
				//'refund_reason' => '',
				'out_trade_no'  => $this->payTradeNo,
				'refund_amount'  => $orderInfo['amount'],
				'out_request_no' => $orderInfo['refund_sn'], // 标识一次退款请求，需要保证在交易号下唯一，如需部分退款，则此参数必传
			);

			$request = new AlipayTradeRefundRequest();
			$request->setBizContent(json_encode($biz_content));
			$result = $aop->execute($request);

			$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
			$resultCode = $result->$responseNode->code;
			if (empty($resultCode) || $resultCode != 10000) {
				$this->errors = $result->$responseNode->sub_msg ? $result->$responseNode->sub_msg : $result->$responseNode->msg;
				return false;
			}

			return true;
		} catch (\Exception $e) {
			$this->errors = $e->getMessage();
			return false;
		}
	}

	/**
	 * 转账接口只支持证书模式
	 */
	public function getTransform($orderInfo = array())
	{
		if (!$this->appId || !$this->rsaPrivateKey || !$this->alipayCertPath || !$this->appCertPath || !$this->rootCertPath) {
			$this->errors = Language::get('params fail');
			return false;
		}

		try {

			$aop = new AopCertClient();
			$aop->appId 				= $this->appId;
			$aop->rsaPrivateKey 		= $this->rsaPrivateKey;
			$aop->postCharset 			= Yii::$app->charset;
			$aop->signType 				= $this->signType;
			$aop->apiVersion 			= '1.0';

			// 证书模式
			$aop->alipayrsaPublicKey 	= $aop->getPublicKey($this->alipayCertPath);

			//是否校验自动下载的支付宝公钥证书，如果开启校验要保证支付宝根证书在有效期内
			$aop->isCheckAlipayPublicCert = true;

			//调用getCertSN获取证书序列号
			$aop->appCertSN = $aop->getCertSN($this->appCertPath);

			//调用getRootCertSN获取支付宝根证书序列号
			$aop->alipayRootCertSN = $aop->getRootCertSN($this->rootCertPath);

			// 提现金额最低0.1
			$biz_content = array(
				'order_title' => $orderInfo['title'],
				'out_biz_no'  => $this->payTradeNo,
				'trans_amount' => $orderInfo['amount'],
				'product_code' => 'TRANS_ACCOUNT_NO_PWD',
				'payee_info'  => [
					'identity'  => $orderInfo['payee']['account'],
					'identity_type' => 'ALIPAY_LOGON_ID',
					'name' => $orderInfo['payee']['name'],
				],
				'biz_scene' => 'DIRECT_TRANSFER',
				//'remark' => '',
			);

			$request = new AlipayFundTransUniTransferRequest();
			$request->setBizContent(json_encode($biz_content));
			$result = $aop->execute($request);

			$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
			$resultCode = $result->$responseNode->code;
			if (empty($resultCode) || $resultCode != 10000) {
				$this->errors = $result->$responseNode->sub_msg ? $result->$responseNode->sub_msg : $result->$responseNode->msg;
				return false;
			}

			return $result->$responseNode;
		} catch (\Exception $e) {
			$this->errors = $e->getMessage();
			return false;
		}
	}

	/**
	 * 获取支付工厂类
	 */
	private function getInstance()
	{
		if (!$this->appId || !$this->rsaPrivateKey || !$this->alipayCertPath || !$this->appCertPath || !$this->rootCertPath) {
			$this->errors = Language::get('params fail');
			return false;
		}

		$aop = new AopCertClient();
		$aop->appId 				= $this->appId;
		$aop->rsaPrivateKey 		= $this->rsaPrivateKey;
		$aop->postCharset 			= Yii::$app->charset;
		$aop->signType 				= $this->signType;
		$aop->apiVersion 			= '1.0';

		// 证书模式
		$aop->alipayrsaPublicKey = $aop->getPublicKey($this->alipayCertPath);

		//是否校验自动下载的支付宝公钥证书，如果开启校验要保证支付宝根证书在有效期内
		$aop->isCheckAlipayPublicCert = true;

		//调用getCertSN获取证书序列号
		$aop->appCertSN = $aop->getCertSN($this->appCertPath);

		//调用getRootCertSN获取支付宝根证书序列号
		$aop->alipayRootCertSN = $aop->getRootCertSN($this->rootCertPath);

		return $aop;
	}

	/**
	 * 验证通知
	 */
	public function verifyNotify($orderInfo, $notify)
	{
		// 验证与本地信息是否匹配。这里不只是付款通知，有可能是发货通知，确认收货通知
		if ($orderInfo['payTradeNo'] != $notify['out_trade_no']) {
			// 通知中的订单与欲改变的订单不一致
			$this->errors = Language::get('order_inconsistent');
			return false;
		}

		// 必须加round避免字符类型不一致导致比对有误
		if (round($orderInfo['amount']) != round($notify['total_amount'])) {
			// 支付的金额与实际金额不一致
			$this->errors = Language::get('price_inconsistent');
			return false;
		}

		//至此，说明通知是可信的，订单也是对应的，可信的
		if (in_array($notify['trade_status'], ['TRADE_FINISHED', 'TRADE_SUCCESS'])) {
			$order_status = Def::ORDER_ACCEPTED;
		} elseif (in_array($notify['trade_status'], ['TRADE_CLOSED'])) {
			$order_status = Def::ORDER_CANCELED;
		} else {
			$this->errors = Language::get('undefined_status');
			return false;
		}
		return array('target' => $order_status);
	}

	/**
	 * 验证签名
	 */
	public function verifySign($notify)
	{
		// RSA2密钥验签
		//$aop = new AopClient();
		//$aop->alipayrsaPublicKey 	= $this->alipayrsaPublicKey;
		//return $aop->rsaCheckV1($notify, $this->alipayrsaPublicKey, $this->signType);

		// 公钥证书验签
		$aop = new AopCertClient();
		$aop->alipayrsaPublicKey = $aop->getPublicKey($this->alipayCertPath);
		return $aop->rsaCheckV1($notify, $this->alipayCertPath, $this->signType);
	}
}
