<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\plugins;

use yii;

use common\models\BindModel;
use common\models\UserModel;

use common\library\Language;
use common\library\Timezone;

/**
 * @Id BaseConnect.php 2018.6.1 $
 * @author mosir
 */

class BaseConnect extends BasePlugin
{
	/**
	 * 第三方登录插件系列
	 * @var string $instance 
	 */
	protected $instance = 'connect';

	/**
	 * 检测账号是否绑定过
	 * @param string $unionid
	 */
	public function isBind($unionid = '', $openid = '')
	{
		// 不要限制CODE，因为对于微信来说，CODE会有多个(weixin,weixinmp,weixinapp)
		$bind = BindModel::find()->select('unionid,userid,enabled')->where(['unionid' => $unionid/*, 'code' => $this->code*/])->one();

		// 考虑已登录状态下绑定的情况，如果当前登录用户与原有绑定用户不一致，则修改为新绑定（同时删除旧绑定）
		if ($bind && $bind->userid && $bind->enabled && (Yii::$app->user->isGuest || ($bind->userid == Yii::$app->user->id))) {
			// 如果该unionid已经绑定， 则检查该用户是否存在
			if (!UserModel::find()->where(['userid' => $bind->userid])->exists()) {
				// 如果没有此用户，则说明绑定数据过时，删除绑定
				BindModel::deleteAll(['userid' => $bind->userid]);
				$this->setErrors(Language::get('bind_data_error'));
				return false;
			}

			// 没有该场景的登录记录，则添加(目的是保存不同登录场景的openid值，以便其他业务使用)
			if ($openid && !BindModel::find()->where(['openid' => $openid, 'code' => $this->code])->exists()) {
				$bind->openid = $openid;
				$bind->code = $this->code;
				$this->createBind($bind, $bind->userid);
			}

			return $bind->userid;
		}
		return false;
	}

	/**
	 * PC端跳转至绑定页面（绑定手机）
	 * @param object $response
	 */
	public function goBind($response = null)
	{
		$result = array(
			'code' 			=> $this->code,
			'unionid' 		=> $response->unionid,
			'expire_time' 	=> Timezone::gmtime() + 600,
			'access_token' 	=> $response->access_token,
			'refresh_token' => $response->refresh_token,
			'portrait'		=> isset($response->portrait) ? $response->portrait : null,
			'nickname'		=> isset($response->nickname) ? $response->nickname : null
		);
		$this->setErrors('redirect...'); // 防止执行后续业务逻辑
		return Yii::$app->controller->redirect(['connect/bind', 'token' => base64_encode(json_encode($result))]);
	}

	/**
	 * 不跳转，自动绑定
	 */
	public function autoBind($response = null)
	{
		if ($response) {
			$response->code = $this->code;
		}
		return $this->createUser($response, true);
	}

	/**
	 * 创建用户
	 * @param object $post
	 * @param boolean $third 是否为第三方账号登录（如微信登录等）
	 */
	protected function createUser($post, $third = false)
	{
		$model = new \frontend\models\UserRegisterForm();

		do {
			$model->username = UserModel::generateName($post->code);
			$model->password  = mt_rand(1000, 9999);
			$model->phone_mob = $post->phone_mob ? $post->phone_mob : '';
			$user = $model->register(['portrait' => $post->portrait ? $post->portrait : '', 'nickname' => $post->nickname ? $post->nickname : '']);
		} while (!$user);

		if ($third && !$this->createBind($post, $user->userid)) {
			return false;
		}

		return $user;
	}

	/**
	 * 第三方账户绑定【未登录/登录后】
	 * @desc 微信/支付宝/QQ等
	 */
	public function createBind($bind, $userid)
	{
		// 将绑定信息插入数据库
		if (BindModel::bindUser($bind, $userid) == false) {
			return false;
		}
		return true;
	}
}