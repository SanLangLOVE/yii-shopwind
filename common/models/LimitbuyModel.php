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

use common\models\GoodsModel;
use common\models\GoodsSpecModel;
use common\models\OrderGoodsModel;

use common\library\Basewind;
use common\library\Timezone;
use common\library\Language;
use common\library\Page;

/**
 * @Id LimitbuyModel.php 2018.5.21 $
 * @author mosir
 */

class LimitbuyModel extends ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%limitbuy}}';
	}

	// 关联表
	public function getGoods()
	{
		return parent::hasOne(GoodsModel::className(), ['goods_id' => 'goods_id']);
	}
	// 关联表
	public function getStore()
	{
		return parent::hasOne(StoreModel::className(), ['store_id' => 'store_id']);
	}

	public static function getList($appid, $store_id = 0, $params = array(), &$pagination = false)
	{
		$query = parent::find()->alias('lb')->select('lb.id,lb.goods_id,lb.store_id,lb.title,lb.rules,lb.start_time,lb.end_time,lb.image,g.goods_name,g.default_image,g.price,g.default_spec')->joinWith('goods g', false)->where(['g.if_show' => 1, 'g.closed' => 0]);
		if ($store_id > 0) $query->andWhere(['lb.store_id' => $store_id]);
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
		$limitbuys = $query->orderBy(['id' => SORT_DESC])->asArray()->all();

		foreach ($limitbuys as $key => $limitbuy) {
			$limitbuys[$key]['rules'] = unserialize($limitbuy['rules']);

			list($proPrice) = self::getItemProPrice($limitbuy['goods_id'], $limitbuy['default_spec'], true);
			if ($proPrice !== false) $limitbuys[$key]['pro_price'] = $proPrice;

			if ($limitbuy['image']) {
				$limitbuys[$key]['default_image'] = $limitbuy['image'];
			} else {
				$limitbuy['default_image'] || $limitbuys[$key]['default_image'] = Yii::$app->params['default_goods_image'];
			}

			// 判断状态
			$limitbuys[$key]['status'] = self::getLimitbuyStatus($limitbuy, true);
			$limitbuys[$key]['status_label'] = Language::get($limitbuys[$key]['status']);
		}
		return $limitbuys;
	}

	/**
	 * 获取促销价格
	 * @param boolean $force 强验证，验证促销价格时间
	 */
	public static function getItemProPrice($goods_id, $spec_id = 0, $force = true)
	{
		$proPrice = false;

		if (!$spec_id) {
			return array(false, 0);
		}

		if (!($spec = GoodsSpecModel::find()->select('price,goods_id')->where(['spec_id' => $spec_id])->one())) {
			return array(false, 0);
		}

		// 该处可以处理不传[goods_id]的情形
		if (!$goods_id) $goods_id = $spec->goods_id;

		$query = parent::find()->select('id,rules')->where(['goods_id' => $goods_id])->orderBy(['id' => SORT_DESC]);

		if ($force) {
			$query->andWhere(['and', ['<=', 'start_time', Timezone::gmtime()], ['>=', 'end_time', Timezone::gmtime()]]);
		}

		if (($limitbuy = $query->one())) {
			$specPrice = unserialize($limitbuy->rules);
			if (isset($specPrice[$spec_id])) {
				if ($specPrice[$spec_id]['pro_type'] == 'price') {
					$proPrice = round($spec->price - floatval($specPrice[$spec_id]['price']), 2);
					if ($proPrice < 0) {
						$force && $proPrice = 0;
					}
				} else $proPrice = round($spec->price * floatval($specPrice[$spec_id]['price']) / 1000, 4) * 100;
			} else {
				$proPrice = $spec->price;
			}
		}

		return array($proPrice, $limitbuy ? $limitbuy->id : 0);
	}

	/* 判断促销状态 */
	public static function getLimitbuyStatus($data, $checkPrice = false)
	{
		$status = '';

		if (is_array($data)) $limitbuy = Basewind::trimAll($data, true);
		else $limitbuy = parent::find()->select('goods_id,start_time,end_time,rules')->where(['id' => intval($data)])->one();
		// data = id

		if ($limitbuy->end_time < Timezone::gmtime()) {
			$status = 'ended';
		} elseif ($limitbuy->start_time > Timezone::gmtime()) {
			$status = 'waiting';
		} elseif ($limitbuy->start_time < Timezone::gmtime() && $limitbuy->end_time > Timezone::gmtime()) {
			$status = 'going';
		}

		// 此为预留接口，仅用户中心用，如果为true的话，促销挂件中按数量搜索商品则不太好处理，会导致促销挂件中的商品有可能不是促销商品
		// 因为促销挂件中的商品无法判断价格是否合理
		if ($checkPrice === true) {
			// 判断价格是否合理（因为设置促销后，卖家有可能再次去修改了价格，导致价格为负数的情况，这个时候就要设置促销商品状态为失效）
			$specs = GoodsSpecModel::find()->select('spec_id,price')->where(['goods_id' => $limitbuy->goods_id])->all();
			$specPrice = !is_array($limitbuy->rules) ? unserialize($limitbuy->rules) : $limitbuy->rules;

			if (count($specPrice) != count($specs)) {
				$status = 'price_invalid';
			} else {
				foreach ($specs as $spec) {
					if (($specPrice[$spec->spec_id]['pro_type'] == 'price') && ($specPrice[$spec->spec_id]['price'] > $spec->price)) {
						$status = 'price_invalid';
						break;
					}
				}
			}
		}
		return $status;
	}

	/**
	 * 获取指定秒杀活动的销量进度
	 */
	public static function getSpeedOfProgress($id = 0, $goods_id = 0, $percentage = false)
	{
		$progress = 1;

		if (!$id || !($model = self::find()->select('start_time,end_time')->where(['id' => $id])->one())) {
			return false;
		}

		$list = OrderGoodsModel::find()->alias('og')->select('og.quantity')
			->joinWith('order o', false)
			->where(['and', ['>=', 'o.pay_time', $model->start_time], ['<=', 'o.pay_time', $model->end_time], ['og.goods_id' => $goods_id]])
			->asArray()->all();

		// 指定时间段已售出件数
		$sells = 0;
		foreach ($list as $key => $value) {
			$sells += $value['quantity'];
		}

		// 现有库存
		$stocks = GoodsModel::getStocks($goods_id);
		if (($total = $sells + $stocks) > 0) {
			$progress = round($sells / $total, 2);
		}
		if ($progress > 1) {
			$progress = 1;
		}

		return ['sales' => $sells, 'stocks' => $stocks,  'progress' => $percentage ? $progress * 100 . '%' : $progress];
	}
}
