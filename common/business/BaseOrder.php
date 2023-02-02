<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\business;

use yii;
use yii\helpers\ArrayHelper;

use common\models\OrderModel;
use common\models\OrderGoodsModel;
use common\models\OrderExtmModel;
use common\models\CartModel;
use common\models\GoodsModel;
use common\models\GoodsStatisticsModel;
use common\models\AddressModel;
use common\models\DeliveryTemplateModel;
use common\models\CouponModel;
use common\models\CouponsnModel;
use common\models\IntegralModel;
use common\models\OrderIntegralModel;
use common\models\DistributeSettingModel;

use common\library\Basewind;
use common\library\Def;
use common\library\Timezone;
use common\library\Language;
use common\library\Promotool;

/**
 * @Id BaseOrder.php 2018.7.12 $
 * @author mosir
 */

class BaseOrder
{
	/**
	 * 订单类型
	 * @var string $otype
	 */
	protected $otype = '';

	/**
	 * 页面提交参数
	 * @var object $post
	 */
	public $post = null;

	/**
	 * 其他额外参数
	 * @var array $params
	 */
	public $params = array();

	/**
	 * 错误捕捉
	 * @var object $errors
	 */
	public $errors = null;

	public function __construct($otype, $post = null, $params = [])
	{
		$this->otype 	= $otype;
		$this->post 	= $post;
		$this->params 	= $params;
	}

	/**
	 * 提交订单信息 
	 */
	public function submit($data = [])
	{
		return 0;
	}

	/**
	 * 插入订单表数据 
	 */
	public function insertOrder($order_info = [])
	{
		$model = new OrderModel();
		foreach ($order_info as $key => $value) {
			$model->$key = $value;
		}
		return $model->save() ? $model->order_id : 0;
	}

	/**
	 * 插入商品信息 
	 */
	public function insertOrderGoods($order_id = 0, $list = [])
	{
		// 是否有邀请成交的商品
		$list = DistributeSettingModel::orderInvite($list);

		foreach ($list['items'] as $value) {
			$model = new OrderGoodsModel();
			$model->order_id = $order_id;
			$model->goods_id = $value['goods_id'];
			$model->goods_name = $value['goods_name'];
			$model->spec_id = $value['spec_id'];
			$model->specification = $value['specification'] ? $value['specification'] : '';
			$model->price = $value['price'];
			$model->quantity = $value['quantity'];
			$model->goods_image = $value['goods_image'] ? $value['goods_image'] : '';

			if (!empty($value['invite'])) {
				$model->inviteType = $value['invite']['type'];
				$model->inviteRatio = serialize($value['invite']['ratio']);
				$model->inviteUid  = $value['invite']['uid'];
			}
			$model->save();
		}
		return true;
	}

	/**
	 * 插入收货人信息 
	 */
	public function insertOrderExtm($order_id = 0, $consignee_info  = [])
	{
		$model = new OrderExtmModel();

		$model->order_id = $order_id;
		foreach ($consignee_info as $key => $value) {
			$model->$key = $value;
		}
		return $model->save();
	}

	/**
	 * 插入订单相关信息
	 * @return int $insertFail 插入失败数
	 * @return array $result 包含店铺和订单号的数组
	 */
	public function insertOrderData($base_info = [], $goods_info = [], $consignee_info = [])
	{
		$insertFail = 0;
		$result = [];
		foreach ($base_info as $store_id => $store) {
			if (!($order_id = $this->insertOrder($base_info[$store_id]))) {
				$insertFail++;
				$this->errors = Language::get('create_order_failed');
				break;
			}
			if (!($this->insertOrderGoods($order_id, $goods_info['orderList'][$store_id]))) {
				$insertFail++;
				$this->errors = Language::get('create_order_failed');
				break;
			}
			if (!($this->insertOrderExtm($order_id, $consignee_info[$store_id]))) {
				$insertFail++;
				$this->errors = Language::get('create_order_failed');
				break;
			}
			$result[$store_id] = $order_id;
		}

		// 考虑合并付款的情况下（优惠，积分抵扣等问题），必须保证本批次的所有订单都插入成功，要不都删除本次订单
		if ($insertFail > 0) {
			$this->deleteOrderData($result);
			return false;
		}

		return $result;
	}

