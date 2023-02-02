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

use common\models\TeambuyModel;
use common\models\TeambuyLogModel;
use common\models\GoodsSpecModel;

use common\library\Timezone;
use common\library\Page;

/**
 * @Id TeambuyOrder.php 2019.7.12 $
 * @author mosir
 */
 
class TeambuyOrder extends NormalOrder
{
	protected $otype = 'teambuy';
	
	/** 
	 * 提交生成的订单
	 * @return array $result 包含店铺和订单号的数组
	 */
	public function submit($data = array())
	{
		if(($result = parent::submit($data)) === false) {
			return false;
		}

		extract($data);

		// 插入拼团订单信息（实际上有且只有一个拼团订单，不支持同时提交多个拼团订单，在合并付款才会可能有2笔拼团订单一起付款）
		foreach ($result as $store_id => $order_id) {
			$this->insertTeambuyInfo($order_id, $base_info[$store_id], $goods_info['orderList'][$store_id]);
		}

		return $result;
	}

	/**
	 * 获取拼团商品数据 
	 */
	public function getOrderGoodsList()
	{
		$result = array();

		if(!($spec_id = $this->post->extraParams->spec_id)) {
			return false;
		}

		if(($goods = GoodsSpecModel::find()->alias('gs')->select('gs.spec_id,gs.price,gs.spec_1,gs.spec_2,gs.stock,gs.image,g.goods_id,g.store_id,g.goods_name,g.default_image as goods_image,g.spec_name_1,g.spec_name_2')->where(['spec_id' => $spec_id, 'if_show' => 1, 'closed' => 0])->joinWith('goods g', false)->asArray()->one())) 
		{
			$teambuy = TeambuyModel::find()->select('id,specs')->where(['goods_id' => $goods['goods_id'], 'status' => 1])->one();
			if (!$teambuy || !($specs = unserialize($teambuy->specs)) || !isset($specs[$spec_id])) {
				return false;
			}

			$goods['quantity'] = $this->post->extraParams->quantity > 0 ? $this->post->extraParams->quantity : 1;
			$goods['price'] = round($goods['price'] * $specs[$spec_id]['price'] / 1000, 4) * 100;
			!empty($goods['spec_1']) && $goods['specification'] = $goods['spec_name_1'] . ':' . $goods['spec_1'];	
			!empty($goods['spec_2']) && $goods['specification'] .= ' ' . $goods['spec_name_2'] . ':' . $goods['spec_2']; 
				
			// 兼容规格图片功能
			if(isset($goods['image']) && $goods['image']) {
				$goods['goods_image'] = $goods['image'];
				unset($goods['image']);
			}
			$goods['goods_image'] = Page::urlFormat($goods['goods_image'], Yii::$app->params['default_goods_image']);
				
			$result[] = $goods;
		}

		return array($result, $teambuy);
	}

	/**
	 * 插入拼团订单信息
	 * @param string $teamid 为参团成员关联ID
	 */
	private function insertTeambuyInfo($order_id, $order_info, $list = array())
	{
		$checkTeamid = $this->checkTeamid();

		// 默认24小时未成团的设为过期（过期后由后续逻辑来处理是自动成团还是关闭）
		$groupday = intval(Yii::$app->params['autogroupday']);
        $interval = ($groupday > 0 ? $groupday : 1) * 24 * 3600;

		// 实际上拼团订单只有一个商品
		foreach($list['items'] as $value) 
		{
			if(($teambuy = TeambuyModel::find()->select('id,people')->where(['goods_id' => $value['goods_id'], 'status' => 1])->one())) {
				$model = new TeambuyLogModel();
				$model->tbid = $teambuy->id;
				$model->goods_id = $value['goods_id'];
				$model->order_id = $order_id;
				$model->userid = Yii::$app->user->id;
				$model->status = 0; // 未成团
				$model->people = $teambuy->people; // 保留该值，即便拼团活动关闭了也不受影响
				$model->created = Timezone::gmtime();
				$model->expired = $model->created + $interval;
				$model->leader = $checkTeamid ? 0 : 1; // 0 = 参团; 1=开团
				$model->teamid = $checkTeamid ? $this->post->extraParams->teamid : $this->getTeamid();
				$model->save();
			}
		}
	}

	/**
	 * 检查参团ID是否还可用
	 */
	private function checkTeamid()
	{
		// 如果是发起拼单
		if(empty($this->post->extraParams) || !isset($this->post->extraParams->teamid) || empty($this->post->extraParams->teamid)) {
			return false;
		}

		// 如果已成团
		if(TeambuyLogModel::find()->where(['teamid' => $this->post->extraParams->teamid, 'status' => 1])->exists()) {
			return false;
		}
		return true;
	}

	/**
	 * 生成不重复的参团ID
	 */
	private function getTeamid() {
		return Timezone::gmtime() . mt_rand(100, 999);
	}
}