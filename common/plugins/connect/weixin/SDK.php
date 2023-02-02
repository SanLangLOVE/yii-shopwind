<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\plugins\connect\weixin;

use yii;

use common\library\Basewind;
use common\library\Language;

/**
 * @Id SDK.php 2018.6.6 $
 * @author mosir
 */

class SDK
{
	/**
	 * 插件网关
	 * @param string $gateway
	 */
	public $gateway = 'https://open.weixin.qq.com/connect/oauth2/authorize';

	/**
	 * 商户ID
	 * @param string $appId
	 */
	public $appId = null;

	/**
	 * 商户key
	 * @param string $appKey
	 */
	public $appKey = null;

	/**
	 * 返回地址
	 * @param string $redirect_uri
	 */
	public $redirect_uri = null;

	/**
	 * 抓取错误
	 */
	public $errors;

	/**
	 * 构造函数
	 */
	public function __construct(array $config)
	{
		foreach ($config as $key => $value) {
			$this->$key = $value;
		}
	}

	public function getAccessToken($code = '')
	{
		$response = json_decode(Basewind::curl($this->getOpenIdUrl($code)));
		if ($response->errcode) {
			$this->errors = $response->errmsg;
			return false;
		}

		if (!isset($response->unionid)) {
			$response->unionid = $response->openid;
		}

		return $response;
	}

	public function getUserInfo($resp = null)
	{
		if (!$resp->access_token) {
			$this->errors = Language::get('access_token_empty');
			return false;
		}

		$url = "https://api.weixin.qq.com/sns/userinfo?access_token=" . $resp->access_token . "&openid=" . $resp->openid;
		$response = json_decode(Basewind::curl($url));
		if ($response->errcode) {
			$this->errors = $response->errmsg;
			return false;
		}

		return $response;
	}

	public function getAuthorizeURL()
	{
		return $this->gateway . '?appid=' . $this->appId .
			'&redirect_uri=' . $this->redirect_uri .
			'&response_type=code&scope=snsapi_userinfo&state=' . mt_rand() . '#wechat_redirect';
	}

	public function getOpenIdUrl($code = '')
	{
		$gateway = 'https://api.weixin.qq.com/sns/oauth2/access_token';
		return $gateway . '?appid=' . $this->appId . '&secret=' . $this->appKey . '&code=' . $code . '&grant_type=authorization_code';
	}
}
