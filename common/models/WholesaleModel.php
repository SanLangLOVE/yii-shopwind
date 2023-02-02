<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

use common\models\GoodsSpecModel;
use common\library\Page;

/**
 * @Id WholesaleModel.php 2021.5.12 $
 * @author mosir
 */

class WholesaleModel extends ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%wholesale}}';
	}

	// 关联表
	public function getGoods()
	{
		return parent::hasOne(GoodsModel::className(), ['goods_id' => 'goods_id']);
	}

	public static function getList($appid, $store_id = 0, $params = array(), &$pagination = false)
	{
		$query = parent::find()->alias('ws')->select('ws.id,ws.goods_id,ws.status,g.goods_name,g.default_image as goods_image,g.price')
			->joinWith('goods g', false)->where(['g.if_show' => 1, 'g.closed' => 0])
			->orderBy(['id' => SORT_DESC])
			->groupBy('goods_id');
		if ($store_id > 0) $query->andWhere(['g.store_id' => $store_id]);
		if ($params) $query->andWhere($params);

		if ($pagination !== false) {
			// 分页
			if (isset($pagination['pageSize']) && $pagination['pageSize'] > 0) {
				$page = Page::getPage($query->count(), $pagination['pageSize']);
				$query->offset($page->offset)->limit($page->limit);
				$pagination = $page;
			}
			// 指定数量
			elseif (is_numeric($pagination) && ($pagination > 0)) {
				$query->limit($pagination);
			}
		}
		$list = $query->asArray()->all();

		foreach ($list as $key => $value) {
			$value['goods_image'] || $list[$key]['goods_image'] = Yii::$app->params['default_goods_image'];

			$rules = parent::find()->select('price,quantity')->where(['goods_id' => $value['goods_id']])->orderBy(['quantity' => SORT_ASC])->asArray()->all();
			$list[$key]['rules'] = $rules ? $rules : [];
		}
		return $list;
	}

	/**
	 * 获取阶梯价格
	 */
	public static function getPrices($id = 0)
	{
		$result = [];
		$list =  parent::find()->where(['goods_id' => $id, 'status' => 1])->orderBy(['quantity' => SORT_ASC])->asArray()->all();
		foreach ($list as $key => $value) {
			$result[] = array('price' => $value['price'], 'min' => $value['quantity'], 'max' => (isset($list[$key + 1]) && $list[$key + 1]['quantity'] > 1) ? $list[$key + 1]['quantity'] - 1 : 0);
		}
		return $result;
	}

	/**
	 * 阶梯价格策略，根据购买量执行不同的单价
	 * 适用于购物车订单，其他订单类型如：社区团购订单 虽然也做了适配，当不建议使用该策略
	 */
	public static function reBuildByQuantity($list = [], $otype = 'normal')
	{
		if (empty($list) && ($otype == 'normal')) {
			$list = Yii::$app->cart->find();
		}

		if (empty($list) || !isset($list['items']) || empty($list['items'])) {
			return $list;
		}

		$result = [];
		foreach ($list['items'] as $key => $value) {

			// 对于购物车商品，没有选中的不做统计
			if ((!isset($value['selected']) || !$value['selected']) && ($otype == 'normal')) continue;

			if (!isset($result[$value['goods_id']])) {
				$result[$value['goods_id']]['quantity'] = 0;
			}
			$result[$value['goods_id']]['quantity'] += intval($value['quantity']);
		}

		// 重置价格
		foreach ($result as $key => $value) {
			$query = parent::find()->where(['and', ['goods_id' => $key, 'status' => 1], ['<=', 'quantity', $value['quantity']]])
				->orderBy(['quantity' => SORT_DESC])->one();
			if ($query) {

				$list['amount'] = 0;
				foreach ($list['items'] as $k => $v) {
					// 只要是该当前商品，不管有没有选中，都重置价格
					if ($v['goods_id'] == $key) {
						$list['items'][$k]['price'] = $query->price;
					}

					$list['items'][$k]['subtotal'] = floatval($list['items'][$k]['price'] * $v['quantity']);
				}
			}
		}

		// 重置支付金额
		$list['amount'] = 0;
		foreach ($list['items'] as $k => $v) {
			// 库存
			$list['items'][$k]['stock'] = floatval(GoodsSpecModel::find()->select('stock')->where(['spec_id' => $v['spec_id']])->scalar());

			// 统计购物车选中的商品金额
			if ((isset($v['selected']) && $v['selected']) || ($otype != 'normal')) {
				$list['amount'] += $v['subtotal'];
			}
		}

		return $list;
	}
}
