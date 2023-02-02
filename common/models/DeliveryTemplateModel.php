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

use common\models\RegionModel;

use common\library\Timezone;
use common\library\Language;

/**
 * @Id DeliveryTemplateModel.php 2018.5.7 $
 * @author mosir
 */

class DeliveryTemplateModel extends ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%delivery_template}}';
	}

	public static function addFirstTemplate($store_id = 0, $flag = null)
	{
		$model = new DeliveryTemplateModel();
		$model->name 			= '默认运费';
		$model->store_id 		= intval($store_id);
		$model->types 			= implode(';', self::getTypes());
		$model->dests 			= '0;0;0';
		$model->start_standards = '1;1;1';
		$model->start_fees 		=  $flag ? implode(';', [$flag->start_fees, $flag->start_fees, $flag->start_fees]) : '10;10;10';
		$model->add_standards 	= '1;1;1';
		$model->add_fees 		= $flag ? implode(';', [$flag->add_fees, $flag->add_fees, $flag->add_fees]) : '8;8;8';
		$model->created			= Timezone::gmtime();

		return $model->save() ? ArrayHelper::toArray($model) : false;
	}

	public static function formatTemplate($delivery, $need_dest_ids = false)
	{
		if (!is_array($delivery)) {
			return array();
		}

		$data = $deliverys = array();
		foreach ($delivery as $template) {
			$data = array();
			$data['template_id'] = $template['template_id'];
			$data['name'] = $template['name'];
			$data['created'] = $template['created'];
			$data['store_id'] = $template['store_id'];

			$template_types = explode(';', $template['types']);
			$template_dests = explode(';', $template['dests']);
			$template_start_standards = explode(';', $template['start_standards']);
			$template_start_fees = explode(';', $template['start_fees']);
			$template_add_standards = explode(';', $template['add_standards']);
			$template_add_fees = explode(';', $template['add_fees']);

			$i = 0;
			foreach ($template_types as $key => $type) {
				$dests = explode(',', $template_dests[$key]);
				$start_standards = explode(',', $template_start_standards[$key]);
				$start_fees = explode(',', $template_start_fees[$key]);
				$add_standards = explode(',', $template_add_standards[$key]);
				$add_fees = explode(',', $template_add_fees[$key]);

				foreach ($dests as $k => $v) {
					$data['area_fee'][$i] = array(
						'type'				=> $type,
						'dests'				=> RegionModel::getRegionName($v),
						'start_standards'	=> $start_standards[$k],
						'start_fees'	 	=> $start_fees[$k],
						'add_standards'  	=> $add_standards[$k],
						'add_fees'		 	=> $add_fees[$k]
					);
					if ($need_dest_ids) {
						$data['area_fee'][$i]['dest_ids'] = $v;
					}
					$i++;
				}
			}
			$deliverys[] = $data;
		}
		return $deliverys;
	}

	public static function formatTemplateForEdit($delivery)
	{
		$delivery = self::formatTemplate([$delivery], true);
		$delivery = current($delivery);

		$area_fee = [];
		foreach ($delivery['area_fee'] as $key => $value) {
			$type = $value['type'];
			$area_fee[$type][] = $value;
		}
		$delivery['area_fee'] = $area_fee;

		foreach ($delivery['area_fee'] as $key => $value) {
			$delivery['area_fee'][$key]['name'] = Language::get($key);
			foreach ($value as $k => $v) {
				if ($k == 0) {
					$delivery['area_fee'][$key]['default_fee'] = $v;
				} else {
					$delivery['area_fee'][$key]['other_fee'][] = $v;
				}
				unset($delivery['area_fee'][$key][$k]);
			}
		}

		return $delivery;
	}

	public static function getPlusType($area_fee = [])
	{
		$types = self::getTypes();
		if (count($area_fee) > 0) {
			foreach ($types as $key => $value) {
				if (isset($area_fee[$value])) {
					unset($types[$key]);
				}
			}
		}
		return $types;
	}

	public static function getCityLogistic($delivery, $city_id = 0, $types = null, $cached = true)
	{
		$cache = Yii::$app->cache;
		$cachekey = md5((__METHOD__) . var_export(func_get_args(), true));
		$data = $cache->get($cachekey);
		if ($data === false || !$cached) {
			$logistic = array();

			if ($types == null) {
				$types = self::getTypes();
			}
			if ($delivery) {
				$logistic = self::getTypeLogistic(self::formatTemplateForEdit($delivery), $city_id, $types);
			}
			$data = $logistic;
			$cache->set($cachekey, $data, 3600);
		}
		return $data;
	}

	/*
	 * 找出运送目的地所处在的城市ID，便于按城市计算运费
	 */
	public static function getTrueCityId($destination = 0)
	{
		$result = array();

		// 运送目的地的上级树ID（包含自己）
		$parents = RegionModel::getParents($destination);

		$provinceCity = RegionModel::getProvinceCity();
		foreach ($provinceCity as $key => $province) {
			if ($province['cities']) {
				foreach ($province['cities'] as $k => $city) {
					if (in_array($city['region_id'], $parents)) {
						$result = array($province['region_id'], $city['region_id']);
						break;
					}
				}
			}
		}
		return $result;
	}

	/**
	 * @param int $destination 运送目的地ID，有可能是城市ID，也有可能是省ID或镇ID
	 */
	public static function getTypeLogistic($logistic, $destination, $types)
	{
		$result = array();

		if (($trueCity = self::getTrueCityId($destination))) {
			list($province_id, $city_id) = $trueCity;
		}
		if (isset($city_id) && !$city_id) {
			$city_id = 0;
			$province_id = 1;
		}

		foreach ($types as $type) {
			$find = false;
			if (isset($logistic['area_fee'][$type])) {
				if (isset($logistic['area_fee'][$type]['other_fee'])) {
					foreach ($logistic['area_fee'][$type]['other_fee'] as $key => $val) {
						$dest_ids = explode('|', $val['dest_ids']);
						if (in_array($city_id, $dest_ids) || in_array($province_id, $dest_ids)) {
							$result[] = array(
								'type' => $type,
								'name' => Language::get($type),
								'start_standards' => $val['start_standards'],
								'start_fees' => $val['start_fees'],
								'add_standards' => $val['add_standards'],
								'add_fees' => $val['add_fees']
							);
							$find = true;
							break;
						}
					}
				}

				// 找不到，取默认的
				if (!$find) {
					$result[] = array(
						'type' => $type,
						'name' => Language::get($type),
						'start_standards' => $logistic['area_fee'][$type]['default_fee']['start_standards'],
						'start_fees' => $logistic['area_fee'][$type]['default_fee']['start_fees'],
						'add_standards' => $logistic['area_fee'][$type]['default_fee']['add_standards'],
						'add_fees' => $logistic['area_fee'][$type]['default_fee']['add_fees']

					);
				}
			}
		}
		return $result;
	}

	/**
	 * 支持的配送方式
	 */
	private static function getTypes()
	{
		//return ['express','ems','post'];
		return ['express'];
	}
}
