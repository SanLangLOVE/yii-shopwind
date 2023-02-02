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
use yii\helpers\Url;

use common\models\ApprenewalModel;
use common\models\PromotoolSettingModel;

use common\library\Language;
use common\library\Timezone;
use common\library\Plugin;

/**
 * @Id AppmarketModel.php 2018.5.7 $
 * @author mosir
 */

class AppmarketModel extends ActiveRecord
{
	public $errors;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%appmarket}}';
    }
	
	public static function getList()
	{
		$result = [];

		$list = Plugin::getInstance('promote')->build()->getList();
		foreach($list as $key => $value) {
			$result[$key] = $value['name'];
		}

		return $result;
	}

	/**
	 * 检查该营销工具是否可用
	 * @var string $appid 工具id
	 * @var boolean $force 是否验证商家启用状态
	 */
	public function checkAvailable($appid, $store_id = 0, $force = true)
	{
		// 已发布到市场
		if($query = parent::find()->select('status')->where(['appid' => $appid])->one()) {
			
			// 若平台禁用
			if(!$query->status) {
				$this->errors = Language::get('appDisAvailable');
				return false;
			}

			// 以下为发布到应用市场，所有卖家需要订购后才可使用
			// 在此处判断商家是否购买了该营销工具
			$apprenewal = ApprenewalModel::find()->select('expired')->where(['appid' => $appid, 'userid' => $store_id])->orderBy(['rid' => SORT_DESC])->one();
			
			// 如果没有购买
			if(!$apprenewal) {
				$this->errors = Language::get('appHasNotBuy');
				return false;
			}
			
			// 如果购买了，已过期
			if($apprenewal->expired <= Timezone::gmtime()) {
				$this->errors = Language::get('appHasExpired');
				return false;
			}
		}
		
		// 如果商家禁用/或未配置优惠（目前只有 满包邮、满优惠、手机专享 有此控制）
		if($force && in_array($appid, ['exclusive', 'fullfree', 'fullprefer'])) {
			if(!PromotoolSettingModel::find()->where(['appid' => $appid, 'store_id' => $store_id, 'status' => 1])->exists()) {
				$this->errors = Language::get('appDisabled');
				return false;
			}
		}
	
		return true;
	}
}
