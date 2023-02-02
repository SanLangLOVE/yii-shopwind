<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\library;

use Yii;
use yii\helpers\ArrayHelper;

use common\models\AppmarketModel;
use common\models\PromotoolSettingModel;
use common\models\PromotoolItemModel;
use common\models\LimitbuyModel;
use common\models\TeambuyModel;
use common\models\MealModel;
use common\models\WholesaleModel;
use common\models\GoodsModel;
use common\models\GoodsSpecModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Timezone;

/**
 * @Id Promotool.php 2018.5.7 $
 * @author mosir
 * @desc The base library of Promote tools
 */

class Promotool
{
	// tool id
	public $instance = '';

	// extra params for each tool
	public $params = null;

	public function __construct($instance = null, $params = null)
	{
		if ($instance !== null) {
			$this->instance = $instance;
		}
		if ($params !== null) {
			$this->params = $params;
		}
	}

	public static function getInstance($instance = null)
	{
		return new Promotool($instance);
	}

	public function build($params = array())
	{
		if (in_array($this->instance, ['wholesale'])) {
			return new Wholesale($this->instance, $params);
		}
		if (in_array($this->instance, ['teambuy'])) {
			return new Teambuy($this->instance, $params);
		}
		if (in_array($this->instance, ['limitbuy'])) {
			return new Limitbuy($this->instance, $params);
		}
		if (in_array($this->instance, ['mealbuy'])) {
			return new Meal($this->instance, $params);
		}
		if (in_array($this->instance, ['exclusive'])) {
			return new Exclusive($this->instance, $params);
		}
		if (in_array($this->instance, ['fullfree'])) {
			return new Fullfree($this->instance, $params);
		}
		if (in_array($this->instance, ['fullprefer'])) {
			return new Fullprefer($this->instance, $params);
		}
		if (in_array($this->instance, ['distribute'])) {
			return new Distribute($this->instance, $params);
		}

		return new Promotool($this->instance, $params);
	}

	/**
	 * @var string $message 是否返回验证未通过信息
	 * @var boolean $force 是否验证商家启用状态
	 */
	public function checkAvailable($message = true, $force = true)
	{
		$model = new AppmarketModel();

		$result = $model->checkAvailable($this->instance, $this->params['store_id'], $force);
		if (!$result) {
			return $message ? $model->errors : false;
		}

		return true;
	}
	public function getInfo($params = array(), $format = true)
	{
		return PromotoolSettingModel::getInfo($this->instance, $this->params['store_id'], $params, $format);
	}
	public function getList($params = array(), &$pagination = false)
	{
		return PromotoolSettingModel::getList($this->instance, $this->params['store_id'], $params, $pagination);
	}
	public function delete($params = array())
	{
		if ($this->params['store_id']) {
			return PromotoolSettingModel::deleteAll(array_merge(['appid' => $this->instance, 'store_id' => $this->params['store_id']], $params));
		}
		return PromotoolSettingModel::deleteAll(array_merge(['appid' => $this->instance], $params));
	}
	public function getItemInfo($params = array(), $format = true)
	{
		return PromotoolItemModel::getInfo($this->instance, $this->params['store_id'], $params, $format);
	}
	public function deleteItem($params = array())
	{
		if ($this->params['store_id']) {
			return PromotoolItemModel::deleteAll(array_merge(['appid' => $this->instance, 'store_id' => $this->params['store_id']], $params));
		}
		return PromotoolItemModel::deleteAll(array_merge(['appid' => $this->instance], $params));
	}

	/**
	 * 获取某个商品，某个规格的促销价格信息（如限时促销，会员价格，手机专享价）
	 * 特别注意：此处不应考虑批发价，因批发价跟购买数量有关，避免在达到购买量后修改了购物车单价，当减少购买量后无法恢复到最初加入购物的价格的问题 
	 * @param boolean $force 强验证，验证促销价格时间
	 */
	public function getItemProInfo($goods_id = 0, $spec_id = 0, $force = true)
	{
		// 返回结果数组
		$result 	= false;

		// 用于标识是否获取到了优惠价格
		$proPrice 	= false;

		if (!isset($this->params['store_id']) || !$this->params['store_id'] || !$spec_id) {
			$query = GoodsModel::find()->select('store_id,default_spec')->where(['goods_id' => $goods_id])->one();
			if (!isset($this->params['store_id']) || !$this->params['store_id']) {
				$this->params['store_id'] = $query->store_id;
			}
			!$spec_id && $spec_id = $query->default_spec;
		}

		// 优先级一：限时促销功能
		if ($result === false) {
			list($proPrice, $id) = LimitbuyModel::getItemProPrice($goods_id, $spec_id, $force);
			if ($proPrice !== false) {
				$limitbuy = LimitbuyModel::find()->select('start_time,end_time,title')->where(['id' => $id])->one();
				$result = array(
					'price' => round($proPrice, 2),
					'type' => 'limitbuy',
					'name' => $limitbuy->title,
					'start_time' 	=> Timezone::localDate('Y-m-d H:i:s', $limitbuy->start_time),
					'end_time' 		=> Timezone::localDate('Y-m-d H:i:s', $limitbuy->end_time),
					'timestamp' 	=> $limitbuy->end_time - Timezone::gmtime(),
					'lefttime' 		=> Timezone::lefttime($limitbuy->end_time)
				);
			}
		}

		// 优先级二：手机专享价格
		if ($result === false) {
			if (Basewind::isMobileDevice()) {
				$exclusiveTool = self::getInstance('exclusive')->build(['store_id' => $this->params['store_id']]);
				list($proPrice) = $exclusiveTool->getItemProPrice($goods_id, $spec_id);
				if ($proPrice !== false) {
					$result = array(
						'price' => round($proPrice, 2),
						'type' => 'exclusive',
						'name' => Language::get('exclusive')
					);
				}
			}
		}

		return $result;
	}

