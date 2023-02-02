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
use common\models\GoodsPropModel;
use common\models\GoodsPropValueModel;
use common\models\GcategoryModel;
use common\models\ScategoryModel;
use common\models\RegionModel;
use common\models\StoreModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Def;

/**
 * @Id SearchForm.php 2018.4.17 $
 * @author mosir
 */
class SearchForm extends Model
{
    public $errors = null;

	/*
	 * 获取当前商品搜索条件
	 */
	public function getConditions($post = null, $query = null)
	{
		if($query === null) {
			$query = GoodsModel::find()->alias('g')->joinWith('goodsStatistics gst', false)->where(['g.if_show' => 1, 'g.closed' => 0, 's.state' => 1]);
		}
		$query->joinWith('store s', false)->joinWith('goodsPvs gp', false);
		
		if($post->keyword) {
			$query->andWhere(['or', ['like', 'goods_name', $post->keyword], ['like', 'brand', $post->keyword], ['like', 'cate_name', $post->keyword]]);
		}
		if($post->cate_id) {
			$childIds = GcategoryModel::getDescendantIds($post->cate_id, 0);
			$query->andWhere(['in', 'g.cate_id', $childIds]);
		}
		if($post->brand) {
			$query->andWhere(['brand' => $post->brand]);
		}
		if($post->price) {
			$min = trim(explode('-', $post->price)[0]);
            $max = trim(explode('-', $post->price)[1]);
            $min > 0 && $query->andWhere(['>=', 'price', $min]);
            $max > 0 && $query->andWhere(['<=', 'price', $max]);
		}
		if($post->region_id) {
			$query->andWhere(['s.region_id' => $post->region_id]);
		}
		if($post->props)
		{
			foreach(explode('|', $post->props) as $k => $pv)
			{
				// 监测是否全为数字
				if(is_numeric(str_replace(':','', $pv))){
					$query->andWhere("instr(gp.pvs,:pv$k) > 0", [":pv$k" => $pv]);
				}
			}
		}
		
		// 排序
		if($post->orderby) {
			$orderBy = Basewind::trimAll(explode('|', $post->orderby));
			if(in_array($orderBy[0], array_keys($this->getOrders())) && in_array(strtolower($orderBy[1]), ['desc', 'asc'])) {
				$query->orderBy([$orderBy[0] => strtolower($orderBy[1]) == 'asc' ? SORT_ASC : SORT_DESC]);
			} else $query->orderBy(['g.add_time' => SORT_DESC]);
		}
		
		return $query;
	}
	
