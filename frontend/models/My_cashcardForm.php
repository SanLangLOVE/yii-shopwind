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

use common\models\CashcardModel;
use common\models\DepositTradeModel;

use common\library\Basewind;
use common\library\Timezone;
use common\library\Page;

/**
 * @Id My_cashcardForm.php 2018.10.3 $
 * @author mosir
 */
class My_cashcardForm extends Model
{
	public $errors = null;

	public function formData($post = null, $pageper = 4, $isAJax = false, $curPage = false) 
	{
		$query = CashcardModel::find()->select('cardNo,id,name,money,add_time,active_time,expire_time')
			->where(['useId' => Yii::$app->user->id])
			->orderBy(['active_time' => SORT_DESC, 'id' => SORT_DESC]);
		
		if($post->cardNo) {
			$query->andWhere(['cardNo' => $post->cardNo]);
		}
		if($post->keyword) {
			$query->andWhere(['like', 'name', $post->keyword]);
		}
	
		$page = Page::getPage($query->count(), $pageper, $isAJax, $curPage);
		$recordlist = $query->offset($page->offset)->limit($page->limit)->asArray()->all();
		foreach($recordlist as $key => $record)
		{
			$recordlist[$key]['valid'] = 1;
			
			if($record['expire_time'] > 0) {
				if(Timezone::gmtime() > $record['expire_time']) {
					$recordlist[$key]['valid'] = 0;
				}
			}

			if($tradeNo = DepositTradeModel::find()->select('tradeNo')->where(['bizOrderId' => $record['cardNo']])->scalar()) {
				$recordlist[$key]['tradeNo'] = $tradeNo;
			}
			 
			if(in_array(Basewind::getCurrentApp(), ['api'])) {
				$recordlist[$key]['add_time'] = Timezone::localDate('Y-m-d H:i:s', $record['add_time']);
				$record['active_time'] > 0 && $recordlist[$key]['active_time'] = Timezone::localDate('Y-m-d H:i:s', $record['active_time']);
				$record['expire_time'] > 0 && $recordlist[$key]['expire_time'] = Timezone::localDate('Y-m-d H:i:s', $record['expire_time']);
			}
		}
		return array($recordlist, $page);
	}
}
