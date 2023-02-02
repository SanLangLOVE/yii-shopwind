<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace frontend\models;

use Yii;
use yii\base\Model;

use common\models\OrderModel;
use common\models\OrderGoodsModel;
use common\models\GoodsStatisticsModel;
use common\models\StoreModel;

use common\library\Language;
use common\library\Timezone;
use common\library\Def;
use common\library\Page;

/**
 * @Id Buyer_orderEvaluateForm.php 2018.9.19 $
 * @author mosir
 */
class Buyer_orderEvaluateForm extends Model
{
	public $errors = null;

	/**
	 * @api API接口使用该数据
	 */
	public function formData($post = null)
	{
		// 验证订单有效性 
		if (!$post->order_id || !($orderInfo = OrderModel::find()->where(['order_id' => $post->order_id, 'buyer_id' => Yii::$app->user->id])->asArray()->one())) {
			$this->errors = Language::get('no_such_order');
			return false;
		}

		// 不是已完成的订单，无法评价 
		if ($orderInfo['status'] != Def::ORDER_FINISHED) {
			$this->errors = Language::get('cannot_evaluate');
			return false;
		}
		// 已评价的订单，无法再评价 
		if ($orderInfo['evaluation_status'] == 1) {
			$this->errors = Language::get('already_evaluate');
			return false;
		}
	
		return $orderInfo;
	}

	/**
	 * 获取我的已评价订单
	 */
	public function getEvaluateList($post = null) 
	{
		$query = OrderGoodsModel::find()->alias('og')
			->select('og.spec_id,og.order_id,og.goods_id,og.goods_name,og.specification,og.price,og.quantity,og.goods_image,og.evaluation,og.comment,og.reply_comment,og.reply_time,og.images,o.order_sn,o.evaluation_time')
			->where(['buyer_id' => Yii::$app->user->id, 'evaluation_status' => 1])
			->joinWith('order o', false)
			->orderBy(['evaluation_time' => SORT_DESC, 'order_id' => SORT_DESC]);

		if($post->order_id) {
			$query->andWhere(['o.order_id' => explode(',', $post->order_id)]);
		}
		if($post->order_sn) {
			$query->andWhere(['o.order_sn' => explode(',', $post->order_sn)]);
		}
		
		$page = Page::getPage($query->count(), $post->page_size, false, $post->page);
		$list = $query->offset($page->offset)->limit($page->limit)->asArray()->all();
		foreach($list as $key => $value)
		{
			$list[$key]['evaluation_time'] = Timezone::localDate('Y-m-d H:i:s', $value['evaluation_time']);
			$list[$key]['reply_time'] = Timezone::localDate('Y-m-d H:i:s', $value['reply_time']);
			$list[$key]['images'] = json_decode($value['images']);
		}

		return array($list, $page);
	}

	/**
	 * @api API接口使用该数据
	 */
	public function submit($post = array(), $orderInfo = array())
	{
		// 写入宝贝描述评价 
		foreach ($post['evaluations']['goods'] as $spec_id => $item) {
			if ($item['value'] < 0 || $item['value'] > 5) {
				$this->errors = Language::get('evaluation_error');
				return false;
			}

			if (($model = OrderGoodsModel::find()->where(['spec_id' => intval($spec_id), 'order_id' => $orderInfo['order_id']])->one())) {
				$model->evaluation = $item['value'];
				$model->comment = addslashes($item['comment']);

				// 用户评价晒图
				if(isset($post['evaluations']['images'][$spec_id])) {
					$model->images = json_encode($post['evaluations']['images'][$spec_id]);
				}
				if ($model->save()) {

					// 更新店铺信用度
					StoreModel::updateAllCounters(['credit_value' => StoreModel::evaluationToValue($item['value'])], ['store_id' => $orderInfo['seller_id']]);
				}
			}
		}

		// 服务评价及物流评价
		$serviceEvaluation = isset($post['evaluations']['store']['service']) ? $post['evaluations']['store']['service'] : 0;
		$shippedEvaluation = isset($post['evaluations']['store']['shipped']) ? $post['evaluations']['store']['shipped'] : 0;

		// 写入店铺服务评价/物流评价及更新订单评价状态等		
		$model = OrderModel::findOne($orderInfo['order_id']);
		$model->evaluation_status = 1;
		$model->evaluation_time = Timezone::gmtime();
		$model->service_evaluation = ($serviceEvaluation >= 0 && $serviceEvaluation <= 5) ? $serviceEvaluation : 0;
		$model->shipped_evaluation = ($shippedEvaluation >= 0 && $shippedEvaluation <= 5) ? $shippedEvaluation : 0;
		if (!$model->save()) {
			$this->errors = $model->errors;
			return false;
		}

		// 更新店铺好评率
		StoreModel::updateAll(['praise_rate' => StoreModel::recountPraiseRate($orderInfo['seller_id'])], ['store_id' => $orderInfo['seller_id']]);

		// 更新商品评价数 
		$list = OrderGoodsModel::find()->where(['order_id' => $orderInfo['order_id']])->all();
		foreach ($list as $value) {
			GoodsStatisticsModel::updateAllCounters(['comments' => 1], ['goods_id' => $value->goods_id]);
		}
		return true;
	}

	public function getOrderGoods($post = null)
	{
		// 获取订单商品 
		$list = OrderGoodsModel::find()->where(['order_id' => $post->order_id])->asArray()->all();
		foreach ($list as $key => $goods) {
			empty($goods['goods_image']) && $list[$key]['goods_image'] = Yii::$app->params['default_goods_image'];
		}
		return $list;
	}
}
