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

use common\models\DeliveryTemplateModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Timezone;

/**
 * @Id My_deliveryForm.php 2018.10.2 $
 * @author luckey
 */
class My_deliveryForm extends Model
{
	public $id = 0;
	public $store_id = null;
	public $errors = null;

	public function valid(&$post)
	{
		if (empty($post->name)) {
			$this->errors = Language::get('name_empty');
			return false;
		}

		return true;
	}

	public function save($post, $valid = true)
	{
		if ($valid === true && !$this->valid($post)) {
			return false;
		}

		$result = Basewind::getCurrentApp() == 'api' ? $this->getPostFromApi($post) : $this->getPostFromPC($post);
		list($dests, $start, $postage, $plus, $postageplus, $types) = $result;

		if (!$this->id || !($model = DeliveryTemplateModel::find()->where(['template_id' => $this->id, 'store_id' => $this->store_id])->one())) {
			$model = new DeliveryTemplateModel();
			$model->created = Timezone::gmtime();
		}

		$model->name = $post->name;
		$model->store_id = $this->store_id;
		$model->types = substr($types, 1);
		$model->dests = substr($dests, 1);
		$model->start_standards = substr($start, 1);
		$model->start_fees = substr($postage, 1);
		$model->add_standards = substr($plus, 1);
		$model->add_fees = substr($postageplus, 1);

		if (!$this->checkVal($model->start_standards) || !$this->checkVal($model->start_fees) || !$this->checkVal($model->add_standards) || !$this->checkVal($model->add_fees)) {
			$this->errors = Language::get('item_invalid');
			return false;
		}

		if (!$model->save()) {
			$this->errors = $model->errors;
			return false;
		}
		return true;
	}

	private function getPostFromApi($post)
	{
		$dests = $start = $postage = $plus = $postageplus = $types = '';

		foreach ($post->area_fee as $type => $template) {
			$types .= ';' . $type;
			$dests .= ';' . $template->default_fee->dest_ids;
			$start .= ';' . $template->default_fee->start_standards;
			$postage .= ';' . $template->default_fee->start_fees;
			$plus .= ';' . $template->default_fee->add_standards;
			$postageplus .= ';' . $template->default_fee->add_fees;

			if (isset($template->other_fee) && !empty($template->other_fee)) {
				foreach ($template->other_fee as $item) {
					if (empty($item->dest_ids)) {
						$this->errors = Language::get('item_invalid');
						return false;
					}
					$dests .= ',' . $item->dest_ids;
					$start .= ',' . $item->start_standards;
					$postage .= ',' . $item->start_fees;
					$plus .= ',' . $item->add_standards;
					$postageplus .= ',' . $item->add_fees;
				}
			}
		}

		return [$dests, $start, $postage, $plus, $postageplus, $types];
	}

	/**
	 * PC（非VUE）提交数据兼容处理
	 */
	private function getPostFromPC($post)
	{
		$post = ArrayHelper::toArray($post);
		$dests = $start = $postage = $plus = $postageplus = $types = '';
		foreach ($post['types'] as $type) {

			// 检查是否有未设置地区项
			foreach ($post[$type . '_dests'] as $item) {
				if (trim($item) === '') {
					$this->errors = Language::get('set_region_pls');
					return false;
				}
			}

			$types .= ';' . $type;
			$dests .= ';' . implode(',', $post[$type . '_dests']);
			$start .= ';' . implode(',', $post[$type . '_start']);
			$postage .= ';' . implode(',', $post[$type . '_postage']);
			$plus .= ';' . implode(',', $post[$type . '_plus']);
			$postageplus .= ';' . implode(',', $post[$type . '_postageplus']);
		}
		return [$dests, $start, $postage, $plus, $postageplus, $types];
	}

	private function checkVal($string = '')
	{
		foreach (explode(';', $string) as $value) {
			foreach (explode(',', $value) as $v) {
				if (!is_numeric($v) || $v < 0 || $v == '') {
					return false;
				}
			}
		}
		return true;
	}
}