	/**
	 * 插入合并付款提交订单过程中，如有插入失败的订单，则删除批次所有订单
	 */
	public function deleteOrderData($result = [])
	{
		foreach ($result as $order_id) {
			if (!$order_id) continue;
			OrderModel::deleteAll(['order_id' => $order_id]);
			OrderGoodsModel::deleteAll(['order_id' => $order_id]);
			OrderExtmModel::deleteAll(['order_id' => $order_id]);
		}
	}

	/**
	 * 处理订单基本信息，返回有效的订单信息数组 
	 */
	public function handleOrderInfo($goods_info = [])
	{
		// 返回基本信息
		$result = [];

		$post = ArrayHelper::toArray($this->post);
		foreach ($goods_info['orderList'] as $store_id => $order) {
			$result[$store_id] = array(
				'order_sn'      =>  OrderModel::genOrderSn($store_id),
				'otype'         =>  $goods_info['otype'],
				'gtype'    		=>  $goods_info['gtype'],
				'seller_id'    	=>  $order['store_id'],
				'seller_name'   =>  addslashes($order['store_name']),
				'buyer_id'      =>  Yii::$app->user->id,
				'buyer_name'    =>  addslashes(Yii::$app->user->identity->username),
				'buyer_email'   =>  Yii::$app->user->identity->email ? Yii::$app->user->identity->email : '',
				'status'       	=>  Def::ORDER_PENDING,
				'add_time'      =>  Timezone::gmtime(),
				'goods_amount'  =>  $order['amount'],
				'anonymous'     =>  isset($post['anonymous'][$store_id]) ? intval($post['anonymous'][$store_id]) : 0,
				'postscript'   	=>  trim($post['postscript'][$store_id]),
			);
		}
		if (empty($result)) {
			$this->errors = Language::get('base_info_check_fail');
			return false;
		}
		return $result;
	}

	/**
	 * 处理收货人信息，返回有效的收货人信息 
	 */
	public function handleConsigneeInfo($goods_info = [])
	{
		$result = [];

		// 验证收货人信息填写是否完整
		if (!($consignee_info = $this->validConsigneeInfo())) {
			return false;
		}
		// 验证配送方式信息填写是否完整
		if (!($delivery_info = $this->validDeliveryInfo())) {
			return false;
		}

		// 计算配送费用
		$shipping_method = $this->getOrderShippings($goods_info);
		foreach ($shipping_method as $store_id => $shipping) {
			$result[$store_id] = array(
				'consignee'     =>  $consignee_info['consignee'],
				'region_id'     =>  $consignee_info['region_id'],
				'region_name'   =>  $consignee_info['region_name'],
				'address'       =>  $consignee_info['address'],
				'zipcode'       =>  $consignee_info['zipcode'],
				'phone_tel'     =>  $consignee_info['phone_tel'],
				'phone_mob'     =>  $consignee_info['phone_mob'],
				'shipping_name' =>  addslashes(Language::get($delivery_info[$store_id])),
				'shipping_fee'  =>  $shipping[$consignee_info['region_id']][$delivery_info[$store_id]]['logistic_fees'],
			);
		}
		return $result;
	}

	/**
	 * 验证收货人信息是否合法
	 * 注意：因收货地址与运费计算有关，所以为了配合前端更好的显示订单金额，不建议在前端订单页设置表单提交收货地址
	 * 但针对社区团购订单，将把门店自提地址作为收货地址，提货人作为收货人信息保存到数据库，故做好兼容处理
	 */
	public function validConsigneeInfo()
	{
		$post = ArrayHelper::toArray($this->post);
		if (($address = $this->getAddressInfo($post['addr_id'], $post['region_id']))) {
			$post = array_merge($post, $address);
		}
		if (!$post['consignee']) {
			$this->errors = Language::get('consignee_empty');
			return false;
		}
		if (!$post['region_id']) {
			$this->errors = Language::get('region_empty');
			return false;
		}
		if (!$post['address']) {
			$this->errors = Language::get('address_empty');
			return false;
		}
		if (!$post['phone_tel'] && !$post['phone_mob']) {
			$this->errors = Language::get('phone_mob_required');
			return false;
		}

		return $post;
	}