	/*
	 * 在一定的搜索条件下，获取还可用的商品检索字典
	 */
	public function getSelectors($post)
	{
		$result = array();
		
		// 按分类统计(把下级分类的商品数量也计算到父级)
		$by_category = array();
		$queryByCategory = $this->getConditions($post)->select('count(*) as count,g.cate_id')->groupBy('g.cate_id')->orderBy(['count' => SORT_DESC])->asArray()->all();
		foreach($queryByCategory as $key => $val) {
			if(($group = GcategoryModel::getParnetEnd($val['cate_id'], $post->cate_id)) !== false) {
				if(isset($by_category[$group[0]])) {
					$by_category[$group[0]]['count'] += $val['count'];
				} else $by_category[$group[0]] = ['cate_id' => $group[0], 'cate_name' => $group[1], 'count' => $val['count']];
			}
		}
		$result['by_category'] = array_values($by_category);
			
		// 按品牌统计
		$by_brand = $this->getConditions($post)->select('count(*) as count,g.brand,b.brand_logo')->joinWith('brand b', false)->andWhere(['b.if_show' => 1])->groupBy('g.brand')->orderBy(['count' => SORT_DESC])->asArray()->all();
		$result['by_brand'] = $by_brand;
			
		// 按价格统计
		$by_price = array();
		$priceMin = $this->getConditions($post)->min('g.price');
		$priceMax = min($this->getConditions($post)->max('g.price'), 10000);
		$priceStep = max(ceil(($priceMax - $priceMin) / 5), 50);
		$queryByPrice = $this->getConditions($post)->select("FLOOR((g.price-'{$priceMin}')/{$priceStep}) AS i, count(*) as count")->groupBy('i')->orderBy(['i' => SORT_ASC])->orderBy(['count' => SORT_DESC])->asArray()->all();
		foreach($queryByPrice as $key => $val) {
			$min = $priceMin + $val['i'] * $priceStep;
			$max = $priceMin + ($val['i'] + 1) * $priceStep;
			$by_price[] = array(
				'value' => $min . '-' . $max,
				'name'  => Def::priceFormat($min) .' - '. Def::priceFormat($max),
				'count' => $val['count'],
			);
		}
		$result['by_price'] = $by_price;
		
		// 按属性统计
		$by_prop = array();
		$queryByProps = $this->getConditions($post)->select('gp.pvs')->andWhere(['<>', 'gp.pvs', ''])->column();
		$pvs = array_unique(explode(';', implode(';', array_values($queryByProps))));
		sort($pvs, SORT_DESC);
			
		// 检查属性名和属性值是否存在，有可能是之前有，但后面删除了
		foreach($pvs as $key => $val) 
		{
			$item = explode(':', $val);
			if(!GoodsPropModel::find()->select('pid')->where(['pid' => $item[0], 'status' => 1])->exists()) {
				unset($pvs[$key]);
			}
			elseif(!GoodsPropValueModel::find()->where(['pid' => $item[0], 'vid' => $item[1], 'status' => 1])->exists()) {
				unset($pvs[$key]);
			}
		}
		// 当前选中的属性数组
		$propChecked = array();
		if($post->props) {
			$propChecked = array_unique(explode('|', $post->props));
			foreach($propChecked as $key => $val) {
				list($p, $v) = explode(':', $val);
				$propChecked[] = $p;
				unset($propChecked[$key]);
			}
			$propChecked = array_unique($propChecked);
			sort($propChecked);
		}

		$pid = 0;
		foreach($pvs as $key => $val)
		{
			$item = explode(':', $val);
				
			// 将选中的排除
			if(!in_array($item[0], $propChecked))
			{
				$prop = GoodsPropModel::find()->select('pid,name,is_color')->where(['pid' => $item[0], 'status' => 1])->one();
				$by_prop[$prop->pid] = ArrayHelper::toArray($prop);
					
				// 不是同一个pid的属性值，不做累加
				if($pid != $prop->pid) {
					$propValue = array();
					$pid = $prop->pid;
				}
				$propValue[] = GoodsPropValueModel::find()->select('vid,pid,pvalue as val,color')->where(['pid' => $item[0], 'vid' => $item[1], 'status' => 1])->asArray()->one();
				$by_prop[$prop->pid] += array('values' => $propValue);
			}
		}
		// 统计每个属性有多少商品数
		if($by_prop) 
		{
			foreach($by_prop as $key => $val)
			{
				if(!isset($val['values']) || empty($val['values'])) {
					unset($by_prop[$key]);continue;
				}
				foreach($val['values'] as $k => $v)
				{
					$by_prop[$key]['values'][$k]['count'] = $this->getConditions($post)->select('g.goods_id')->andWhere('instr(gp.pvs,:pv) > 0', [':pv' => $v['pid'].':'.$v['vid']])->count();
				}
			}
			$result['by_prop'] = array_values($by_prop);
		}
		
		// 按地区统计
		$by_region = $this->getConditions($post)->select('count(*) as count,s.region_id,s.region_name')->joinWith('store s', false)->andWhere(['>', 's.region_id', 0])->groupBy('s.region_id')->orderBy(['count' => SORT_DESC])->asArray()->all();
		$result['by_region'] = $by_region;
		
		return $result;
	}
	
