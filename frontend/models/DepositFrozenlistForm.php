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

use common\models\DepositTradeModel;
use common\models\DepositRecordModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Timezone;
use common\library\Page;

/**
 * @Id DepositFrozenlistForm.php 2018.9.28 $
 * @author mosir
 */
class DepositFrozenlistForm extends Model
{
	public $errors = null;
	
	public function formData($post = null, $pageper = 4) 
	{
		$query = DepositRecordModel::find()->alias('dr')->select('dr.userid,dr.amount,dt.tradeNo,dt.bizOrderId,dt.status,dt.title,dt.add_time')->joinWith('depositTrade dt', false)->where(['userid' => Yii::$app->user->id])->orderBy(['record_id' => SORT_DESC]);
		$query = $this->getConditions($post, $query);
	
		$page = Page::getPage($query->count(), $pageper);
		$recordlist = $query->offset($page->offset)->limit($page->limit)->asArray()->all();
		foreach($recordlist as $key => $record)
		{
			// 交易的对方
			$recordlist[$key]['partyInfo'] = DepositTradeModel::getPartyInfoByRecord(Yii::$app->user->id, $record);
			$recordlist[$key]['status_label'] = Language::get(strtolower($record['status']));
		}
		return array($recordlist, $page);
	}
	
	/* 统计冻结总额 */
	public function getTotal()
	{
		$query = DepositRecordModel::find()->alias('dr')->select('amount')->joinWith('depositTrade dt', false)->where(['userid' => Yii::$app->user->id])->orderBy(['record_id' => SORT_DESC]);
		$query = $this->getConditions(null, $query);
		$amount = $query->sum('dr.amount');
		
		return array($amount);
	}
	
	public function getConditions($post, $query = null)
	{
		if($query === null) {
			foreach(array_keys(ArrayHelper::toArray($post)) as $field) {
				if(in_array($field, ['add_time_from', 'add_time_to'])) {
					return true;
				}
			}
			return false;
		}
		
		// 目前只冻结待审核的提现，如果还有其他类型的冻结交易，则加到此
		$query->andwhere(['tradeCat' => 'WITHDRAW', 'status' => 'VERIFY']);
		
		if($post->add_time_from) {
			$query->andWhere(['>=', 'add_time', Timezone::gmstr2time($post->add_time_from)]);
		}
		if($post->add_time_to) {
			$query->andWhere(['<=', 'add_time', Timezone::gmstr2time($post->add_time_to)]);
		}
		
		return $query;
	}
}
