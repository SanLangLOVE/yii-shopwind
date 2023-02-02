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

use common\models\CouponModel;

use common\library\Language;
use common\library\Timezone;

/**
 * @Id Seller_couponForm.php 2018.11.20 $
 * @author luckey
 */
class Seller_couponForm extends Model
{
	public $coupon_id = 0;
	public $store_id = 0;
	public $errors = null;

	public function valid($post)
	{
		if(!$post->coupon_name) {
			$this->errors = Language::get('coupon_name_required');
			return false;
		}
		if(!$post->coupon_value || ($post->coupon_value < 0)) {
			$this->errors = Language::get('coupon_value_not');
			return false;
		}
		if(!$post->min_amount || ($post->min_amount < 0)) {
			$this->errors = Language::get('min_amount_gt_zero');
			return false;
		}
		if(!$post->total || ($post->total < 0)) {
			$this->errors = Language::get('coupon_total_required');
			return false;
		}
		if(empty($post->start_time) || empty($post->end_time)) {
			$this->errors = Language::get('time_invalid');
			return false;
		}
		if(Timezone::gmstr2time_end($post->end_time) < Timezone::gmstr2time($post->start_time)) {
			$this->errors = Language::get('end_gt_start');
			return false;
		}

		return true;
	}
	
	public function save($post, $valid = true)
	{
		if($valid === true && !$this->valid($post)) {
			return false;
		}
		if(!$this->coupon_id || !($model = CouponModel::find()->where(['coupon_id' => $this->coupon_id, 'store_id' => $this->store_id])->one())) {
			$model = new CouponModel();
			$model->store_id = $this->store_id;
			$model->available = 1;
			$model->use_times = 1;
			$model->total = $post->total;
			$model->surplus = $post->total;
		}
		
		$model->coupon_name = $post->coupon_name;
		$model->coupon_value = $post->coupon_value;
		$model->start_time = Timezone::gmstr2time($post->start_time);
		$model->end_time = stripos($post->end_time, ':') == false ? Timezone::gmstr2time_end($post->end_time) : Timezone::gmstr2time($post->end_time);
		$model->min_amount = $post->min_amount;
		$model->clickreceive = isset($post->clickreceive) ? intval($post->clickreceive) : 0;
		
		if(!$model->save()) {
			$this->errors = $model->errors;
			return false;
		}
		return ArrayHelper::toArray($model);
	}
}
