<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\library;

use yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

use common\models\OrderModel;
use common\models\OrderGoodsModel;
use common\models\OrderLogModel;
use common\models\RefundModel;
use common\models\DepositTradeModel;
use common\models\DistributeModel;
use common\models\IntegralModel;
use common\models\GoodsStatisticsModel;
use common\models\TeambuyLogModel;
use common\models\GuideshopModel;

use common\library\Language;
use common\library\Timezone;
use common\library\Def;

/**
 * @Id Taskqueue.php 2018.3.2 $
 * @author mosir
 */
 
class Taskqueue
{
	public static function run()
	{
		self::autoConfirm();
		self::autoClosed();

		// 针对拼团订单
		self::autoTeambuy();

		// 针对社区团购订单
		self::autoConfirm('guidebuy');
	}
	
	/**
	 * 到期未付款，自动关闭订单 
	 */
	private static function autoClosed()
	{
		$today = Timezone::gmtime();
		
        // 默认2天
		$closeday = intval(Yii::$app->params['autocloseday']);
        $interval = ($closeday > 0 ? $closeday : 2) * 24 * 3600;
		
		// 每次仅处理2条记录，注意：处理太多会影响性能
		$list = OrderModel::find()->where("add_time + {$interval} < {$today}")->andWhere(['in', 'status', [Def::ORDER_SUBMITTED, Def::ORDER_PENDING]])->indexBy('order_id')->orderBy(['order_id' => SORT_ASC])->limit(2)->asArray()->all();
		
		foreach($list as $orderInfo)
		{
			// 修改订单状态
			OrderModel::updateAll(['status' => Def::ORDER_CANCELED], ['order_id' => $orderInfo['order_id']]);
				
			// 修改交易状态
			DepositTradeModel::updateAll(['status' => 'CLOSED', 'end_time' => Timezone::gmtime()], ['bizIdentity' => Def::TRADE_ORDER, 'bizOrderId' => $orderInfo['order_sn'], 'buyer_id' => $orderInfo['buyer_id']]);
				
			// 订单取消后，归还买家之前被预扣积分 
			IntegralModel::returnIntegral($orderInfo);
				
  			// 加回商品库存
			OrderModel::changeStock('+', $orderInfo['order_id']);
				
			// 记录订单操作日志
			$model = new OrderLogModel();
			$model->order_id = $orderInfo['order_id'];
			$model->operator = Language::get('system');
			$model->order_status = Def::getOrderStatus($orderInfo['status']);
			$model->changed_status = Def::getOrderStatus(Def::ORDER_CANCELED);
			$model->remark = '';
			$model->log_time = Timezone::gmtime();
			$model->save();
		}
	}
	
