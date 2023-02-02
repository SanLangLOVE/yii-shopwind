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
use yii\helpers\ArrayHelper;

use common\models\OrderModel;
use common\models\OrderGoodsModel;

use common\library\Language;
use common\library\Timezone;
use common\library\Page;
use common\library\Def;

/**
 * @Id Seller_orderEvaluateForm.php 2018.9.19 $
 * @author mosir
 */
class Seller_orderEvaluateForm extends Model
{
	public $errors = null;


	public function valid($post)
	{
		// 验证订单有效性 
		if (!$post->order_id || !($model = OrderModel::find()->select('status')->where(['order_id' => $post->order_id, 'seller_id' => Yii::$app->user->id])->one())) {
			$this->errors = Language::get('no_such_order');
			return false;
		}

		// 不是已完成的订单，无法回复评价 
		if ($model->status != Def::ORDER_FINISHED) {
			$this->errors = Language::get('cannot_evaluate');
			return false;
		}

		// 卖家已回评的不能再提交
		if(OrderGoodsModel::find()->select('rec_id')->where(['and', ['order_id' => $post->order_id, 'spec_id' => $post->spec_id], ['>', 'reply_time', 0]])->one()) {
			$this->errors = Language::get('cannot_evaluate');
			return false;
		}

		if(!isset($post->reply_comment) || empty($post->reply_comment)) {
			$this->errors = Language::get('reply_empty');
			return false;
		}
	
		return true;
	}

	/**
	 * 获取我管理的订单评价列表
	 */
	public function getEvaluateList($post = null) 
	{
		$query = OrderGoodsModel::find()->alias('og')
			->select('og.spec_id,og.order_id,og.goods_id,og.goods_name,og.specification,og.price,og.quantity,og.goods_image,og.evaluation,og.comment,og.reply_comment,og.reply_time,og.images,o.order_sn,o.evaluation_time')
			->where(['seller_id' => Yii::$app->user->id, 'evaluation_status' => 1])
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
	 * 提交卖家回复评价
	 */
	public function submit($post, $valid = true)
	{
		if($valid === true && !$this->valid($post)) {
			return false;
		}
	
		$model = OrderGoodsModel::find()->where(['order_id' => $post->order_id, 'spec_id' => $post->spec_id])->one();
		$model->reply_time = Timezone::gmtime();
		$model->reply_comment = $post->reply_comment;
		if(!$model->save()) {
			$this->errors = $model->errors;
			return false;
		}

		return ArrayHelper::toArray($model);		
	}
}