	/**
	 * 通过收货人或地区ID获取地址信息
	 * @param int $addr_id 针对发货订单
	 * @param int $region_id 针对社区团购，买家自提模式
	 */
	public function getAddressInfo($addr_id = 0, $region_id = 0)
	{
		return [];
	}

	/**
	 * 验证配送方式是否合法
	 */
	public function validDeliveryInfo()
	{
		if (!isset($this->post->delivery_type) || !is_object($this->post->delivery_type)) {
			$this->errors = Language::get('shipping_required');
			return false;
		}
		foreach ($this->post->delivery_type as $value) {
			if (!$value || !in_array($value, ['express', 'ems', 'post'])) {
				$this->errors = Language::get('shipping_required');
				return false;
			}
		}
		return ArrayHelper::toArray($this->post->delivery_type);
	}

	/**
	 * 获取我的收货地址
	 * @param int $addr_id 只查询指定记录
	 */
	public function getMyAddress($addr_id = 0)
	{
		$query = AddressModel::find()->where(['userid' => Yii::$app->user->id])->orderBy(['defaddr' => SORT_DESC])->indexBy('addr_id');
		if ($addr_id) {
			return $query->andWhere(['addr_id' => intval($addr_id)])->asArray()->one();
		}
		return $query->asArray()->all();
	}

	/**
	 * 获取本次订单的各个店铺的可用优惠券 
	 */
	public function getStoreCouponList($goods_info = [])
	{
		foreach ($goods_info['orderList'] as $store_id => $order) {
			if ($order['allow_coupon']) {
				$goods_info['orderList'][$store_id]['coupon_list'] = CouponModel::getAvailableByOrder($order);
			}
		}
		return $goods_info;
	}

	/**
	 * 取得有效的订单折扣信息，如积分抵扣，店铺优惠券的合理性，返回各个优惠减少的金额 
	 */
	public function getAllDiscountByPost($goods_info = [])
	{
		$result = $discount_info = [];

		// 验证买家使用多少积分抵扣货款的有效性
		if (isset($goods_info['allow_integral']) && $goods_info['allow_integral']) {
			$result = $goods_info['integralExchange'];

			!isset($this->post->exchange_integral) && $this->post->exchange_integral = 0;
			if ($this->post->exchange_integral > $result['maxPoints']) {
				$this->errors = Language::get('order_can_use_max_integral') . $result['maxPoints'];
				return false;
			} elseif ($this->post->exchange_integral > 0) {
				$discount_info['integral'] = array(
					'amount' 		=> round($this->post->exchange_integral * $result['rate'], 2),
					'points' 		=> $this->post->exchange_integral,
					'orderIntegral' => $result['orderIntegral']
				);
			}
		}

		// 验证买家使用的优惠券的有效性
		$goods_info = $this->getStoreCouponList($goods_info);
		$result = $goods_info['orderList'];

		if (isset($this->post->coupon_sn) && !empty($this->post->coupon_sn)) {
			foreach ($this->post->coupon_sn as $store_id => $coupon_sn) {
				if (isset($result[$store_id]['coupon_list']) && !empty($result[$store_id]['coupon_list'])) {
					foreach ($result[$store_id]['coupon_list'] as $key => $value) {
						if ($coupon_sn == $value['coupon_sn']) {
							$discount_info['coupon'][$store_id] = array('coupon_value' => $value['coupon_value'], 'coupon_sn' => $coupon_sn);
							break;
						}
					}
				}
			}
		}

		foreach ($goods_info['storeIds'] as $store_id) {
			$promotool = Promotool::getInstance()->build(['store_id' => $store_id]);

			// 处理满折满减信息
			if ($fullprefer = $promotool->getOrderFullPreferInfo($goods_info['orderList'][$store_id])) {
				$discount_info['fullprefer'][$store_id] = array('amount' => $fullprefer['price']);
			}
		}

		return $discount_info;
	}

