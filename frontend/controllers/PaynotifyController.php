<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace frontend\controllers;

use Yii;
use yii\helpers\ArrayHelper;

use common\models\DepositTradeModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Plugin;
use common\library\Page;
use common\library\Def;

/**
 * @Id PaynotifyController.php 2018.7.20 $
 * @author mosir
 */

class PaynotifyController extends \common\controllers\BaseMallController
{
	/**
	 * 初始化
	 * @var array $view 当前视图
	 * @var array $params 传递给视图的公共参数
	 */
	public function init()
	{
		parent::init();
		$this->view  = Page::setView('mall');
		$this->params = ArrayHelper::merge($this->params, Page::getAssign('mall'));
	}
	
	public function actionIndex()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);
		
		// 为了在用户页面不显示支付网关GET过来的隐私数据，把隐私数据过滤点后执行一次跳转（参数R是给路由美化关闭用的，不要删除）
		if(array_diff(array_keys(ArrayHelper::toArray($post)), array('orderId', 'r'))) {
			return $this->redirect(['paynotify/index', 'orderId' => $post->payTradeNo]);
		}
		
        if (empty($post->orderId)) {
            return Message::warning(Language::get('forbidden'));
        }

		// 检索出最后支付的单纯充值或购物（或购买应用）订单，如果最后一笔是支付成功的，那么认为都是支付成功了
		$tradeInfo = DepositTradeModel::find()->where(['buyer_id' => Yii::$app->user->id, 'payTradeNo' => $post->orderId])->orderBy(['trade_id' => SORT_DESC])->asArray()->one();
		 
		if(empty($tradeInfo))
		{
			// 由于支付变更，通过商户交易号找不到对应的交易记录后，插入的资金退回记录
			$tradeInfo = DepositTradeModel::find()->where(['buyer_id' => Yii::$app->user->id, 'tradeNo' => $post->orderId])->orderBy(['trade_id' => SORT_DESC])->asArray()->one();
				
			// 资金退回标记
			if($tradeInfo) $tradeInfo['RETURN_MONEY'] = true;
		}
		
		if(empty($tradeInfo)) {
			return Message::warning(Language::get('trade_info_empty'));
		}
		
		$payment = Plugin::getInstance('payment')->build($tradeInfo['payment_code']);
		$this->params['payInfo'] = $payment->getLinksOfPage($tradeInfo);
		
		$this->params['page'] = Page::seo(['title' => Language::get('paynotify_status')]);
        return $this->render('../paynotify.index.html', $this->params);
	}
	
	public function actionAlipay()
	{
		return $this->notify(Yii::$app->request->post('out_trade_no', 0));
	}
	
	public function actionWxpay()
	{
		$notify = Plugin::getInstance('payment')->build('wxpay')->getNotify();
		return $this->notify($notify['out_trade_no'], json_encode($notify));
	}

	public function actionWxmppay()
	{
		$notify = Plugin::getInstance('payment')->build('wxmppay')->getNotify();
		return $this->notify($notify['out_trade_no'], json_encode($notify));
	}

	public function actionWxapppay()
	{
		$notify = Plugin::getInstance('payment')->build('wxapppay')->getNotify();
		return $this->notify($notify['out_trade_no'], json_encode($notify));
	}
	
	public function actionUnionpay()
	{
		return $this->notify(Yii::$app->request->post('out_trade_no', 0));
	}
	
	/**
	 * 当异步通知Url可以带参数时可使用此
	 */
 	public function actionNotify()
	{
		return $this->notify(Yii::$app->request->post('payTradeNo', 0));
	}
	
	/**
	 * 支付异步通知
	 */
	private function notify($payTradeNo, $params = null)
	{
		if(empty($payTradeNo)) {
			return Message::warning(Language::get('order_info_empty'));
		}
		if(!($orderInfo = DepositTradeModel::getTradeInfoForNotify($payTradeNo))) {
			return Message::warning(Language::get('order_info_empty'));
		}
		
		$payment_code = $orderInfo['payment_code'];
		
		// 货到付款的订单不许进入此通知页面
        if(in_array(strtoupper($payment_code), array('COD'))) {
			return Message::warning(Language::get('forbidden'));
		}
		
		$payment = Plugin::getInstance('payment')->build($payment_code, $params);
		if(!($payment_info = $payment->getInfo()) || !$payment_info['enabled']) {
			return Message::warning(Language::get('no_such_payment'));
        }
		
		if(($notify_result = $payment->verifyNotify($orderInfo, true)) === false) {
			return $payment->verifyResult(false);
		}

		// 到此支付通知数据已经验证完毕，记录下第三方支付平台的部分交易参数
		$payment->updateTradeInfo($payTradeNo);
		
		// 充值订单（处理充值逻辑）
		if(in_array($orderInfo['bizIdentity'], array(Def::TRADE_RECHARGE))) 
		{
			if(!($result = $payment->handleRechargeAfterNotify($orderInfo, $notify_result))) {
				//return Message::warning($payment->errors);
				return $payment->verifyResult(false);
			}
		}	
			
		// 购物订单（处理购物逻辑）
		if(in_array($orderInfo['bizIdentity'], array(Def::TRADE_ORDER)))
		{
			if($payment->handleOrderAfterNotify($orderInfo, $notify_result) === false) {
				//return Message::warning($payment->errors);
				return $payment->verifyResult(false);
			}
		}	
		
		// 购买应用订单（处理购买应用逻辑）
		if(in_array($orderInfo['bizIdentity'], array(Def::TRADE_BUYAPP)))
		{
			if($payment->handleBuyappAfterNotify($orderInfo, $notify_result) === false) {
				//return Message::warning($payment->errors);
				return $payment->verifyResult(false);
			}
		}
		
		return $payment->verifyResult(true);
	}
}