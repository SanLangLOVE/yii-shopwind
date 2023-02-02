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

use common\models\GoodsPropModel;
use common\models\GoodsPropValueModel;
use common\models\GoodsPvsModel;

/**
 * @Id CatePvsModel.php 2018.5.5 $
 * @author mosir
 */

class CatePvsModel extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%cate_pvs}}';
    }
	
	/**
	 * 获取分类的属性
	 * @param $cate_id int
	 * @param $goods_id int 传goods_id表示：将该商品具有的属性设置选中状态
	 */
	public static function getList($cate_id, $goods_id = 0)
	{
		$selected = [];
		if($goods_id > 0 && ($query = GoodsPvsModel::find()->select('pvs')->where(['goods_id' => intval($goods_id)])->one())) {
			$selected = explode(';', $query->pvs);
		}
		
		$result = $values = [];
		if(($query = parent::find()->select('pvs')->where(['cate_id' => intval($cate_id)])->one()))
		{
			foreach(explode(';', $query->pvs) as $key => $value)
			{
				if(empty($value)) continue;
				list($pid, $vid) = explode(':', $value);
				
				// 检验属性名和属性值是否存在
				if(($props = GoodsPropModel::find()->select('pid,is_color,name,ptype,status')->where(['pid' => $pid, 'status' => 1])->asArray()->one())) {
					if(($item = GoodsPropValueModel::find()->select('vid,pid,color,status,pvalue as value')->where(['pid' => $pid, 'vid' => $vid, 'status' => 1])->asArray()->one())) {
						if($selected && in_array($value, $selected)) $item['selected'] = 1;
						
						$result[$pid] = $props;
						$values[$pid][] = $item;
						$result[$pid]['values'] = $values[$pid];
					}
				}
			}
		}

		return array_values($result);
	}
}
