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
use yii\helpers\ArrayHelper;

use common\models\PromotoolItemModel;
use common\models\GoodsModel;

use common\library\Timezone;
use common\library\Page;

/**
 * @Id PromotoolSettingModel.php 2018.5.7 $
 * @author mosir
 */

class PromotoolSettingModel extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%promotool_setting}}';
    }
	
	/* 获取某个卖家设置的营销工具详细信息，并格式化配置项 */
	public static function getInfo($appid, $store_id = 0, $params = array(), $format = true)
	{
		if($appid && $store_id)
		{
			$query = parent::find()->where(['appid' => $appid, 'store_id' => $store_id])->orderBy(['psid' => SORT_DESC]);
			if($params) {
				$query->andWhere($params);
			}
			if(($result = $query->one()) && $result->rules && $format) {
				$result->rules = unserialize($result->rules);
			}
		}
		return $result ? ArrayHelper::toArray($result) : [];
	}
	
	/* 获取某个卖家设置的营销工具列表，并格式化配置项 */
	public static function getList($appid, $store_id = 0, $params = array(), &$pagination = false)
	{
		$query = parent::find()->where(['appid' => $appid, 'store_id' => $store_id]);
		if($params) $query->andWhere($params);
		
		if($pagination !== false) {
			// 分页
			if(isset($pagination['pageSize']) && $pagination['pageSize'] > 0) {
				$page = Page::getPage($query->count(), $pagination['pageSize']);
				$query->offset($page->offset)->limit($page->limit);
				$pagination = $page;
			}
			// 指定数量
			elseif(is_numeric($pagination) && ($pagination > 0)) {
				$query->limit($pagination);
			}
		}
		$list = $query->orderBy(['psid' => SORT_DESC])->asArray()->all();

		foreach($list as $key => $val)
		{
			if($val['rules']) 
			{
				$items 		= array();
				$if_show 	= 0;
				$rules = unserialize($val['rules']);
				if(isset($rules['items']) && is_array($rules['items'])) {
					foreach($rules['items'] as $v) {
						if($item = self::getRulesItem($appid, $store_id, $v)) {
							if($item['available']) $if_show++;
							unset($item['available']);
							$items[$v] = $item;
						}
					}
					if(!$if_show) 
					{
						$list[$key]['status'] = 0; //  当所有项目都是下架状态，则该营销工具不可用
						parent::updateAll(['status' => 0], ['psid' => $val['psid']]);
					}
				}
				$list[$key]['rules'] = array(
					'title' => $rules['title'], 'amount' => isset($rules['amount']) ? $rules['amount'] : 0, 'money' => $rules['money'], 
					'items' => $items
				);
			}
		}
		
		return $list;
	}
	
	/* 获取卖家设置的营销工具中的每一项配置的值 */
	public static function getRulesItem($appid = '', $store_id = 0, $goods_id = 0)
	{
		$item = array();
		if($appid == 'fullfree') {
			// TODO
		}
		else
		{
			if(($item = GoodsModel::find()->select('goods_id,goods_name,price,default_spec,default_image,if_show,closed')->where(['goods_id' => $goods_id, 'store_id' => $store_id])->asArray()->one())) {
				$item['available'] = ($item['if_show'] && !$item['closed']) ? true : false;
			}
		}
		return $item;
	}
	
	public static function getExclusive($appid, $store_id = 0, $goods_id = 0)
	{
		$data = self::getInfo($appid, $store_id);
		if(isset($data) && $data && isset($data['status']) && $data['status'])
		{
			$desc = '';
			if($data['rules']['discount']) {
				$data['discount'] = $data['rules']['discount'];
				$desc = sprintf('开启后，通过手机下单，可享%s折优惠（默认）', $data['discount']);
			} else {
				$data['decrease'] = $data['rules']['decrease'];
				$desc = sprintf('开启后，通过手机下单，可立减%s元（默认）', $data['decrease']);
			}
			unset($data['rules'], $data['status']);
			$data['desc'] = $desc;
		}
		
		/* 设置选中状态 */
		if($goods_id) 
		{
			$data['selected'] = 0;
			if(($item = PromotoolItemModel::getInfo($appid, $store_id, ['goods_id' => $goods_id]))) {
				$item['status'] && $data['selected'] = 1;
				if(!$item['config']) $item['config'] = array();
                $data = array_merge($data, $item);
			}
		}
		return $data;
	}
}
