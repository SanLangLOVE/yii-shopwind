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
use common\models\GoodsSpecModel;
use common\models\GoodsImageModel;
use common\models\GoodsPvsModel;
use common\models\GoodsIntegralModel;
use common\models\UploadedFileModel;
use common\models\GcategoryModel;
use common\models\StoreModel;
use common\models\DeliveryTemplateModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Timezone;
use common\library\Promotool;
use common\library\Page;
use common\library\Plugin;

/**
 * @Id GoodsForm.php 2018.8.14 $
 * @author mosir
 */
class GoodsForm extends Model
{
	public $goods_id = 0;
	public $store_id = 0;
	public $gtype = 'material';
	public $errors = null;

	/**
	 * 从第三方平台采集数据导入到本地系统
	 */
	public function import($post, $itemid)
	{
		$cache = Yii::$app->cache;
		$cachekey = md5((__METHOD__) . var_export(func_get_args(), true));
		$result = $cache->get($cachekey);

		if ($result === false) {
			$client = Plugin::getInstance('datapicker')->autoBuild(false, ['platform' => $post->platform]);
			if (!$client || !($result = $client->detail($itemid))) {
				$this->errors = $client->errors;
				return false;
			}
			$cache->set($cachekey, $result, 3600 * 24 * 2);
		}

		$model = new GoodsModel();
		$model->store_id = $this->store_id;
		$model->add_time = Timezone::gmtime();

		$model->goods_name = $result['goods_name'];
		$model->spec_name_1 = $result['spec_name_1'];
		$model->spec_name_2 = $result['spec_name_2'];
		$model->spec_qty = $result['spec_qty'];
		$model->tags = $result['tags'];
		$model->description = $result['description'];

		$model->cate_id = $post->cate_id;
		$model->cate_name = '';
		$gcategories = GcategoryModel::getAncestor($post->cate_id);
		foreach ($gcategories as $key => $value) {
			$model->cate_name .= (($key == 0) ? "" : "\t") . $value['cate_name'];
		}

		if (!$model->save()) {
			$this->errors = $model->errors;
			return false;
		}

		// 保存商品主图
		foreach ($result['goods_images'] as $key => $value) {
			$imageModel = new GoodsImageModel();
			$imageModel->goods_id = $model->goods_id;
			$imageModel->image_url = $value;
			$imageModel->thumbnail = $value;
			$imageModel->sort_order = $key + 1;
			if ($imageModel->save()) {
				if ($key == 0) {
					$model->default_image = $value;
					$model->save();
				}
			}
		}

		// 保存商品规格
		if ($result['specs']) {
			foreach ($result['specs'] as $key => $value) {
				$specModel = new GoodsSpecModel();
				$specModel->goods_id = $model->goods_id;
				foreach ($value as $k => $v) {
					$specModel->$k = $v;
				}
				if ($specModel->save()) {
					if ($key == 0) {
						$model->default_spec = $specModel->spec_id;
						$model->price = $specModel->price;
						$model->save();
					}
				}
			}
		}

		return true;
	}
}
