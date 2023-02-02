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
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

use common\models\OrderModel;
use common\models\DepositTradeModel;
use common\models\GuideshopModel;
use common\models\DepositSettingModel;
use common\models\GcategoryModel;
use common\models\UserModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Resource;
use common\library\Timezone;
use common\library\Page;
use common\library\Def;

/**
 * @Id GuideshopController.php 2020.2.4 $
 * @author mosir
 */

class GuideshopController extends \common\controllers\BaseAdminController
{
	/**
	 * 初始化
	 */
	public function init()
	{
		parent::init();
	}
	
	public function actionIndex()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['limit', 'page']);
	
		if(!Yii::$app->request->isAjax)
		{
			$this->params['filtered'] = $this->getConditions($post);

			$this->params['_foot_tags'] = Resource::import('inline_edit.js');
			$this->params['page'] = Page::seo(['title' => Language::get('guideshop_list')]);
			return $this->render('../guideshop.index.html', $this->params);
		}
		else
		{
			$query = GuideshopModel::find()->select('id,userid,owner,phone_mob,name,region_name,address,created,status');
			$query = $this->getConditions($post, $query)->orderBy(['id' => SORT_DESC]);
			
			
			$page = Page::getPage($query->count(), $post->limit ? $post->limit : 10);
			$list = $query->offset($page->offset)->limit($page->limit)->asArray()->all();
			
			foreach ($list as $key => $value)
			{
				if($username = UserModel::find()->select('username')->where(['userid' => $value['userid']])->scalar()){
			        $list[$key]['username'] = $username;
			    }
				$list[$key]['guider_rate'] = DepositSettingModel::getDepositSetting($value['userid'], 'guider_rate');
				$list[$key]['address'] 	= $value['region_name'].$value['address'];
				$list[$key]['status'] 	= $this->getStatus($value['status']);
				$list[$key]['created'] 	= Timezone::localDate('Y-m-d', $value['created']);
			}

			return Json::encode(['code' => 0, 'msg' => '', 'count' => $query->count(), 'data' => $list]);
		}
	}

	/**
	 * 门店自提订单
	 */
	public function actionOrder()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['limit', 'page', 'status']);
		
		if(!Yii::$app->request->isAjax) 
		{
			$this->params['filtered'] = $this->getOrderConditions($post);
			$this->params['search_options'] = $this->getSearchOption();
			$this->params['status_list'] = $this->getOrderStatus();
			
			$this->params['_foot_tags'] = Resource::import([
				'script' => 'jquery.ui/jquery.ui.js,jquery.ui/i18n/' . Yii::$app->language . '.js',
            	'style'=> 'jquery.ui/themes/smoothness/jquery.ui.css'
			]);
			$this->params['page'] = Page::seo(['title' => Language::get('order_list')]);
			return $this->render('../guideshop.order.html', $this->params);
		}
		else
		{
			$query = OrderModel::find()->select('order_id,order_sn,buyer_name,seller_name as store_name,order_amount,payment_name,status,add_time,pay_time,finished_time');
			$query = $this->getOrderConditions($post, $query)->andWhere(['otype' => 'guidebuy'])->orderBy(['order_id' => SORT_DESC]);
			
			$page = Page::getPage($query->count(), $post->limit ? $post->limit : 10);
			$list = $query->offset($page->offset)->limit($page->limit)->asArray()->all();
			foreach ($list as $key => $value)
			{
				$list[$key]['tradeNo'] = DepositTradeModel::find()->select('tradeNo')->where(['bizOrderId' => $value['order_sn']])->scalar();
				$list[$key]['add_time'] = Timezone::localDate('Y-m-d H:i:s', $value['add_time']);
				$list[$key]['pay_time'] = Timezone::localDate('Y-m-d H:i:s', $value['pay_time']);
				$list[$key]['finished_time'] = Timezone::localDate('Y-m-d H:i:s', $value['finished_time']);
				$list[$key]['status'] = Def::getOrderStatus($value['status']);
			}

			return Json::encode(['code' => 0, 'msg' => '', 'count' => $query->count(), 'data' => $list]);
		}
	}

	/**
	 * 门店审核
	 */
	public function actionVerify()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['limit', 'page']);
	
		if(!Yii::$app->request->isAjax)
		{
			$this->params['page'] = Page::seo(['title' => Language::get('guideshop_verify')]);
			return $this->render('../guideshop.verify.html', $this->params);
		}
		else
		{
			$query = GuideshopModel::find()->select('id,userid,owner,phone_mob,name,region_name,address,status')
				->where(['in', 'status', [Def::STORE_APPLYING, Def::STORE_NOPASS]])
				->orderBy(['id' => SORT_DESC]);
			
			
			$page = Page::getPage($query->count(), $post->limit ? $post->limit : 10);
			$list = $query->offset($page->offset)->limit($page->limit)->asArray()->all();
			
			foreach ($list as $key => $value)
			{
				$list[$key]['guider_rate'] = DepositSettingModel::getDepositSetting($value['userid'], 'guider_rate');
				$list[$key]['address'] 	= $value['region_name'].$value['address'];
				$list[$key]['status'] 	= $this->getStatus($value['status']);
			}

			return Json::encode(['code' => 0, 'msg' => '', 'count' => $query->count(), 'data' => $list]);
		}
	}

	/**
	 * 查看门店并审核
	 */
	public function actionView()
	{
		$get = Basewind::trimAll(Yii::$app->request->get(), true, ['id']);

		if(!Yii::$app->request->isPost)
		{
			$record = GuideshopModel::find()->select('id,owner,phone_mob,name,region_name,address,created,status,banner,remark')->where(['id' => $get->id])->asArray()->one();
			$record['status'] = $this->getStatus($record['status']);
			$this->params['guideshop'] = $record;
			
			$this->params['page'] = Page::seo(['title' => Language::get('detail')]);
			return $this->render('../guideshop.view.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post(), true);
			$model = GuideshopModel::findOne($get->id);

			// 待审核的店铺才允许提交，防止重复插入
			if($model && $model->status == Def::STORE_APPLYING)
			{
				// 批准
				if ($post->action == 'agree')
				{
					$model->status = Def::STORE_OPEN;
					$model->remark = '';
					if(!$model->save()) {
						return Message::warning(Language::get('handle_fail'));
					}

					return Message::display(Language::get('agree_ok'), ['guideshop/index']);
				}
				// 拒绝
				elseif($post->action == 'reject')
				{
					if (!$post->reason) {
						return Message::warning(Language::get('input_reason'));
					}
					
					$model->remark = $post->reason;
					$model->status = Def::STORE_NOPASS;
					if(!$model->save()) {
						return Message::warning(Language::get('handle_fail'));
					}

					return Message::display(Language::get('reject_ok'), ['guideshop/verify']);
				}
			}
			return Message::warning(Language::get('handle_error'));
		}
	}

	public function actionSetting()
	{
		if(!Yii::$app->request->isPost)
		{
			$setting = DepositSettingModel::find()->select('guider_rate')->where(['userid' => 0])->asArray()->one();
			if(($guideshop = Yii::$app->params['guideshop'])) {
				$setting = array_merge($setting, $guideshop);
			}
			$this->params['setting'] = $setting;
			$this->params['gcategories'] = GcategoryModel::getOptions(0, -1, null, 2);
			$this->params['page'] = Page::seo(['title' => Language::get('config')]);
			return $this->render('../guideshop.setting.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post(), true);

			$model = new \backend\models\DepositSettingForm();
			if(!$model->save($post, true)) {
				return Message::warning($model->errors);
			}

			$post = ['guideshop' => ['cateId' => intval($post->cate_id)]];
			$model = new \backend\models\SettingForm();
			if(!$model->save($post, true)) {
				return Message::warning($model->errors);
			}

			return Message::display(Language::get('handle_ok'));
		}
	}
	
	/**
	 * 删除门店
	 */
	public function actionDelete()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);
		GuideshopModel::deleteAll(['in', 'id', explode(',', $post->id)]);
		return Message::display(Language::get('drop_ok'));
	}

	/* 异步修改数据 */
    public function actionEditcol()
    {
		$post = Basewind::trimAll(Yii::$app->request->get(), true);
		if(in_array($post->column, ['guider_rate'])) {
			if(!($userid = GuideshopModel::find()->select('userid')->where(['id' => $post->id])->scalar())) {
				return Message::warning(Language::get('edit_fail'));
			}
			$model = new \backend\models\DepositSettingForm(['userid' => $userid]);
			if(!$model->save((Object)[$post->column => $post->value], true)) {
				return Message::warning($model->errors);
			}
			return Message::display(Language::get('edit_ok'));
		}
    }

	/**
	 * 导出数据
	 */
	public function actionExport()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);
		if($post->id) $post->id = explode(',', $post->id);
		
		$query = GuideshopModel::find()->select('id,owner,phone_mob,name,region_name,address,created,status');
		if(!empty($post->id)) {
			$query->andWhere(['in', 'id', $post->id]);
		}
		else {
			$query = $this->getConditions($post, $query)->limit(100);
		}
		if($query->count() == 0) {
			return Message::warning(Language::get('no_data'));
		}
		return \backend\models\GuideshopExportForm::download($query->asArray()->all());		
	}
	
	private function getStatus($status = null)
	{
		$result = array(
            Def::STORE_APPLYING  => Language::get('applying'),
			Def::STORE_NOPASS	 => Language::get('nopass'),
            Def::STORE_OPEN      => Language::get('open'),
            Def::STORE_CLOSED    => Language::get('close'),
        );
		if($status !== null) {
			return isset($result[$status]) ? $result[$status] : '';
		}
		return $result;		
	}

	private function getOrderStatus($status = null)
	{
		$result = array(
			Def::ORDER_PENDING  => Language::get('order_pending'),
            Def::ORDER_PICKING  => Language::get('order_picking'),
			Def::ORDER_DELIVERED=> Language::get('order_delivered'),
			Def::ORDER_FINISHED => Language::get('order_finished'),
            Def::ORDER_CANCELED => Language::get('order_canceled'),
        );
		if($status !== null) {
			return isset($result[$status]) ? $result[$status] : '';
		}
		return $result;		
	}

	private function getSearchOption()
	{
		return array(
            'seller_name'	=> Language::get('store_name'),
            'buyer_name' 	=> Language::get('buyer_name'),
            'payment_name' 	=> Language::get('payment_name'),
            'order_sn' 		=> Language::get('order_sn'),
		);
	}
	
	private function getConditions($post, $query = null)
	{
		if($query === null) {
			foreach(array_keys(ArrayHelper::toArray($post)) as $field) {
				if(in_array($field, ['name', 'status', 'owner', 'phone_mob'])) {
					return true;
				}
			}
			return false;
		}
		
		if($post->name) {
			$query->andWhere(['like', 'name', $post->name]);
		}
		if($post->owner) {
			$query->andWhere(['owner' => $post->owner]);
		}
		if($post->phone_mob) {
			$query->andWhere(['phone_mob' => $post->phone_mob]);
		}
		if(isset($post->status) && $post->status !== '') {
			$query->andWhere(['status' => intval($post->status)]);
		} else {
			$query->andWhere(['in', 'status', [Def::STORE_OPEN, Def::STORE_CLOSED]]);
		}

		return $query;
	}

	private function getOrderConditions($post, $query = null)
	{
		if($query === null) {
			foreach(array_keys(ArrayHelper::toArray($post)) as $field) {
				if(in_array($field, ['search_name', 'status', 'add_time_from', 'add_time_to', 'order_amount_from', 'order_amount_to'])) {
					return true;
				}
			}
			return false;
		}
		
		if($post->field && $post->search_name && in_array($post->field, array_keys($this->getSearchOption()))) {
			$query->andWhere([$post->field => $post->search_name]);
		}
		if($post->status) {
			$query->andWhere(['status' => $post->status]);
		}
		if($post->add_time_from) $post->add_time_from = Timezone::gmstr2time($post->add_time_from);
		if($post->add_time_to) $post->add_time_to = Timezone::gmstr2time_end($post->add_time_to);
		if($post->add_time_from && $post->add_time_to) {
			$query->andWhere(['and', ['>=', 'add_time', $post->add_time_from], ['<=', 'add_time', $post->add_time_to]]);
		}
		if($post->add_time_from && (!$post->add_time_to || ($post->add_time_to <= $post->add_time_from))) {
			$query->andWhere(['>=', 'add_time', $post->add_time_from]);
		}
		if(!$post->add_time_from && ($post->add_time_to && ($post->add_time_to > Timezone::gmtime()))) {
			$query->andWhere(['<=', 'add_time', $post->add_time_to]);
		}
		
		if($post->order_amount_from) $post->order_amount_from = floatval($post->order_amount_from);
		if($post->order_amount_to) $post->order_amount_to = floatval($post->order_amount_to);
		if($post->order_amount_from && $post->order_amount_to) {
			$query->andWhere(['and', ['>=', 'order_amount', $post->order_amount_from], ['<=', 'order_amount', $post->order_amount_to]]);
		}
		if($post->order_amount_from && (!$post->order_amount_to || ($post->order_amount_to < 0))) {
			$query->andWhere(['>=', 'order_amount', $post->order_amount_from]);
		}
		if(!$post->order_amount_from && ($post->order_amount_to > 0)) {
			$query->andWhere(['<=', 'order_amount', $post->order_amount_to]);
		}
		return $query;
	}
}