	/**
	 * 检验折扣信息和订单总价的合理性 
	 */
	public function checkAllDiscountForOrderAmount(&$base_info, &$discount_info, $consignee_info, $integralExchangeRate = 0)
	{
		$amount = 0;
		foreach ($base_info as $store_id => $order_info) {
			// 商品总价
			$goodsAmount 	= $order_info['goods_amount'];
			// 包含运费的订单总价
			$storeAmount 	= $order_info['goods_amount'] + $consignee_info[$store_id]['shipping_fee'];

			$couponDiscount = $fullpreferDiscount = 0;

			// 每个订单的店铺优惠券优惠
			if (isset($discount_info['coupon'][$store_id]['coupon_value'])) {
				$couponDiscount = $discount_info['coupon'][$store_id]['coupon_value'];
				if ($couponDiscount > 0) {
					// 如果优惠折扣大于订单总价
					if ($couponDiscount > $storeAmount) {
						$this->errors = Language::get('discount_gt_storeAmount');
						return false;
					}

					$storeAmount -= $couponDiscount;
				}
			}
			// 每个订单的满折满减优惠
			if (isset($discount_info['fullprefer'][$store_id]['amount'])) {
				$fullpreferDiscount 	= $discount_info['fullprefer'][$store_id]['amount'];
				if ($fullpreferDiscount > 0) {
					// 如果优惠折扣大于订单总价
					if ($fullpreferDiscount > $storeAmount) {
						$this->errors = Language::get('discount_gt_storeAmount');
						return false;
					}

					$storeAmount -= $fullpreferDiscount;
				}
			}

			// 返回的数据
			$base_info[$store_id]['order_amount'] 	= $storeAmount;
			$base_info[$store_id]['goods_amount']   = $goodsAmount;
			$base_info[$store_id]['discount']		= $couponDiscount + $fullpreferDiscount;

			// 所有订单实际支付的金额汇总（未使用积分前）
			$amount	+= $storeAmount;
		}

		// 情况一：所有订单减去折扣之后的总额为零，那么说明已经不能再使用积分来抵扣了
		// 情况二：所有订单减去折扣之后的总额不为零，则判断积分抵扣的金额是否合理（如使用积分抵扣后订单总额为负，则不合理）
		if (isset($discount_info['integral']['amount']) && ($discount_info['integral']['amount'] > 0)) {
			if (($amount <= 0) || ($discount_info['integral']['amount'] > $amount)) {
				$this->errors = Language::get('integral_gt_amount');
				return false;
			}
		}

		// 至此说明所使用的积分抵扣值是合理的（不大于订单总价了，或者本次订单没有使用积分来抵扣，如果使用了积分抵扣，还要继续判断哪个订单使用了多少积分来抵扣，用分摊来计算）
		if (($amount > 0) && (isset($discount_info['integral']['amount']) && ($discount_info['integral']['amount'] > 0))) {
			foreach ($base_info as $store_id => $order_info) {
				$rate = $discount_info['integral']['orderIntegral']['items'][$store_id] / $discount_info['integral']['orderIntegral']['totalPoints'];
				$sharePoints = round($rate * $discount_info['integral']['points'], 2);
				$shareAmount = round($rate * $discount_info['integral']['points'] * $integralExchangeRate, 2);

				// 在这里已经不用判断各个订单分摊的积分，是否抵扣完订单总价甚至抵扣为负值的情况了，因为最多能抵扣完， 不会出现负值的情况
				$discount_info['integral']['shareIntegral'][$store_id] = array('amount' => $shareAmount, 'points' => $sharePoints);

				// 返回的数据
				$base_info[$store_id]['order_amount'] 	-= $shareAmount;
				$base_info[$store_id]['discount']		+= $shareAmount;
			}
		}

		return true;
	}

