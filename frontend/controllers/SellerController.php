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
use yii\captcha\CaptchaValidator;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

use common\models\UserModel;
use common\models\StoreModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Resource;
use common\library\Page;

/**
 * @Id SellerController.php 2018.4.1 $
 * @author mosir
 */

class SellerController extends \common\controllers\BaseUserController
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

	/**
	 * 排除特定Action外，其他需要登录后访问
	 * @param $action
	 * @var array $extraAction
	 */
	public function beforeAction($action)
    {
		$this->extraAction = ['index', 'login', 'jslang'];
		return parent::beforeAction($action);
    }

    public function actionIndex()
    {
		if(StoreModel::find()->where(['store_id' => $this->visitor['store_id']])->exists()) {
			Yii::$app->session->set('userRole', 'seller');
			return $this->redirect(['my_goods/index']);
		}

		if(Yii::$app->user->isGuest) {
			return $this->redirect(['seller/login']);
		}
			
		return $this->redirect(['apply/index']);
	}

	/**
	 * 商家登录
	 */
	public function actionLogin()
	{
		if (!Yii::$app->user->isGuest && $this->visitor['store_id']) {
			return $this->redirect(['seller/index']);
		}

		if(!Yii::$app->request->isPost) 
		{
			Yii::$app->user->logout();
			
			if(!($redirect = Yii::$app->request->get('redirect'))) {
				$redirect = Url::toRoute('my_goods/index');
			}
			$this->params['redirect'] = $redirect;
			
			$this->params['_foot_tags'] = Resource::import('jquery.plugins/jquery.lazyload.js,jquery.plugins/jquery.validate.js');
			$this->params['page'] = Page::seo(['title' => Language::get('seller_login')]);
			return $this->render('../seller.login.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post(), true, ['rememberMe']);
			$rememberMe = $post->rememberMe ? 3600 * 24 * 30 : 0;// one month
			
			if(isset(Yii::$app->params['captcha_status']['login']) && Yii::$app->params['captcha_status']['login']) {
				$captchaValidator = new CaptchaValidator(['captchaAction' => 'default/captcha']);
				if(!$captchaValidator->validate($post->captcha)) {
					return Message::warning(Language::get('captcha_failed'), ['seller/login']);
				}
			}
			
			if(empty($post->username) || empty($post->password)) {
				return Message::warning(Language::get('username_password_error'), ['seller/login']);
			}
			
			$identity = UserModel::find()->where(['username' => $post->username])->one();
			if(!$identity) {
				return Message::warning(Language::get('username_password_error'), ['seller/login']);
			}
			if(!$identity->validatePassword($post->password)) {
				return Message::warning(Language::get('username_password_error'), ['seller/login']);
			}
			if($identity->locked) {
				return Message::warning(Language::get('userlocked'));
			}

			// 不是卖家
			if(!StoreModel::find()->where(['store_id' => $identity->userid])->exists()) {
				return Message::warning(Language::get('not_seller'));
			}
			
			// 登录用户
			if(!Yii::$app->user->login($identity, $rememberMe)) {
				return Message::warning(Language::get('login_fail'));
			}
			UserModel::afterLogin($identity);
			return Message::display(Language::get('login_successed'), $post->redirect);
		}
	}
}