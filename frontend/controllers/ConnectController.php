<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace frontend\controllers;

use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

use common\models\BindModel;
use common\models\UserModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Plugin;
use common\library\Timezone;
use common\library\Page;

/**
 * @Id ConnectController.php 2018.5.31 $
 * @author mosir
 */

class ConnectController extends \common\controllers\BaseMallController
{
	/**
	 * 初始化
	 * @var array $view 当前视图
	 * @var array $params 传递给视图的公共参数
	 */
	public function init()
	{
		parent::init();
		$this->view  = Page::setView('mall');
		$this->params = ArrayHelper::merge($this->params, Page::getAssign('user'));
	}

	public function actionIndex()
	{
		if (Yii::$app->user->isGuest) {
			Page::redirect(Yii::$app->request->url);
			return false;
		}

		$connect = Plugin::getInstance('connect')->build();
		$plugins = $connect->getList();

		$binds = array();
		foreach ($plugins as $key => $plugin) {
			if (!in_array($key, ['alipay', 'qq', 'weixin', 'xwb'])) {
				continue;
			}
			$bind = BindModel::find()->select('enabled')->where(['userid' => Yii::$app->user->id, 'code' => $key])->one();
			$binds[] = array('code' => $key, 'name' => $plugin['name'], 'enabled' => $bind->enabled ? 1 : 0);
		}
		$this->params['binds'] = $binds;

		// 当前位置
		$this->params['_curlocal'] = Page::setLocal(Language::get('connect'), Url::toRoute('connect/index'), Language::get('connect_index'));

		// 当前用户中心菜单
		$this->params['_usermenu'] = Page::setMenu('connect', 'connect_index');

		$this->params['page'] = Page::seo(['title' => Language::get('connect_index')]);
		return $this->render('../connect.index.html', $this->params);
	}

