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

use common\models\GoodsModel;
use common\models\GoodsSpecModel;
use common\models\AppmarketModel;
use common\models\ApprenewalModel;
use common\models\LimitbuyModel;
use common\models\UploadedFileModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Timezone;
use common\library\Promotool;
use common\library\Def;

/**
 * @Id Seller_limitbuyForm.php 2018.10.7 $
 * @author mosir
 */
class Seller_limitbuyForm extends Model
{
	public $id = 0;
	public $store_id = null;
	public $errors = null;
	
	public function valid(&$post)
	{
		$result = array();
	
		if(($message = Promotool::getInstance('limitbuy')->build(['store_id' => $this->store_id])->checkAvailable()) !== true) {
			$this->errors = $message;
			return false;
		}
		
		if (empty($post->title)) {
			$this->errors = Language::get('fill_title');
			return false;
		}

        $post->start_time = Timezone::gmstr2time($post->start_time);
        if ($post->end_time) {
			if(Timezone::gmstr2time($post->end_time) < Timezone::gmtime()) {
				$this->errors = Language::get('end_not_le_today');
				return false;
			}
			$post->end_time = Timezone::gmstr2time($post->end_time); // 前台时间允许到秒
        }
        else {
        	$this->errors = Language::get('fill_end_time');
		    return false;
        }
        if ($post->start_time > $post->end_time) {
			$this->errors = Language::get('start_not_gt_end');
			return false;
        }
		
		// 如果是订购模式
		if(AppmarketModel::find()->where(['appid' => 'limitbuy'])->exists())
		{
			// 如果结束的时间大于该应用的购买时限，则不允许
			$apprenewal = ApprenewalModel::find()->select('expired')->where(['appid' => 'limitbuy', 'userid' => Yii::$app->user->id])
				->orderBy(['rid' => SORT_DESC])->one();
				
			if(!$apprenewal || ($apprenewal->expired <= $post->end_time)) {
				$this->errors = sprintf(Language::get('limitbuy_end_time_gt_app_expired'), Timezone::localDate('Y-m-d', $apprenewal->expired));
				return false;
			}
		}

        if (!$post->goods_id) {
            $this->errors = Language::get('fill_goods');
			return false;
        }
		if(LimitbuyModel::find()->where(['goods_id' => $post->goods_id])->andWhere(['!=', 'id', $this->id])->exists()) {
			$this->errors = Language::get('goods_has_set_limitbuy');
			return false;
		}
        if (!$post->rules || !is_object($post->rules)) {
            $this->errors = Language::get('fill_spec');
			return false;
        }
	
		foreach($post->rules as $key => $value) 
		{
			if(!$value->price && !$value->discount) {
				$this->errors = Language::get('invalid_price');
				return false;
			}
			else 
			{
				if($value->discount) {
					if($value->discount >= 10 || $value->discount <= 0) {
						$this->errors = Language::get('invalid_discount');
						return false;
					}
					$result[$key] = ['price' => $value->discount, 'pro_type' => 'discount'];
				}
				elseif($value->price) {
					$price = GoodsSpecModel::find()->select('price')->where(['spec_id' => $key])->scalar();
					if($value->price <= 0 || $value->price >= $price) {
						$this->errors = Language::get('invalid_decrease');
						return false;
					}
					$result[$key] = ['price' => $value->price, 'pro_type' => 'price'];
				}
			}
		}
		if($result) {
			$post->rules = (object) $result;
		}
		
		return true;
	}
	
	public function save($post, $valid = true)
	{
		if($valid === true && !$this->valid($post)) {
			return false;
		}

		if(!$this->id || !($model = LimitbuyModel::find()->where(['id' => $this->id, 'store_id' => $this->store_id])->one())) {
			$model = new LimitbuyModel();
		}
		
		$model->title = $post->title;
		$model->summary = $post->summary ? $post->summary : '';
		$model->start_time = $post->start_time;
		$model->end_time = $post->end_time;
		$model->goods_id = $post->goods_id;
		$model->rules = serialize(ArrayHelper::toArray($post->rules));
		$model->store_id = $this->store_id;
		
		if(Basewind::getCurrentApp() == 'pc') {
			$post->image = UploadedFileModel::getInstance()->upload($post->fileVal, $this->store_id, Def::BELONG_LIMITBUY, Yii::$app->user->id);
		}
		// 注意：PC(非vue)提交后上传图片，WAP先上传图片后提交，所以WAP端有$post->image，但PC没有
		if($post->image) {
			$model->image = $post->image;
		}	
		if(!$model->save()) {
			$this->errors = $model->errors;
			return false;
		}
        return true;
	}
	
	public function queryInfo($id, $limitbuy = array())
    {
		if(!$id && $limitbuy) {
			$id = $limitbuy['goods_id'];
		}
		
		$goods = GoodsModel::find()->select('goods_id,goods_name,spec_name_1,spec_name_2,spec_qty,default_spec,default_image')
			->with('goodsSpec')->where(['goods_id' => $id, 'store_id' => $this->store_id])->asArray()->one();
		if(!$goods) {
			return false;
		}
		
		empty($goods['default_image']) && $goods['default_image'] = Yii::$app->params['default_goods_image'];
		
        if ($goods['spec_qty'] == 1 || $goods['spec_qty'] == 2) {
            $goods['spec_name'] = htmlspecialchars($goods['spec_name_1'] . ($goods['spec_name_2'] ? ' ' . $goods['spec_name_2'] : ''));
        }
        else {
            $goods['spec_name'] = Language::get('spec');
        }
		
        foreach ($goods['goodsSpec'] as $key => $spec)
        {	
            if ($goods['spec_qty'] == 1 || $goods['spec_qty'] == 2) {
                $goods['goodsSpec'][$key]['spec'] = htmlspecialchars($spec['spec_1'] . ($spec['spec_2'] ? ' ' . $spec['spec_2'] : ''));
			}
		    else {
                $goods['goodsSpec'][$key]['spec'] = Language::get('default_spec');
            }
			
			if($limitbuy) {
				$goods['goodsSpec'][$key]['pro_price'] = $limitbuy['rules'][$spec['spec_id']]['price'];
				$goods['goodsSpec'][$key]['pro_type'] = $limitbuy['rules'][$spec['spec_id']]['pro_type'];
			}
        }
        return $goods;
    }
}
