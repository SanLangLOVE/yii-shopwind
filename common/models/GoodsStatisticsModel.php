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

use common\models\OrderGoodsModel;

/**
 * @Id GoodsStatisticsModel.php 2018.4.24 $
 * @author mosir
 */

class GoodsStatisticsModel extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%goods_statistics}}';
    }
	
	/* 
	 * 更新浏览量/收藏量/销量/评价量 
	 */
	public static function updateStatistics($id = 0, $fields = 'views', $quantity = 1)
	{
		if(!in_array($fields, ['views', 'collects', 'orders', 'sales', 'comments'])) {
			return false;
		}
		if(!self::find()->where(['goods_id' => $id])->exists()) {
			$model = new GoodsStatisticsModel();
			$model->goods_id = $id;
			$model->$fields = $quantity;
			return $model->save();
		}
		elseif($model = self::find()->where(['goods_id' => $id])->one()) {
			return $model->updateCounters([$fields => $quantity]); 
		}
		return false;
	}
	
	/*
	 * 获取商品评价统计数据
	 * @api API接口用到该数据
	 */
	public static function getCommectStatistics($id = 0)
	{
		$query = OrderGoodsModel::find()->alias('og')->select('og.evaluation')->joinWith('order o', false)->where(['goods_id' => intval($id), 'o.evaluation_status' => 1, 'is_valid' => 1]);
		$result = array('total' => $query->count(), 'good' => ['count' => 0], 'middle' => ['count' => 0], 'bad' => ['count' => 0]);

		foreach($query->asArray()->all() as $record) {
			if($record['evaluation'] < 3) {
				$result['bad']['count']++;
			} elseif($record['evaluation'] == 3) {
				$result['middle']['count']++;
			} else {
				$result['good']['count']++;
			}
		}

		// 计算百分比
		if($query->count() > 0) {
			$result['good']['percentage'] = round($result['good']['count'] / $query->count(), 3) * 100 . '%'; 
			$result['middle']['percentage'] = round($result['middle']['count'] / $query->count(), 3) * 100 . '%';
			$result['bad']['percentage'] = round($result['bad']['count'] / $query->count(), 3) * 100 . '%';
		}

		return ['statistics' => $result];	
	}
}