	public function actionQq()
	{
		$connect = Plugin::getInstance('connect')->build('qq');
		$connect->login();
	}
	public function actionQqcallback()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);

		$connect = Plugin::getInstance('connect')->build('qq', $post);
		$connect->callback();
		if (!$connect->userid) {
			return Message::warning($connect->errors);
		}

		return $this->doLogin($connect->userid);
	}

	public function actionAlipay()
	{
		$connect = Plugin::getInstance('connect')->build('alipay');
		$connect->login();
	}
	public function actionAlipaycallback()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);

		$connect = Plugin::getInstance('connect')->build('alipay', $post);
		$connect->callback();
		if (!$connect->userid) {
			return Message::warning($connect->errors);
		}

		return $this->doLogin($connect->userid);
	}

	public function actionWeixin()
	{
		// 使用开放平台秘钥
		$connect = Plugin::getInstance('connect')->build('weixinapp');
		$connect->login();
	}
	public function actionWeixincallback()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);

		$connect = Plugin::getInstance('connect')->build('weixinapp', $post);
		$connect->callback();
		if (!$connect->userid) {
			return Message::warning($connect->errors);
		}

		return $this->doLogin($connect->userid);
	}

	public function actionXwb()
	{
		$connect = Plugin::getInstance('connect')->build('xwb');
		$connect->login();
	}
	public function actionXwbcallback()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);

		$connect = Plugin::getInstance('connect')->build('xwb', $post);
		$connect->callback();
		if (!$connect->userid) {
			return Message::warning($connect->errors);
		}

		return $this->doLogin($connect->userid);
	}

	public function actionBind()
	{
		$token = Basewind::trimAll(Yii::$app->request->get('token'), true);
		$bind = Basewind::trimAll(json_decode(base64_decode($token), true), true);

		if (!isset($bind->unionid) || !$bind->unionid) {
			return Message::warning(Language::get('session_expire'));
		}
		// 进入绑定界面，10分钟有效期 
		if (!isset($bind->expire_time) || ($bind->expire_time < Timezone::gmtime())) {
			return Message::warning(Language::get('session_expire'));
		}

		// 绑定当前登录的账户
		if (!Yii::$app->user->isGuest) {
			// 将绑定信息插入数据库
			if (BindModel::bindUser($bind, Yii::$app->user->id) == false) {
				return Message::warning(Language::get('bind_fail'));
			}

			return Message::display(Language::get('bind_ok'), ['connect/index']);
		}
		// 绑定指定的账户（只考虑手机绑定，不考虑邮箱绑定）
		else {
			if (!Yii::$app->request->isPost) {
				$this->params['bind'] = ArrayHelper::toArray($bind);

				$this->params['page'] = Page::seo(['title' => Language::get('connect_bind')]);
				return $this->render('../connect.bind.html', $this->params);
			} else {
				$post = Basewind::trimAll(Yii::$app->request->post(), true);

				if (!Basewind::isPhone($post->phone_mob)) {
					return Message::warning(Language::get('phone_mob_invalid'));
				}

				if (Yii::$app->session->get('phone_code') != md5($post->phone_mob . $post->code)) {
					return Message::warning(Language::get('phone_code_check_failed'));
				} elseif (Yii::$app->session->get('last_send_time_phone_code') + 120 < Timezone::gmtime()) {
					return Message::warning(Language::get('phone_code_check_failed'));
				}

				// 检查手机号是否被注册过（如注册过，绑定该用户）
				// 如果是绑定新用户，则执行注册 
				if (!($user = UserModel::find()->where(['phone_mob' => $post->phone_mob])->one())) {
					do {
						$model = new \frontend\models\UserRegisterForm();
						if ($bind->nickname) {
							$model->username = Basewind::checkUser($bind->nickname) ? $bind->nickname : UserModel::generateName($bind->nickname);
						} else $model->username = UserModel::generateName($bind->code);
						$model->password  = mt_rand(1000, 9999);
						$model->phone_mob = $post->phone_mob;
						$user = $model->register(['real_name' => $bind->nickname, 'portrait' => $bind->portrait]);
					} while (!$user);
				}

				// 将绑定信息插入数据库
				if (BindModel::bindUser($bind, $user->userid) == false) {
					return Message::warning(Language::get('bind_fail'));
				}

				// 退出绑定模式 
				Yii::$app->session->remove('phone_code');
				Yii::$app->session->remove('last_send_time_phone_code');

				if (!Yii::$app->getUser()->login($user)) {
					return Message::display(Language::get('login_fail'));
				}
				return Message::display(Language::get('login_successed'), ['user/index']);
			}
		}
	}

	public function actionRelieve()
	{
		if (Yii::$app->user->isGuest) {
			return Message::warning(Language::get('login_please'));
		}

		$post = Basewind::trimAll(Yii::$app->request->get(), true);
		if (!in_array($post->code, array('qq', 'weixin', 'alipay', 'xwb'))) {
			return Message::warning(Language::get('unbind_fail'));
		}
		if (!BindModel::updateAll(['enabled' => 0], ['userid' => Yii::$app->user->id, 'code' => $post->code])) {
			return Message::warning(Language::get('unbind_fail'));
		}
		return Message::display(Language::get('unbind_ok'));
	}

	protected function doLogin($userid, $redirect = null)
	{
		$identity = UserModel::findOne($userid);

		if ($identity->locked) {
			return Message::warning(Language::get('userlocked'));
		}

		// 登录用户
		if (!Yii::$app->user->login($identity)) {
			return Message::warning(Language::get('login_fail'));
		}
		UserModel::afterLogin($identity);

		return Message::display(Language::get('login_successed'), $redirect ? $redirect : ['user/index']);
	}

	/* 三级菜单 */
	public function getUserSubmenu()
	{
		$submenus =  array(
			array(
				'name'  => 'connect_index',
				'url'   => Url::toRoute('connect/index'),
			),
		);

		return $submenus;
	}
}
