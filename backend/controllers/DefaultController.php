<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace backend\controllers;

use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

use common\models\UserModel;
use common\models\DepositTradeModel;
use common\models\StoreModel;
use common\models\GuideshopModel;
use common\models\DistributeMerchantModel;
use common\models\GoodsModel;
use common\models\OrderModel;
use common\models\RegionModel;
use common\models\GcategoryModel;
use common\models\ScategoryModel;
use common\models\RefundModel;
use common\models\ReportModel;
use common\models\PluginModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Resource;
use common\library\Timezone;
use common\library\Page;
use common\library\Def;
use common\library\Plugin;

/**
 * @Id DefaultController.php 2018.7.25 $
 * @author mosir
 */

class DefaultController extends \common\controllers\BaseAdminController
{
	/**
	 * 初始化
	 */
	public function init()
	{
		parent::init();
	}
	
	/**
	 * 排除特定Action外，其他需要登录后访问
	 * @param $action
	 * @var array $extraAction
	 */
	public function beforeAction($action)
    {
		$this->extraAction = ['captcha', 'checkCaptcha'];
		return parent::beforeAction($action);
    }
	
	public function actionIndex()
	{
		$this->params['promotes'] = Plugin::getInstance('promote')->build()->getList();
		// $this->params['sys_info'] = $this->getSysInfo();
		$this->params['functions'] = $this->getStatistics();
		$this->params['days'] = $this->getDataOfDay();
		$this->params['reminds'] = $this->getRemindInfo();
		$this->params['now'] = Timezone::gmtime();

		$this->params['_foot_tags'] = Resource::import('echarts/echarts.min.js,echarts/macarons.js');
		$this->params['page'] = Page::seo(['title' => Language::get('admin_backend')]);
        return $this->render('../index.html', $this->params);
	}
	
	/* 实时查询访客地区 */
	public function actionGetipinfo()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['id']);
		if(empty($post->id) || !($query = UserEnterModel::find()->select('id,ip')->where(['id' => $post->id])->one())){
			return Message::warning(Language::get('no_such_item'));
		}
		
		if(!($result = RegionModel::getAddressByIp($query->ip))) {
			return Message::warning(Language::get('get_region_fail'));
		}
		if(!($address = $result['city'])) {
			return Message::warning(Language::get('get_region_fail'));
		}
		$query->address = $address;
		$query->save();
		
		return Message::result($address);	
	}	
	
	/* 日数据 */
	public function getDataOfDay()
    {
		// 当天时间
        $today = Timezone::gmstr2time(Timezone::localDate('Y-m-d 00:00:00', Timezone::gmtime()));
		$list = ['today' => $today, 'yesterday' => $today - 24 * 3600];

		$result = [];
		foreach($list as $key => $value) {
			$result[$key] = array(
            	'users'  => UserModel::find()->where(['>', 'create_time', $value])->count('userid'),
            	'stores' => StoreModel::find()->where(['and', ['in', 'state', [Def::STORE_APPLYING, Def::STORE_OPEN]], ['>', 'add_time', $value]])->count('store_id'),
            	'orders' => OrderModel::find()->where(['and', ['!=', 'status', Def::ORDER_CANCELED], ['>', 'add_time', $value]])->count('order_id'),
				'sales' => OrderModel::find()->where(['and', ['>', 'status', Def::ORDER_CANCELED], ['>', 'add_time', $value]])->sum('order_amount')
        	);
		}
	
		foreach($result['today'] as $key => $value) {
			
			// 昨天的值
			$result['yesterday'][$key] -= $value;

			// 日同比
			if($result['yesterday'][$key] <= 0) {
				if($value > 0) {
					$result['compares'][$key] = ['value' => '100%', 'level' => 'high']; 
				} else {
					$result['compares'][$key] = ['value' => '0%', 'level' => 'equal']; 
				}
			} else {
				$v = round($value / $result['yesterday'][$key], 4);
				$result['compares'][$key] = ['value' => $v * 100 .'%', 'level' => $v > 1 ? 'high' : ($v < 1 ? 'low' : 'equal')];
			}
		}

		// 汇总
		$result['stores'] = StoreModel::find()->count('store_id');
		$result['users'] = UserModel::find()->count('userid');
		$result['orders'] = OrderModel::find()->count('order_id');
		$result['sales'] = OrderModel::find()->sum('order_amount');

		return $result;
    }

	/* 基础统计 */
	public function getStatistics()
    {
        return array(
			'refunds' => RefundModel::find()->where(['not in', 'status', ['CLOSED', 'SUCCESS']])->count('refund_id'),
			'drawals' => DepositTradeModel::find()->where(['bizIdentity' => Def::TRADE_DRAW, 'status' => 'VERIFY'])->count('trade_id'),
			'stores'  => StoreModel::find()->where(['in', 'state', [Def::STORE_APPLYING, Def::STORE_NOPASS]])->count('store_id'),
			'guideshops' => GuideshopModel::find()->where(['in', 'status', [Def::STORE_APPLYING, Def::STORE_NOPASS]])->count('id'),
			'distributeshops' => DistributeMerchantModel::find()->where(['in', 'status', [Def::STORE_APPLYING, Def::STORE_NOPASS]])->count('dmid'),
        );
    }
	
	/* 系统信息 */
	private function getSysInfo()
    {
        $file = Yii::getAlias('@frontend') . '/web/data/install.lock';
        return array(
            'server_os'     => PHP_OS,
            'web_server'    => $_SERVER['SERVER_SOFTWARE'],
            'php_version'   => PHP_VERSION, 
            'mysql_version' => Yii::$app->db->getServerVersion(),
			'version'		=> Basewind::getVersion(),
            'install_date'  => file_exists($file) ? date('Y-m-d', fileatime($file)) : date('Y-m-d', time()),
        );
    }
	
	/* 取得提醒信息 */
    public function getRemindInfo()
    {
        $remind_info = array();

        // 地区
		if(!RegionModel::find()->where(['parent_id' => 0, 'if_show' => 1])->exists()) {
			$remind_info[] = sprintf(Language::get('reminds.region'), Url::toRoute('region/index'));
			return $remind_info;
		}
        // 支付方式
		if(!PluginModel::find()->where(['instance' => 'payment', 'enabled' => 1])->exists()) {
			$remind_info[] = sprintf(Language::get('reminds.payment'), Url::toRoute(['plugin/index', 'instance' => 'payment']));
			return $remind_info;
		}
        // 商品分类
		if(!GcategoryModel::find()->where(['parent_id' => 0, 'store_id' => 0, 'if_show' => 1])->exists()) {
			$remind_info[] = sprintf(Language::get('reminds.gcategory'), Url::toRoute('gcategory/index'));
			return $remind_info;
		}
		
		// 待处理的举报
		if(($count = ReportModel::find()->where(['status' => 0])->count()) > 0) {
			$remind_info[] = sprintf(Language::get('reminds.report'), $count, Url::toRoute('report/index'));
			return $remind_info;
		}
		
		// 待平台处理的退款
		if(($count = RefundModel::find()->where(['intervene' => 1, 'status' => 'WAIT_SELLER_AGREE'])->count()) > 0) {
			$remind_info[] = sprintf(Language::get('reminds.refund'), $count, Url::toRoute('refund/index'));
			return $remind_info;
		}
		// 待审核的店铺
		if(($count = StoreModel::find()->where(['state' => Def::STORE_APPLYING])->count()) > 0) {
			$remind_info[] = sprintf(Language::get('reminds.apply'), $count, Url::toRoute('store/verify'));
			return $remind_info;
		}

        return $remind_info;
    }
}
