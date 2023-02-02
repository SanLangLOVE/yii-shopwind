<?php

/**
 * @link http://www.shopwind.net/
 * @copyright Copyright (c) 2018 shopwind, Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license http://www.shopwind.net/license/
 */

namespace frontend\models;

use Yii;
use yii\base\Model; 
use yii\helpers\ArrayHelper;

use common\models\GoodsModel;
use common\models\WholesaleModel;

use common\library\Language;
use common\library\Promotool;

/**
 * @Id WholesaleForm.php 2021.5.14 $
 * @author mosir
 */
class WholesaleForm extends Model
{
	public $store_id = null;
	public $errors = null;
	
	public function valid($post = null)
	{
		if(($message = Promotool::getInstance('wholesale')->build(['store_id' => $this->store_id])->checkAvailable()) !== true) {
			$this->errors = $message;
			return false;
		}

        if (!$post->goods_id || !GoodsModel::find()->where(['goods_id' => $post->goods_id, 'store_id' => $this->store_id, 'if_show' => 1, 'closed' => 0])->exists()) {
            $this->errors = Language::get('fill_goods');
			return false;
        }

		if(!isset($post->rules) || empty($post->rules)) {
			$this->errors = Language::get('fill_price');
			return false;
		}

		return true;
	}
	
	public function save($post, $valid = true)
	{
		if($valid === true && !$this->valid($post)) {
			return false;
		}

		// 留存副本
		$allId = WholesaleModel::find()->select('id')->where(['goods_id' => $post->goods_id, 'store_id' => $this->store_id])->column();

		$insertId = [];
		foreach($post->rules as $key => $value)
		{
			if(empty($value) || $value->quantity < 2 || $value->price < 0) {
				$this->errors =  Language::get('fill_price');
				break;
			}

			$model = new WholesaleModel();
			$model->store_id = $this->store_id;
			$model->goods_id = $post->goods_id;
			$model->quantity = $value->quantity;
			$model->price = $value->price;
			$model->status = isset($post->status) ? intval($post->status) : 1;
			if(!$model->save()) {
				$this->errors = $model->errors ? $model->errors : Language::get('handle_fail');
				break;
			}
			$insertId[] = $model->id;
		}
		if($this->errors !== null) 
		{
			// 把插入的数据删掉
			if(!empty($insertId)) {
				WholesaleModel::deleteAll(['in', 'id', $insertId]);
			}
			return false;
		}

		// 执行成功，删除旧数据
		if(!empty($allId)) {
			WholesaleModel::deleteAll(['in', 'id', $allId]);
		}

        return true;
	}
}