	/* 获取卖家设置的某个商品的优惠价格 */
	public function getItemProPrice($goods_id = 0, $spec_id = 0)
	{
		$proPrice = false;

		if ($this->checkAvailable(false)) {
			if (($item_info = $this->getItemInfo(['goods_id' => $goods_id, 'status' => 1]))) {
				// 如果某个商品的配置信息为空，则说明每个商品的配置信息都是一致的，那么就从卖家营销工具综合配置表读取配置（规则）
				if (!$item_info['config']) {
					$info = $this->getInfo();
					$config = $info['rules'];
				} else {
					$config = $item_info['config'];
				}

				// 读取该商品原始价格
				$spec = GoodsSpecModel::find()->select('price')->where(['goods_id' => $goods_id, 'spec_id' => $spec_id])->one();

				// 手机专享优惠
				if ($this->instance == 'exclusive') {
					if (isset($config['discount']) && !empty($config['discount'])) {
						$proPrice = round($spec->price * $config['discount'] / 1000, 4) * 100;
					} elseif (isset($config['decrease']) && !empty($config['decrease'])) {
						$proPrice = $spec->price - $config['decrease'];
						if ($proPrice < 0) $proPrice = 0;
					}
				}
			}
		}

		return array($proPrice);
	}

	/* 获取商品详情页显示所有该商品具有的营销工具信息 */
	public function getGoodsAllPromotoolInfo($goods_id = 0, $cached = true)
	{
		$cache = Yii::$app->cache;
		$cachekey = md5((__METHOD__) . var_export(func_get_args(), true));
		$data = $cache->get($cachekey);
		if ($data === false || !$cached) {
			$data = array();

			// 店铺满包邮
			$fullfreeTool = self::getInstance('fullfree')->build(['store_id' => $this->params['store_id']]);
			if ($fullfreeTool->checkAvailable(false)) {
				$fullfree = $fullfreeTool->getInfo();
				if (isset($fullfree['status']) && $fullfree['status']) {
					if (isset($fullfree['rules']['amount'])) {
						$data['storeFullfreeInfo'] = sprintf(Language::get('free_amount_ship_title'), $fullfree['rules']['amount']);
					} else $data['storeFullfreeInfo'] = sprintf(Language::get('free_acount_ship_title'), $fullfree['rules']['quantity']);
				}
			}

			// 店铺满优惠
			$fullpreferTool = self::getInstance('fullprefer')->build(['store_id' => $this->params['store_id']]);
			if ($fullpreferTool->checkAvailable(false)) {
				$fullprefer = $fullpreferTool->getInfo();
				if (isset($fullprefer['status']) && $fullprefer['status']) {
					if ($fullprefer['rules']['type'] == 'discount') {
						$data['storeFullPreferInfo'] = sprintf(Language::get('fullperfer_discount_title'), $fullprefer['rules']['amount'], $fullprefer['rules']['discount']);
					} else $data['storeFullPreferInfo'] = sprintf(Language::get('fullperfer_decrease_title'), $fullprefer['rules']['amount'], $fullprefer['rules']['decrease']);
				}
			}

			$cache->set($cachekey, $data, 3600);
		}
		return $data;
	}

	/* 保存卖家设置的某个商品对应的营销工具信息 */
	public function savePromotoolItem($post = array())
	{
		$post = Basewind::trimAll($post, true);
		if (!isset($post->goods_id) || empty($post->goods_id)) {
			return false;
		}

		$post->appid 		= $this->instance;
		$post->store_id 	= $this->params['store_id'];

		if (isset($post->config) && !empty($post->config)) {

			$config = serialize(ArrayHelper::toArray($post->config));

			if ($this->instance == 'exclusive') {
				if (isset($post->config->discount) && $post->config->discount) $post->config->discount = floor(abs($post->config->discount) * 100) / 100;
				if (isset($post->config->decrease) && $post->config->decrease) $post->config->decrease = floor(abs($post->config->decrease) * 100) / 100;

				if (!$post->config->discount) unset($post->config->discount);
				if (isset($post->config->discount) && $post->config->discount) unset($post->config->decrease);

				if ((!isset($post->config->discount) || !$post->config->discount) && (!isset($post->config->decrease) || !$post->config->decrease)) {
					$config = '';
				}
			}
			$post->config = $config;
		}
		if (($item = $this->getItemInfo(['goods_id' => $post->goods_id], false))) {
			return PromotoolItemModel::updateAll($post, ['piid' => $item['piid']]);
		} else {
			$model = new PromotoolItemModel();
			$post->add_time = Timezone::gmtime();
			foreach ($post as $key => $val) {
				$model->$key = $val;
			}
			return $model->save();
		}
	}