	/** 
	 * 获取本次订单的运费资费（多个店铺）
	 */
	public function getOrderShippings($goods_info = [])
	{
		$shipping_methods = [];

		// 根据goods_info找出所有店铺每个商品的运费模板id
		$base_deliverys = [];
		foreach ($goods_info['orderList'] as $store_id => $order) {
			foreach ($order['items'] as $goods) {
				$template_id = GoodsModel::find()->select('dt_id')->where(['goods_id' => $goods['goods_id']])->scalar();

				// 如果商品的运费模板id为0，即未设置运费模板，则获取店铺默认的运费模板（取第一个）
				if (!$template_id || !($delivery = DeliveryTemplateModel::find()->where(['template_id' => $template_id])->asArray()->one())) {
					$delivery = DeliveryTemplateModel::find()->where(['store_id' => $store_id])->orderBy(['template_id' => SORT_ASC])->asArray()->one();
					// 如果店铺也没有默认的运费模板
					if (empty($delivery)) {
						$delivery = DeliveryTemplateModel::addFirstTemplate();
					}
				}
				$base_deliverys[$store_id][$goods['goods_id']] = $delivery;
			}
		}

		// 获取配送目的地, 并计算每个目的地的运费
		$deliverytos = $this->getDeliveryTos();
		foreach ($deliverytos as $key => $region_id) {
			foreach ($base_deliverys as $store_id => $goods_deliverys) {
				// 相同配送地址的已经查过，无需再查询
				if (isset($shipping_methods[$store_id][$region_id])) {
					continue;
				}

				$deliverys = array();
				foreach ($goods_deliverys as $key => $delivery) {
					$deliverys[$key] = DeliveryTemplateModel::getCityLogistic($delivery, $region_id);
				}

				// 一、如果每个商品可用的运送方式都一致，则统一计算；二、 如果有一个商品的运送方式不同，则进行组合计算
				// 注：目前已经强制每个运费模板都必须设置三个运送方式，所以不存在不全等的情况。 

				// 1. 分别计算每个运送方式的费用：找出首费最大的那个运费方式，作为首费，并且找出作为首费的那个商品id，便于在统计运费总额时，该商品使用首费，其他商品使用续费计算
				$merge_info = array(
					'express' => array('start_fees' => 0, 'goods_id' => 0),
					'ems'     => array('start_fees' => 0, 'goods_id' => 0),
					'post'    => array('start_fees' => 0, 'goods_id' => 0),
				);
				foreach ($deliverys as $goods_id	=> $delivery) {
					foreach ($delivery as $template_types) {
						if ($merge_info[$template_types['type']]['start_fees'] <= $template_types['start_fees']) {
							$merge_info[$template_types['type']]['start_fees'] = $template_types['start_fees'];
							$merge_info[$template_types['type']]['goods_id'] = $goods_id;
						}
					}
				}

				// 2. 计算每个订单（店铺）的商品的总件数（包括不同规格）和每个商品的总件数（包括不同规格），以下会用到总件数来计算运费
				$total_quantity = 0;
				$quantity = [];
				foreach ($goods_info['orderList'][$store_id]['items'] as $goods) {
					!isset($quantity[$goods['goods_id']]) && $quantity[$goods['goods_id']] = 0;
					$quantity[$goods['goods_id']] += $goods['quantity'];
					$total_quantity += $goods['quantity'];
				}
				// 3. 计算总运费
				$logistic = [];
				foreach ($deliverys as $goods_id => $delivery) {
					foreach ($delivery as $template_types) {
						$goods_fees = 0;
						if ($goods_id == $merge_info[$template_types['type']]['goods_id']) {
							if ($total_quantity > $template_types['start_standards'] && $template_types['add_standards'] > 0) {
								if ($quantity[$goods_id] > $template_types['start_standards']) {
									$goods_fees = $merge_info[$template_types['type']]['start_fees'] + ($quantity[$goods_id] - $template_types['start_standards']) / $template_types['add_standards'] * $template_types['add_fees'];
								} else {
									$goods_fees = $merge_info[$template_types['type']]['start_fees'];
								}
							} else {
								$goods_fees = $merge_info[$template_types['type']]['start_fees'];
							}
						} else {
							if ($template_types['add_standards'] > 0) {
								$goods_fees = $quantity[$goods_id] / $template_types['add_standards'] * $template_types['add_fees'];
							} else {
								$goods_fees = $template_types['add_fees'];
							}
						}

						!isset($logistic[$template_types['type']]['logistic_fees']) && $logistic[$template_types['type']]['logistic_fees'] = 0;
						$logistic[$template_types['type']]['logistic_fees'] += round($goods_fees, 2);
						$logistic[$template_types['type']] += $template_types;
					}
				}

				// 检查是否满足满包邮条件
				if (($result = Promotool::getInstance()->build(['store_id' => $store_id])->getOrderFullfree($goods_info['orderList'][$store_id])) !== false) {
					foreach ($logistic as $k => $v) {
						$logistic[$k]['logistic_fees'] = 0;
						$logistic[$k]['name'] = $v['name'] . '(' . $result['title'] . ')';
					}
				}

				$shipping_methods[$store_id][$region_id] = $logistic;
			}
		}

		// 返回本次订单所有地址的运费资费
		return $shipping_methods;
	}

