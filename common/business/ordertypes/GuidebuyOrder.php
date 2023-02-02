<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */
 
namespace common\business\ordertypes;

use yii;

use common\models\OrderModel;
use common\models\GoodsSpecModel;
use common\models\RegionModel;
use common\models\GuideshopModel;

use common\library\Language;
use common\library\Page;
use common\library\Def;
use common\library\Promotool;

/**
 * @Id GuidebuyOrder.php 2020.3.12 $
 * @author mosir
 */
 
class GuidebuyOrder extends NormalOrder
{
	protected $otype = 'guidebuy';

	/**
	 * 增加自提门店地址数据，以配合运费模板功能数据初始化
	 * 即便您觉得社区团购不需要配送，也建议您渲染该数据，已达到更好的底层数据兼容效果
	 */
	public function formData(&$goods_info = array())
	{
		$result = parent::formData($goods_info);

		$record = array();
		if(isset($this->post->extraParams->shopid) && ($shopid = intval($this->post->extraParams->shopid))) {
			$record = GuideshopModel::find()->select('id,address,region_id,region_name,name,latitude,longitude')->where(['id' => $shopid, 'status' => Def::STORE_OPEN])->asArray()->one();
			if($record) {
				$record = array_merge($record, RegionModel::getArrayRegion($record['region_id'], $record['region_name']));
				unset($record['region_name']);
			}
		}

		return array_merge($result, ['my_guideshop' => $record]);
	}

	/**
	 * 获取社区团商品数据 
	 */
	public function getOrderGoodsList()
	{
		$result = array();

		if(empty($this->post->specs)) {
			return false;
		}

		$promotool = Promotool::getInstance()->build();

		// 记录选中的商品规格
		foreach($this->post->specs as $key => $value)
		{
			if(($goods = GoodsSpecModel::find()->alias('gs')->select('gs.spec_id,gs.price,gs.spec_1,gs.spec_2,gs.stock,gs.image,g.goods_id,g.store_id,g.goods_name,g.default_image as goods_image,g.spec_name_1,g.spec_name_2')->where(['spec_id' => $value->spec_id])->joinWith('goods g', false)->asArray()->one())) 
			{
				// 读取商品促销价格
				if(($promotion = $promotool->getItemProInfo($goods['goods_id'], $goods['spec_id']))) {
					$goods['price'] = $promotion['price'];
				}
				$goods['quantity'] = $value->quantity;
				!empty($goods['spec_1']) && $goods['specification'] = $goods['spec_name_1'] . ':' . $goods['spec_1'];	
				!empty($goods['spec_2']) && $goods['specification'] .= ' ' . $goods['spec_name_2'] . ':' . $goods['spec_2']; 
				
				// 兼容规格图片功能
				if(isset($goods['image']) && $goods['image']) {
					$goods['goods_image'] = $goods['image'];
					unset($goods['image']);
				}
				$goods['goods_image'] = Page::urlFormat($goods['goods_image'], Yii::$app->params['default_goods_image']);
				$result[$key] = $goods;
			}
		}

		return array($result, null);
	}

	/** 
	 * 获取本次订单的运费资费（多个店铺）
	 * 社区团购订单，统一由平台采购完成配送免运费（如果想继续采用正常配送算运费，可以不用重写该方法）
	 */
	public function getOrderShippings($goods_info = array())
	{
		$shipping_methods = parent::getOrderShippings($goods_info);

		foreach($shipping_methods as $store_id => $shippings) {
			foreach($shippings as $region_id => $logistic) {
				foreach($logistic as $key => $value) {
					$logistic[$key]['logistic_fees'] = 0;
					$logistic[$key]['name'] = Language::get($value['type']);
				}
				$shipping_methods[$store_id][$region_id] = $logistic;
			}
		}

		return $shipping_methods;
	}

	/**
	 * 通过收货人或地区ID获取地址信息
	 * @param int $addr_id 针对发货订单
	 * @param int $region_id 针对社区团购订单，买家自提模式
	 */
	public function getAddressInfo($addr_id = 0, $region_id = 0) {
		return array('region_name' => RegionModel::getRegionName(intval($region_id), true));
	}

	/**
	 * 获取我的所有配送目的地列表，用于计算每个地址的运费
	 * 对于普通订单，配送目的地为收货地址
	 * 对于社区团购订单，配送目的地为自提门店地址
	 * 注意：加载父数据，主要是考虑在前端订单提交页，可以兼容门店自提和物流配送两种配送模式所需要的数据（虽然目前前端使用了二选一的配送模式）
	 */
	public function getDeliveryTos()
	{
		// 如果在前端订单页不需要显示收货地址运费信息，可以不累加父数据
		$result = parent::getDeliveryTos();

		if(isset($this->post->extraParams->shopid) && ($shopid = intval($this->post->extraParams->shopid))) {
			$query = GuideshopModel::find()->select('region_id')->where(['id' => $shopid, 'status' => Def::STORE_OPEN])->one();
			if($query->region_id) {
				$result[] = $query->region_id;
			}
		}
		return array_values(array_unique($result));
	}

	/**
	 * 下单完成后的操作，如清空购物车，更新库存等
	 * 如果发送邮件提醒的内容不符合预期，可以在此重写发送邮件
	 */
	public function afterInsertOrder($order_id, $store_id, $list, $sendNotify = true)
	{
		parent::afterInsertOrder($order_id, $store_id, $list, $sendNotify);

		// 增加团长信息
		$query = GuideshopModel::find()->select('userid')->where(['id' => intval($this->post->extraParams->shopid)])->one();
		if($query) {
			OrderModel::updateAll(['guider_id' => $query->userid], ['order_id' => $order_id]);
		}
	}
}