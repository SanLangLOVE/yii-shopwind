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

use common\models\CouponsnModel;

use common\library\Timezone;

/**
 * @Id CouponModel.php 2018.5.20 $
 * @author mosir
 */

class CouponModel extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%coupon}}';
    }
	
	// 关联表
	public function getStore()
	{
		return parent::hasOne(StoreModel::className(), ['store_id' => 'store_id']);
	}
	
	/* 获取订单可用的优惠券 */
	public static function getAvailableByOrder($order = [])
	{
		$time = Timezone::gmtime();
		$list = CouponsnModel::find()->alias('sn')
			->select('sn.coupon_sn,c.coupon_value,c.coupon_name')
			//->select('sn.coupon_sn as number,c.coupon_value as value,c.coupon_name as name')
			->joinWith('coupon c', false)
			->where(['c.store_id' => $order['store_id'], 'sn.userid' => Yii::$app->user->id])
			->andWhere(['>=', 'sn.remain_times', 1])
			->andWhere(['<=', 'c.start_time', $time])
			->andWhere(['>=', 'c.end_time', $time])
			->andWhere(['<=', 'c.min_amount', $order['amount']])
			->orderBy(['c.coupon_value' => SORT_DESC])
			//->indexBy('number')
			->asArray()->all();

		return $list;
	}
}