	/**
	 * 更新优惠券的使用次数 
	 */
	public function updateCouponRemainTimes($result = [], $coupon = [])
	{
		foreach ($result as $store_id => $order_id) {
			if (isset($coupon[$store_id]['coupon_sn'])) {
				$query = CouponsnModel::find()->where(['coupon_sn' => $coupon[$store_id]['coupon_sn']])->one();
				if ($query->remain_times > 0) {
					$query->updateCounters(['remain_times' => -1]);
				}
			}
		}
	}

	/**
	 * 保存订单使用的积分数额 
	 */
	public function saveIntegralInfoByOrder($result = [], $integral = [])
	{
		if (!empty($result) && isset($integral['points']) && ($integral['points'] > 0)) {
			foreach ($result as $store_id => $order_id) {
				if ($integral['shareIntegral'][$store_id]['points'] > 0) {
					$order = OrderModel::find()->select('order_sn, buyer_id')->where(['order_id' => $order_id])->one();

					// 扣减操作
					IntegralModel::updateIntegral([
						'userid' 	=> $order->buyer_id,
						'type'    	=> 'buying_pay_integral',
						'order_id'	=> $order_id,
						'order_sn'	=> $order->order_sn,
						'amount'  	=> $integral['shareIntegral'][$store_id]['points'],
						'state'   	=> 'frozen',
						'flow'    	=> 'minus'
					]);

					// 积分扣减
					$model = new OrderIntegralModel();
					$model->order_id = $order_id;
					$model->buyer_id = $order->buyer_id;

					// 买家抵价的积分，该积分会在交易完成后付给卖家，从买家账户中扣除
					$model->frozen_integral = $integral['shareIntegral'][$store_id]['points'];
					$model->save();
				}
			}
		}
	}

	/**
	 * 下单完成后的操作，如清空购物车，更新库存等
	 */
	public function afterInsertOrder($order_id, $store_id, $list, $sendNotify = true)
	{
		// 订单下完后清空指定购物车
		if (in_array($this->otype, ['normal'])) {
			CartModel::deleteAll(['userid' => Yii::$app->user->id, 'selected' => 1, 'store_id' => $store_id]);
		}

		// 减去商品库存
		OrderModel::changeStock('-', $order_id);

		// 更新下单次数（非销量）
		foreach ($list['items'] as $goods) {
			GoodsStatisticsModel::updateStatistics($goods['goods_id'], 'orders');
		}

		if ($sendNotify === true) {
			$orderInfo = OrderModel::find()->where(['order_id' => $order_id])->asArray()->one();

			// 邮件提醒： 买家已下单通知自己 
			Basewind::sendMailMsgNotify($orderInfo, array('key' => 'tobuyer_new_order_notify', 'receiver' => Yii::$app->user->id));

			// 短信和邮件提醒： 买家已下单通知卖家 
			Basewind::sendMailMsgNotify(
				$orderInfo,
				array(
					'key' => 'toseller_new_order_notify',
				),
				array(
					'key' => 'toseller_new_order_notify',
				)
			);
		}
	}

	public function getDeliveryTos()
	{
		return [];
	}
}