	/**
	 * 自动确认收货
	 */
	private static function autoConfirm($otype = 'normal')
	{
		// 排除退款中的订单
		$query = OrderModel::find()->alias('o')->where(['>', 'order_id', 0])->joinWith(['depositTrade' => function($model) {
			$model->alias('dt')->select('dt.tradeNo,dt.buyer_id,dt.bizOrderId,r.status as refund_status')->joinWith('refund r', false)->where(['in', 'r.status',['CLOSED', null, '']]);
		}]);
		$query = self::getConditions($query, $otype);

		// 每次仅处理2条记录，注意：处理太多会影响性能
		$list = $query->orderBy(['order_id' => SORT_ASC])->indexBy('order_id')->limit(2)->asArray()->all();
		foreach($list as $orderInfo)
		{
			// 交易信息 
			if(!($tradeInfo = DepositTradeModel::find()->select('tradeNo')->where(['bizIdentity' => Def::TRADE_ORDER, 'bizOrderId' => $orderInfo['order_sn']])->asArray()->one())) {
				continue;
			}
			
			// 如果订单中的商品为空，则认为订单信息不完整，不执行
			$ordergoods = OrderGoodsModel::find()->where(['order_id' => $orderInfo['order_id']])->asArray()->all();
			if(empty($ordergoods)) {
				continue;
			}
	
			// 更新订单状态 
			$model = OrderModel::findOne($orderInfo['order_id']);
			$model->status = Def::ORDER_FINISHED;
			$model->finished_time = Timezone::gmtime();
			if(!$model->save()) {
				continue;
			}
				
			// 转到对应的业务实例，不同的业务实例用不同的文件处理，如购物，卖出商品，充值，提现等，每个业务实例又继承支出或者收入 
			$depopay_type    = \common\library\Business::getInstance('depopay')->build('sellgoods');
				
			$result = $depopay_type->submit(array(
				'trade_info' => array('userid' => $orderInfo['seller_id'], 'party_id' => $orderInfo['buyer_id'], 'amount' => $orderInfo['order_amount']),
				'extra_info' => $orderInfo + array('tradeNo' => $tradeInfo['tradeNo'])
			));
				
			if($result !== true) {
				continue;
			}
			
			// 确认收货后，即交易完成，处理订单商品三级返佣 
			DistributeModel::distributeInvite($orderInfo);

			// 如果是社区团购订单，给团长分成
			GuideshopModel::distributeProfit($orderInfo);
				
			// 确认收货后，即交易完成，将订单积分表中的积分进行派发
			IntegralModel::distributeIntegral($orderInfo);
			
			// 更新累计销售件数 
			foreach ($ordergoods as $key => $goods) {
				GoodsStatisticsModel::updateAllCounters(['sales' => $goods['quantity']], ['goods_id' => $goods['goods_id']]);
			}
				
			// 将确认的商品状态设置为 交易完成 
			OrderGoodsModel::updateAll(['status' => 'SUCCESS'], ['order_id' => $orderInfo['order_id']]);
				
			// 记录订单操作日志 
			$model = new OrderLogModel();
			$model->order_id = $orderInfo['order_id'];
			$model->operator = Language::get('system');
			$model->order_status = Def::getOrderStatus($orderInfo['status']);
			$model->changed_status = Def::getOrderStatus(Def::ORDER_FINISHED);
			$model->remark = '';
			$model->log_time = Timezone::gmtime();
			$model->save();
		}
	}

	/**
	 * 到期未付款，自动删除拼团记录
	 * 到期已支付未成团，设为自动成团
	 */
	private static function autoTeambuy()
	{
		$today = Timezone::gmtime();

		// 到期未付款，自动删除拼团记录
		TeambuyLogModel::deleteAll(['and', ['<=', 'expired', $today], ['pay_time' => 0]]);

		// 已付款，到期未成团的订单，设置为自动成团（待发货）
		$list = TeambuyLogModel::find()->select('logid,order_id')->where(['and', ['<=', 'expired', $today], ['>', 'pay_time', 0], ['status' => 0]])->all();
		foreach($list as $model) {
			$model->status = 1;
			if($model->save()) {
				// 从待成团状态设置为待发货状态
				OrderModel::updateAll(['status' => Def::ORDER_ACCEPTED], ['status' => Def::ORDER_TEAMING, 'order_id' => $model->order_id]);
			}
		}
	}

	/**
	 * 针对普通订单，发货后默认8天自动收货
	 * 针对社区团购订单，团长通知取货后默认2天自动完成订单
	 */
	private static function getConditions($query = null, $otype = 'normal')
	{
		$today = Timezone::gmtime();
		$shipday = intval(Yii::$app->params['autoshipday']);
		$deliveryday = intval(Yii::$app->params['autodeliveryday']);

		$interval = ($otype == 'guidebuy') ? ($deliveryday > 0 ? $deliveryday : 2) * 24 * 3600 : ($shipday > 0 ? $shipday : 8) * 24 * 3600;
		$status = ($otype == 'guidebuy') ? Def::ORDER_DELIVERED : Def::ORDER_SHIPPED;
		$query->andWhere("ship_time + {$interval} < {$today}")->andWhere(['o.status' => $status]);

		return $query;
	}
}