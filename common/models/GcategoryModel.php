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

use common\library\Tree;

/**
 * @Id GcategoryModel.php 2018.3.19 $
 * @author mosir
 */

class GcategoryModel extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%gcategory}}';
    }
	
	/**
     * 取得分类列表
     * @param int $parent_id 大于等于0表示取某分类的下级分类，小于0表示取所有分类
     * @param int $store_id  店铺编号
     * @param bool $shown    只取显示的分类
     */
    public static function getList($parent_id = -1, $store_id = 0, $shown = true, $limit = 0, $fields = null)
    {
		if(!$store_id) $store_id = 0;
		$query = parent::find()->where(['store_id' => $store_id]);
		if($fields != null) $query->select($fields);
		if($limit > 0) $query->limit($limit);

		if($parent_id >= 0) $query->andWhere(['parent_id' => $parent_id]);
		if($shown) $query->andWhere(['if_show' => 1]);
		
		return $query->orderBy(['sort_order' => SORT_ASC, 'cate_id' => SORT_ASC])->asArray()->all();
    }
	
	/* 分组，主要用在头部分类 */
	public static function getGroupGcategory($limit = 0, $cached = true)
	{
		$cache = Yii::$app->cache;
		$cachekey = md5((__METHOD__).var_export(func_get_args(), true));
		$data = $cache->get($cachekey);
		if($data === false || !$cached)
		{
			$data = array();

			$gcategories = self::getList(0, 0, true, 0, 'cate_id,cate_name,groupid,ad');
			foreach($gcategories as $key => $value)
			{
				if($value['groupid']) {
					$allId = self::find()->select('cate_id')->where(['groupid' => $value['groupid']])->column();
					$subitems = self::find()->select('cate_id,cate_name')->where(['in', 'parent_id', $allId])->limit(4)->orderBy(['sort_order' => SORT_ASC, 'cate_id' => SORT_ASC])->asArray()->all();
					$data['group'.$value['groupid']]['subitems'] = $subitems;
					$data['group'.$value['groupid']]['items'][] = $value;
				} else {
					$subitems = self::getList($value['cate_id'], 0, true, 4, 'cate_id,cate_name');
					$data[$value['cate_id']]['subitems'] = $subitems;
					$data[$value['cate_id']]['items'][] = $value;
				}
			}

			//第二个参数即是我们要缓存的数据 
    		//第三个参数是缓存时间，如果是0，意味着永久缓存。默认是0 
    		$cache->set($cachekey, $data, 3600); 
		}
		
		return $data;
	}
	
	/* 所有商品类目，树结构 */
	public static function getTree($store_id = 0, $shown = true, $layer = 0, $cached = true)
	{
		$cache = Yii::$app->cache;
		$cachekey = md5((__METHOD__).var_export(func_get_args(), true));
		$data = $cache->get($cachekey);
		if($data === false || !$cached)
		{
			$gcategories = self::getList(-1, $store_id, $shown);
		
			$tree = new Tree();
			$tree->setTree($gcategories, 'cate_id', 'parent_id', 'cate_name');
			$data = $tree->getArrayList(0, $layer);
		
    		//第二个参数即是我们要缓存的数据 
    		//第三个参数是缓存时间，如果是0，意味着永久缓存。默认是0 
    		$cache->set($cachekey, $data, 3600); 
		} 

		return $data;
	}
	
	/**
	 * 取得所有商品分类 
	 * 保留级别缩进效果，一般用于select
	 * @return array(21 => 'abc', 22 => '&nbsp;&nbsp;');
	 */
    public static function getOptions($store_id = 0, $parent_id = -1, $except = null, $layer = 0, $shown = true, $space = '&nbsp;&nbsp;')
    {
		$gcategories = self::getList($parent_id, $store_id, $shown);
		
		$tree = new Tree();
		$tree->setTree($gcategories, 'cate_id', 'parent_id', 'cate_name');
			
        return $tree->getOptions($layer, 0, $except, $space);
    }
	
	/**
     * 取得某分类的子孙分类id
     * @param int  $id     分类id
     * @param bool $cached 是否缓存
	 * @param bool $shown  只取显示的分类
	 * @param bool $selfin 是否包含自身id
	 * @return array(1,2,3,4...)
	 */
	public static function getDescendantIds($id = 0, $store_id = 0, $cached = true, $shown = false, $selfin = true)
	{
		$cache = Yii::$app->cache;
		$cachekey = md5((__METHOD__).var_export(func_get_args(), true));
		$data = $cache->get($cachekey);
		if($data === false || !$cached) 
		{
			$conditions = $shown ? ['store_id' => $store_id, 'if_show' => 1] : ['store_id' => $store_id];
			
			$tree = new Tree();
			$data = $tree->recursive(new GcategoryModel(), $conditions)->getArrayList($id)->fields($selfin);
						
			$cache->set($cachekey, $data, 3600);
		}
		return $data;
	}
	
	 /**
     * 取得某分类的祖先分类（包括自身，按层级排序）
     *
     * @param   int  $id       分类id
	 * @param 	int  $store_id  店铺编号
	 * @param 	bool $shown    只取要显示的分类
     * @param   bool $cached   是否取缓存
     * @return  array(
     *              array('cate_id' => 1, 'cate_name' => '数码产品'),
     *              array('cate_id' => 2, 'cate_name' => '手机'),
     *              ...
     *          )
     */
    public static function getAncestor($id, $store_id = 0, $shown = true, $cached = true)
	{
		$cache = Yii::$app->cache;
		$cachekey = md5((__METHOD__).var_export(func_get_args(), true));
		$data = $cache->get($cachekey);
		if($data === false || !$cached) 
		{
			$data = array();
			$query = parent::find()->select('cate_id,cate_name,parent_id')->where(['cate_id' => $id, 'store_id' => $store_id]);
			if($shown) $query->andWhere(['if_show' => 1]);
			$gcategory = $query->asArray()->one();
			if($gcategory) {
				$data[] = $gcategory;
			}
			
			while($gcategory && ($gcategory['parent_id'] > 0)) 
			{
				$query = parent::find()->select('cate_id,cate_name,parent_id')->where(['cate_id' => $gcategory['parent_id'], 'store_id' => $store_id]);
				if($shown) $query->andWhere(['if_show' => 1]);
				$gcategory = $query->asArray()->one();
					
				$data[] = $gcategory;
			}
			$cache->set($cachekey, $data, 3600);
		}
		return array_reverse($data);
	}
	
	/* 寻找某id的父级id，如果传parent_id，则找的父级id为parent_id的直接下级 */
	public static function getParnetEnd($id, $parent_id = 0)
	{
		while(($query = parent::find()->select('cate_id,parent_id,cate_name')->where(['cate_id' => $id])->one()) && ($query->parent_id != $parent_id)) {
			$id = $query->parent_id;
		}
		return $query ? [$query->cate_id, $query->cate_name] : false;
	}
	
	/**
     * 格式化分类名称
     *
     * @param string $cate_name 用tab键隔开的多级分类名称
	 * @param bool $textIndent 是否分级缩进
     * @return string
     */
	public static function formatCateName($cate_name = '', $textIndent = true, $split = ',')
    {
        $arr = explode("\t", $cate_name);
        if (count($arr) > 1)
        {
            for ($i = 0; $i < count($arr); $i++)
            {
                $arr[$i] = ($textIndent ? str_repeat("&nbsp;", $i * 4) : "") . htmlspecialchars($arr[$i]);
            }
            $cate_name = $textIndent ? join("\n", $arr) : join($split,  $arr);
        }

        return $cate_name;
    }
}
