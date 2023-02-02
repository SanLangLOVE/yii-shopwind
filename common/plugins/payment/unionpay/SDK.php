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

use common\library\Basewind;
use common\library\Language;
use common\library\Def;

use common\plugins\payment\unionpay\lib\AcpService;

/**
 * @Id SDK.php 2018.7.19 $
 * @author mosir
 *
 */

class SDK
{
	/**
	 * 网关地址
	 * @var string $gateway
	 */
	public $gateway = 'https://gateway.95516.com/gateway/api/frontTransReq.do';

	/**
	 * 商户ID
	 * @var string $merId
	 */
	public $merId;

	/**
	 * 支付交易号
	 * @var string $payTradeNo
	 */
	public $payTradeNo;

	/**
	 * 通知地址
	 * @var string $notifyUrl
	 */
	public $notifyUrl;

	/**
	 * 返回地址
	 * @var string $returnUrl
	 */
	public $returnUrl;

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
			$this->$key = $value;
		}
	}

	public function getPayform($orderInfo = array(), $post = null)
	{
		// 该接口通过此参数区别电脑支付和手机支付
		$channelType = Basewind::isMobileDevice() ? '08' : '07';

		$params = array(
			'version' 			=> '5.0.0',                 //版本号
			'encoding' 			=> Yii::$app->charset,		//编码方式
			'txnType'			=> '01',				      //交易类型
			'txnSubType' 		=> '01',				  //交易子类
			'bizType' 			=> '000201',				  //业务类型 000201：B2C网关支付
			'frontUrl' 			=> $this->returnUrl,  //前台通知地址
			'backUrl' 			=> $this->notifyUrl,  //后台通知地址
			'signMethod' 		=> '01',	              //签名方法
			'channelType' 		=> $channelType,	              //渠道类型，07-PC，08-手机
			'accessType' 		=> '0',		          //接入类型
			'currencyCode' 		=> '156',	          //交易币种，境内商户固定156

			//TODO 以下信息需要填写
			'merId' 			=> $this->merId,		//商户代码，请改自己的测试商户号。
			'orderId' 			=> $this->payTradeNo,	//商户订单号，8-32位数字字母，不能含“-”或“_”。
			'txnTime' 			=> date("YmdHis", time()),	//订单发送时间，格式为YYYYMMDDhhmmss，取北京时间。
			'txnAmt' 			=> $orderInfo['amount'] * 100,	//交易金额，单位分
		);
		//$uri = SDK_FRONT_TRANS_URL;
		//$html_form = AcpService::createAutoFormHtml( $params, $uri );
		//echo $html_form;
		AcpService::sign($params);

		return $params;
	}
	public function verifyNotify($orderInfo, $notify)
	{
		// 验证与本地信息是否匹配。这里不只是付款通知，有可能是发货通知，确认收货通知
		if ($orderInfo['payTradeNo'] != $notify['orderId']) {
			// 通知中的订单与欲改变的订单不一致
			$this->errors = Language::get('order_inconsistent');
			return false;
		}

		// 必须加round避免字符类型不一致导致比对有误
		if (round($orderInfo['amount']) != round($notify['txnAmt']) / 100) {
			// 支付的金额与实际金额不一致
			$this->errors = Language::get('price_inconsistent');
			return false;
		}

		//至此，说明通知是可信的，订单也是对应的，可信的
		if (in_array($notify['respCode'], array('00', 'A6'))) {
			$order_status = Def::ORDER_ACCEPTED;
		} else {
			$this->errors = Language::get('undefined_status');
			return false;
		}
		return array('target' => $order_status);
	}

	public function verifySign($notify)
	{
		if (isset($notify['signature'])) {
			return AcpService::validate($notify);
		}
		return false;
	}
}