	/* 获取订单满包邮设置 */
	public function getOrderFullfree($goods_info = array())
	{
		$result = array();
		$fullfreeTool = self::getInstance('fullfree')->build(['store_id' => $this->params['store_id']]);
		if ($fullfreeTool->checkAvailable(false)) {
			$fullfree = $fullfreeTool->getInfo();
			if (isset($fullfree['status']) && $fullfree['status']) {
				if (($goods_info['amount'] >= $fullfree['rules']['amount']) && ($fullfree['rules']['amount'] > 0)) {
					$result = array('title' => sprintf(Language::get('free_amount_ship_title'), $fullfree['rules']['amount']));
				} elseif (($goods_info['quantity'] >= $fullfree['rules']['quantity']) && ($fullfree['rules']['quantity'] > 0)) {
					$result = array('title' => sprintf(Language::get('free_acount_ship_title'), $fullfree['rules']['quantity']));
				}
			}
		}
		return $result ? $result : false;
	}

	/* 获取订单提交页面显示该订单所有营销工具信息（兼容多店铺合并付款） */
	public function getOrderAllPromotoolInfo(&$goods_info = array())
	{
		$order_info = $goods_info['orderList'][$this->params['store_id']];

		// 获取搭配套餐优惠 
		if ($goods_info['otype'] == 'mealbuy') {
			$goods_info['orderList'][$this->params['store_id']]['mealprefer'] = $this->getOrderMealPreferInfo($order_info);
		}

		// 判断商品金额（不含运费）是否满足满优惠设置 
		$goods_info['orderList'][$this->params['store_id']]['fullprefer'] = $this->getOrderFullPreferInfo($order_info);
	}

	/* 获取订单搭配套餐优惠 */
	public function getOrderMealPreferInfo($goods_info = [])
	{
		return array('text' => Language::get('submit_order_reduce'), 'price' => $goods_info['oldAmount'] - $goods_info['amount']);
	}

	/* 获取订单是否满足满优惠设置 */
	public function getOrderFullPreferInfo($goods_info = [])
	{
		$result = [];
		$fullpreferTool = self::getInstance('fullprefer')->build(['store_id' => $this->params['store_id']]);
		if ($fullpreferTool->checkAvailable(false)) {
			$fullprefer = $fullpreferTool->getInfo();
			if (isset($fullprefer['status']) && $fullprefer['status']) {
				$amount = floatval($fullprefer['rules']['amount']);
				if ($amount <= $goods_info['amount']) {
					$result = ['amount' => $amount, 'type' => $fullprefer['rules']['type']];
					if ($fullprefer['rules']['type'] == 'discount') {
						$decrease = round($goods_info['amount'] * (10 - $fullprefer['rules']['discount']) / 10, 2);
						$result = array_merge($result, [
							'text' => sprintf(Language::get('order_for_fullperfer_discount'), $amount, $fullprefer['rules']['discount']),
							'price' => $decrease,
							'discount' => $fullprefer['rules']['discount']
						]);
					} elseif ($fullprefer['rules']['type'] == 'decrease') {
						$decrease = $fullprefer['rules']['decrease'];
						$result = array_merge($result, [
							'text' => sprintf(Language::get('order_for_fullperfer_decrease'), $amount, $decrease),
							'price' => $decrease
						]);
					}
				}
			}
		}
		return $result;
	}
}

class Limitbuy extends Promotool
{

	public function getList($params = array(), &$pagination = false)
	{
		return LimitbuyModel::getList($this->instance, $this->params['store_id'], $params, $pagination);
	}
	public function getInfo($params = array(), $format = false)
	{
	}
}
class Meal extends Promotool
{

	public function getList($params = array(), &$pagination = false)
	{
		return MealModel::getList($this->instance, $this->params['store_id'], $params, $pagination);
	}
	public function getInfo($params = array(), $format = false)
	{
	}
}
class Exclusive extends Promotool
{

	public function getExclusive($goods_id = 0)
	{
		return PromotoolSettingModel::getExclusive($this->instance, $this->params['store_id'], $goods_id);
	}
}
class Teambuy extends Promotool
{
}
class Fullfree extends Promotool
{
}
class Fullprefer extends Promotool
{
}
class Distribute extends Promotool
{
}
class Wholesale extends Promotool
{

	public function getList($params = array(), &$pagination = false)
	{
		return WholesaleModel::getList($this->instance, $this->params['store_id'], $params, $pagination);
	}
	public function getInfo($params = array(), $format = false)
	{
	}
}