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

use common\models\MealModel;
use common\models\MealGoodsModel;
use common\models\GoodsModel;
use common\models\GoodsSpecModel;
use common\models\UploadedFileModel;

use common\library\Language;
use common\library\Timezone;
use common\library\Promotool;

/**
 * @Id Seller_mealForm.php 2018.10.24 $
 * @author luckey
 */
class Seller_mealForm extends Model
{
	public $meal_id = 0;
	public $store_id = null;
	public $errors = null;
	
	public function valid(&$post)
	{
		$result = array();
		
		if(($message = Promotool::getInstance('mealbuy')->build(['store_id' => $this->store_id])->checkAvailable()) !== true) {
			$this->errors = $message;
			return false;
		}
		
		if(!$post->title) {
			$this->errors = Language::get('note_for_title');
			return false;
		}
		if(!$post->price || $post->price <= 0) {
			$this->errors = Language::get('meal_price_gt0');
			return false;
		}
		if(!isset($post->selected)) {
			$this->errors = Language::get('add_records');
			return false;
		}
		
		$selected = array_unique(ArrayHelper::toArray($post->selected));
		
		// 套餐商品的数量必须在2-10之间
		if(!is_array($selected) || count($selected) < 2 || count($selected) > 10) {
			$this->errors = Language::get('records_error');
			return false;
		}
		
		// 搭配宝贝是否属于本店的
		if(GoodsModel::find()->where(['!=', 'store_id', $this->store_id])->andWhere(['in', 'goods_id', $selected])->exists()) {
			$this->errors = Language::get('goods_not_on_sale');
			return false;
		}
		
		// 套餐中的宝贝是否处在禁售或者下架中
		if(GoodsModel::find()->where(['store_id' => $this->store_id])->andWhere(['or', ['if_show' => 0], ['closed' => 1]])->andWhere(['in', 'goods_id', $selected])->exists()) {
			$this->errors = Language::get('goods_not_on_sale');
			return false;
		}
		
		// 取最小的金额总和 或有多个规格的话，就是小于价格最小的总价
		$list = GoodsSpecModel::find()->select('goods_id,price')->where(['in', 'goods_id', $selected])->indexBy('goods_id')->orderBy(['price' => SORT_DESC])->all();
		$allPrice = 0;
		foreach($list as $query) {
			$allPrice += $query->price;
		}
		$post->price = round($post->price, 2);
		if($post->price - $allPrice > 0) {
			$this->errors = sprintf(Language::get('meal_price_error'), $allPrice);
			return false;
		}
		
		return true;
	}
	
	public function save($post, $valid = true)
	{
		if($valid === true && !$this->valid($post)) {
			return false;
		}

		// 搭配购商品ID
		$selected = array_unique(ArrayHelper::toArray($post->selected));
		if(!$this->meal_id || !($model = MealModel::find()->where(['meal_id' => $this->meal_id, 'store_id' => $this->store_id])->one())) {
			$model = new MealModel();
			$model->created = Timezone::gmtime();
		}
		
		$model->store_id = $this->store_id;
		$model->title = $post->title;
		$model->price = $post->price;
		$model->keyword = $this->getKeywords($selected);
		$model->description = $post->description ? $post->description : '';
		$model->status = isset($post->status) ? intval($post->status) : 1;

		if(!$model->save()) {
			$this->errors = $model->errors;
			return false;
		}

		$this->meal_id = $model->meal_id;
		MealGoodsModel::deleteAll(['meal_id' => $model->meal_id]);
		foreach($selected as $goods_id) {
			$query = new MealGoodsModel();
			$query->meal_id = $model->meal_id;
			$query->goods_id = $goods_id;
			$query->save();
		}
		
		// for PC
		if ($post->desc_file_id) {
			UploadedFileModel::updateAll(['item_id' => $model->meal_id], ['in', 'file_id', array_unique(ArrayHelper::toArray($post->desc_file_id))]); 
  		}
		
        return true;
	}

	/**
	 * 获取搭配购商品关键词，用于搜索用途
	 */
	private function getKeywords($selected = [])
	{
		$all = GoodsModel::find()->select('goods_name')->where(['in', 'goods_id', $selected])->column();
		return $all ? implode(',', $all) : '';
	}
	
	public function queryInfo($id, $meal = null)
    {
		$allId = array();
		
		if($id) {
			$allId = explode(',', $id);
		} elseif($meal) {
			$allId = MealGoodsModel::find()->select('goods_id')->where(['meal_id' => $meal['meal_id']])->column();
		}
		
		$goodsList = GoodsModel::find()->select('goods_id,goods_name,price,default_image')->where(['store_id' => $this->store_id])->andWhere(['in', 'goods_id', $allId])->asArray()->all();
		foreach($goodsList as $key => $goods)
		{
			$price_data = GoodsSpecModel::find()->select('min(price) as priceMin,max(price) as priceMax')->where(['goods_id' => $mg['goods_id']])->asArray()->one();
			if($price_data && ($price_data['priceMin'] != $price_data['priceMax'])) {
				$goodsList[$key]['price'] = $price_data['priceMin'] . '-' . $price_data['priceMax'];
			} else $goodsList[$key]['price'] = $goods['price'];
				
			$goodsList[$key]['goods_name'] = htmlspecialchars($goods['goods_name']); // json need
			$goods['default_image'] || $goodsList[$key]['default_image'] = Yii::$app->params['default_goods_image'];
		}
		
		return $goodsList;
    }
}