	/**
	 * 获取当前店铺搜索条件
	 */
	public function getStoreConditions($post = null, $query = null)
	{
		if(isset($post->recommended)) {
			$query->andWhere(['recommended' => $post->recommended]);
		}
		if($post->cate_id) {
			$allId = ScategoryModel::getDescendantIds($post->cate_id);
			$query->andWhere(['in', 'cate_id', $allId]);
		}
		if($post->sgrade) {
			$query->andWhere(['sgrade' => $post->sgrade]);
		}
		if($post->praise_rate) {
			$query->andWhere(['>','praise_rate', $post->praise_rate]);
		}
		if($post->keyword) {
			$query->andWhere(['like','store_name', $post->keyword]);
		}
		
		if($post->level)
		{
			list($credit_value, $higher) = StoreModel::getValueByLevel($post->level);
			if($credit_value > 0) {
				$query->andWhere(['>=', 'credit_value', $credit_value]);
				if($higher > 0) {
					$query->andWhere(['<', 'credit_value', $higher]);
				}
			}
		}
		if($post->region_id) {
			$allId = RegionModel::getDescendantIds($post->region_id);
			$query->andWhere(['in', 'region_id', $allId]);
		}
		
		// 排序
		if($post->orderby) {
			$orderBy = Basewind::trimAll(explode('|', $post->orderby));
			if(in_array($orderBy[0], array_keys($this->getStoreOrders())) && in_array(strtolower($orderBy[1]), ['desc', 'asc'])) {
				$query->orderBy([$orderBy[0] => strtolower($orderBy[1]) == 'asc' ? SORT_ASC : SORT_DESC]);
			} else $query->orderBy(['add_time' => SORT_DESC]);
		}
		
		return $query;
	}
	
	/* 
	 * 取得选中条件 
	 */
    public function getFilters($post = null)
    {
        static $filters = null;
        if ($filters === null)
        {
            $filters = array();
            if($post->keyword)
            {
                $filters['keyword'] = array('key' => 'keyword', 'name' => Language::get('keyword'), 'value' => $post->keyword);
            }
			if($post->brand)
			{
				$filters['brand'] = array('key' => 'brand', 'name' => Language::get('brand'), 'value' => $post->brand);
			}
			if($post->region_id && ($region = RegionModel::find()->select('region_name')->where(['region_id' =>  $post->region_id])->one()))
			{
				$filters['region'] = array('key' => 'region_id', 'name' => Language::get('region'), 'value' => $region->region_name);
			}
			if($post->price)
			{
				list($priceMin, $priceMax) = Basewind::trimAll(explode('-', $post->price));
                if($priceMin <= 0) {
                    $filters['price'] = array('key' => 'price', 'name' => Language::get('price'), 'value' => Language::get('le') . ' ' . Def::priceFormat($priceMax));
                }
                elseif($priceMax <= 0) {
                    $filters['price'] = array('key' => 'price', 'name' => Language::get('price'), 'value' => Language::get('ge') . ' ' . Def::priceFormat($priceMin));
                }
                else{
                    $filters['price'] = array('key' => 'price', 'name' => Language::get('price'), 'value' => Def::priceFormat($priceMin) . ' - ' . Def::priceFormat($priceMax));
                }
			}
			if($post->props)
			{
				foreach(explode('|',$post->props) as $val)
				{
					$pv = explode(':', $val);
					if(is_numeric($pv[0]) && is_numeric($pv[1]))
					{
						$propName = GoodsPropModel::find()->select('name')->where(['pid' => $pv[0], 'status' => 1])->scalar();
						$propValue = GoodsPropValueModel::find()->select('pvalue')->where(['pid' => $pv[0], 'vid' => $pv[1], 'status' => 1])->scalar();
						if($propName && $propValue) {
							$filters['props'.$pv[0]] = array('key' => $val, 'name' => $propName, 'value'=> $propValue);
						}
					}
				}
			}
			if($post->cate_id)
			{
				$filters['category'] = array('key' => 'cate_id', 'name' => Language::get('gcategory'), 'value' => GcategoryModel::find()->select('cate_name')->where(['cate_id' => $post->cate_id])->scalar());
			}
        }
        return $filters;
    }
	
	/*
	 * 商品排序条件
	 */
	public function getOrders()
    {
        return array(
            ''             	=> Language::get('default_order'),
            'sales'     	=> Language::get('sales_desc'),
			'price'        	=> Language::get('price'),
			'g.add_time'    => Language::get('add_time'),
			'comments'     	=> Language::get('comment'),
            'credit_value'	=> Language::get('credit_value'),
            'views'       	=> Language::get('views')
        );
    }
	
	/*
	 * 店铺排序条件
	 */
	public function getStoreOrders()
    {
        return array(
			''             	=> Language::get('default_order'),
            'credit_value'	=> Language::get('credit_value'),
			'add_time'    	=> Language::get('add_time'),
            'praise_rate'	=> Language::get('praise_rate')
        );
    }
}
