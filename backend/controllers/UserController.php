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
use yii\captcha\CaptchaValidator;
use yii\helpers\Url;
use yii\helpers\Json;

use common\models\UserModel;
use common\models\UserPrivModel;
use common\models\UserEnterModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Resource;
use common\library\Timezone;
use common\library\Page;

/**
 * @Id UserController.php 2018.7.25 $
 * @author mosir
 */

class UserController extends \common\controllers\BaseAdminController
{
	/**
	 * 初始化
	 */
	public function init()
	{
		parent::init();
	}
	
	public function beforeAction($action)
    {
		$this->extraAction = ['login', 'logout', 'jslang'];
		return parent::beforeAction($action);
    }
	
    public function actionIndex() 
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['limit', 'page']);
		
		if(!Yii::$app->request->isAjax) 
		{
			$this->params['filtered'] = $this->getConditions($post);
			$this->params['page'] = Page::seo(['title' => Language::get('user_list')]);
			return $this->render('../user.index.html', $this->params);
		}
		else
		{
			$query = UserModel::find()->select('userid,username,nickname,email,phone_mob,create_time,last_login,logins,last_ip');
			$query = $this->getConditions($post, $query)->orderBy(['userid' => SORT_ASC]);
			
			$page = Page::getPage($query->count(), $post->limit ? $post->limit : 10);

			$list = $query->offset($page->offset)->limit($page->limit)->asArray()->all();
			foreach ($list as $key => $value)
			{
				$list[$key]['if_admin'] = UserPrivModel::find()->select('privs')->where(['userid' => $value['userid'], 'store_id' => 0])->scalar() ? true : false;
				$list[$key]['create_time']= Timezone::localDate('Y-m-d', $value['create_time']);
				$list[$key]['last_login'] = Timezone::localDate('Y-m-d H:i:s', $value['last_login']);
			}
		
			return Json::encode(['code' => 0, 'msg' => '', 'count' => $query->count(), 'data' => $list]);
		}
	}
	
	public function actionAdd()
	{
		if(!Yii::$app->request->isPost)
		{
			$this->params['user'] = array('gender' => 0);
			$this->params['page'] = Page::seo(['title' => Language::get('user_add')]);
			return $this->render('../user.form.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post(), true, ['gender', 'imforbid', 'locked']);
			
			$model = new \backend\models\UserForm();
			if(!($user = $model->save($post, true))) {
				return Message::warning($model->errors);
			}
			return Message::display(Language::get('add_ok'), ['user/index']);
		}
	}
	
	public function actionEdit()
	{
		$id = intval(Yii::$app->request->get('id'));
		
		if(!Yii::$app->request->isPost)
		{
			$this->params['user'] = UserModel::find()
				->select('userid,username,nickname,real_name,email,phone_mob,gender,im_qq,locked,portrait')
				->where(['userid' => $id])->asArray()->one();
			
			$this->params['page'] = Page::seo(['title' => Language::get('user_edit')]);
			return $this->render('../user.form.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post(), true, ['gender', 'imforbid', 'locked']);
			
			$model = new \backend\models\UserForm(['userid' => $id]);
			if(!($user = $model->save($post, true))) {
				return Message::warning($model->errors);
			}
			return Message::display(Language::get('edit_ok'), ['user/index']);
		}
	}
	
	public function actionDelete()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);
		
		if(empty($post->id)) {
			return Message::warning(Language::get('no_user_to_drop'));
		}
		if(UserPrivModel::isManager($post->id)) {
			return Message::warning(Language::get('cannot_drop_admin'));
		}

		$model = new \backend\models\UserDeleteForm(['userid' => $post->id]);
		if(!$model->delete($post, true)) {
			return Message::warning($model->errors);
		}
		if(in_array(Yii::$app->user->id, explode(',', $post->id))) {
			Yii::$app->user->logout();
		}
		return Message::display(Language::get('drop_ok'), ['user/index']);
	}
	
	/**
	 * 导出数据
	 */
	public function actionExport()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);
		if($post->id) $post->id = explode(',', $post->id);
		
		$query = UserModel::find()->select('userid,username,real_name,phone_mob,email,im_qq,create_time,last_login,last_ip,logins')
			->orderBy(['userid' => SORT_ASC]);
		if(!empty($post->id)) {
			$query->andWhere(['in', 'userid', $post->id]);
		}
		else {
			$query = $this->getConditions($post, $query)->limit(100);
		}
		if($query->count() == 0) {
			return Message::warning(Language::get('no_data'));
		}
		return \backend\models\UserExportForm::download($query->asArray()->all());		
	}

    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }
		
		if(!Yii::$app->request->isPost)
		{
			$this->params['page'] = Page::seo(['title' => Language::get('user_login')]);
			return $this->render('../login.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post(), true);
			
			if(isset(Yii::$app->params['captcha_status']['backend']) && Yii::$app->params['captcha_status']['backend']) {
				$captchaValidator = new CaptchaValidator(['captchaAction' => 'default/captcha']);
				if(!$captchaValidator->validate($post->captcha)) {
					return Message::warning(Language::get('captcha_failed'));
				}
			}
			
			if(empty($post->username) || empty($post->password)) {
				return Message::warning(Language::get('username_password_error'));
			}
			
			$identity = UserModel::find()->where(['username' => $post->username])->one();
			if(!$identity) {
				return Message::warning(Language::get('username_password_error'));
			}
			if(!$identity->validatePassword($post->password)) {
				return Message::warning(Language::get('username_password_error'));
			}
			if(!UserPrivModel::isManager($identity->userid)) {
				return Message::warning(Language::get('not_admin'));
			}
			if(!Yii::$app->user->login($identity)) {
				return Message::warning(Language::get('login_failure'));
			}
			UserModel::afterLogin($identity);
			return Message::display(Language::get('login_successed'), ['default/index']);
		}
    }
	
	public function actionEnter()
	{
		if(!Yii::$app->request->isAjax) 
		{
			$this->params['page'] = Page::seo(['title' => Language::get('user_enter')]);
			return $this->render('../user.enter.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->get(), true, ['id', 'limit', 'page']);
			
			$query = UserEnterModel::find()->select('id,userid,username,ip,address,add_time')->where(['userid' => $post->id]);
			$page = Page::getPage($query->count(), $post->limit ? $post->limit : 10);
			
			$list = $query->offset($page->offset)->limit($page->limit)->asArray()->all();
			foreach ($list as $key => $value) {
				$list[$key]['add_time'] = Timezone::localDate('Y-m-d H:i:s', $value['add_time']);
			}

			return Json::encode(['code' => 0, 'msg' => '', 'count' => $query->count(), 'data' => $list]);
		}
	}
	
	public function actionLogout()
    {
        Yii::$app->user->logout();
        return $this->redirect(Yii::$app->user->loginUrl);
    }
	
	/* 新增用户走势（图表）本月和上月的数据统计 */
	public function actionTrend()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);
		
		list($curMonthQuantity, $curDays, $beginMonth, $endMonth) = $this->getMonthTrend(Timezone::gmtime());
		list($preMonthQuantity, $preDays) = $this->getMonthTrend($beginMonth - 1);
		
		$series = array($curMonthQuantity, $preMonthQuantity);
		$legend = array('本月新增用户数','上月用户数');
		
		$days = $curDays > $preDays ? $curDays : $preDays;
		
		// 获取日期列表
		$xaxis = array();
		for($day = 1; $day <= $days; $day++) {
			$xaxis[] = $day;
		}

		$this->params['echart'] = array(
			'id'		=>  mt_rand(),
			'theme' 	=> 'macarons',
			'width'		=> '100%',
			'height'    => 360,
			'option'  	=> json_encode([
				'grid' => ['left' => '20', 'right' => '20', 'top' => '80', 'bottom' => '20', 'containLabel' => true],
				'tooltip' 	=> ['trigger' => 'axis'],
				'legend'	=> [
					'data' => $legend
				],
				'calculable' => true,
   				'xAxis' => [
        			[
						'type' => 'category', 
						'data' => $xaxis
        			]
    			],
				'yAxis' => [
        			[
            			'type' => 'value'
        			]
   				 ],
				 'series' => [
					[
						'name' => $legend[0],
						'type' => 'bar',
						'data' => $series[0],
					],
					[
						'name' => $legend[1],
						'type' => 'bar',
						'data' => $series[1],
					]
				]
			])
		);
		
		return $this->render('../echarts.html', $this->params);
	}
	
	/* 月数据统计 */
	private function getMonthTrend($month = 0)
	{
		// 本月
		if(!$month) $month = Timezone::gmtime();
		
		// 获取当月的开始时间戳和结束那天的时间戳
		list($beginMonth, $endMonth) = Timezone::getMonthDay(Timezone::localDate('Y-m', $month));
		
		$list = UserModel::find()->select('create_time')->where(['>=', 'create_time', $beginMonth])->andWhere(['<=', 'create_time', $endMonth])->asArray()->all();
		
		// 该月有多少天
		$days = round(($endMonth-$beginMonth) / (24 * 3600));
		
		// 按天算归类
		$amount = $quantity = array();
		foreach($list as $key => $val)
		{
			$day = Timezone::localDate('d', $val['create_time']);
	
			if(isset($quantity[$day-1])) {
				$quantity[$day-1]++;
			}
			else {
				$quantity[$day-1] = 1;
			}
		}
		
		// 给天数补全
		for($day = 1; $day <= $days; $day++)
		{
			if(!isset($quantity[$day-1])) {
				$quantity[$day-1] = 0;
			}
		}
		// 按日期顺序排序
		ksort($quantity);

		return array($quantity, $days, $beginMonth, $endMonth);
	}
	
	private function getConditions($post, $query = null)
	{
		if($query === null) {
			foreach(array_keys(ArrayHelper::toArray($post)) as $field) {
				if(in_array($field, ['username', 'nickname', 'real_name', 'email', 'phone_mob'])) {
					return true;
				}
			}
			return false;
		}
		if($post->username) {
			$query->andWhere(['username' => $post->username]);
		}
		if($post->nickname) {
			$query->andWhere(['nickname' => $post->nickname]);
		}
		if($post->real_name) {
			$query->andWhere(['real_name' => $post->real_name]);
		}
		if($post->email) {
			$query->andWhere(['email' => $post->email]);
		}
		if($post->phone_mob) {
			$query->andWhere(['phone_mob' => $post->phone_mob]);
		}
		return $query;
	}
}