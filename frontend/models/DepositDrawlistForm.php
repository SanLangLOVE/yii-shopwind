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

use common\library\Basewind;
use common\library\Language;
use common\library\Timezone;
use common\library\Page;

/**
 * @Id DepositDrawlistForm.php 2018.9.29 $
 * @author mosir
 */
class DepositDrawlistForm extends Model
{
	public $errors = null;
	
	public function formData($post = null, $pageper = 4) 
	{
		$query = DepositTradeModel::find()->alias('dt')->select('dt.tradeNo,dt.bizOrderId,dt.amount,dt.status,dt.title,dt.add_time,dw.drawtype,dw.account,dw.name,dw.bank')->joinWith('depositWithdraw dw', false)->where(['tradeCat' => 'WITHDRAW', 'buyer_id' => Yii::$app->user->id])->orderBy(['trade_id' => SORT_DESC]);
		$query = $this->getConditions($post, $query);
	
		$page = Page::getPage($query->count(), $pageper);
		$recordlist = $query->offset($page->offset)->limit($page->limit)->asArray()->all();
		foreach($recordlist as $key => $record)
		{	
			$recordlist[$key]['status_label'] = Language::get('TRADE_'.$record['status']);
		}
		return array($recordlist, $page);
	}

	public function getConditions($post, $query = null)
	{
		if($query === null) {
			foreach(array_keys(ArrayHelper::toArray($post)) as $field) {
				if(in_array($field, ['add_time_from', 'add_time_to', 'status'])) {
					return true;
				}
			}
			return false;
		}
		
		if($post->add_time_from) {
			$query->andWhere(['>=', 'add_time', Timezone::gmstr2time($post->add_time_from)]);
		}
		if($post->add_time_to) {
			$query->andWhere(['<=', 'add_time', Timezone::gmstr2time($post->add_time_to)]);
		}
		if($post->status) {
			$query->andWhere(['status' => in_array(strtoupper($post->status), ['VERIFING']) ? 'VERIFY' : 'SUCCESS']);
		}
		
		return $query;
	}
}
