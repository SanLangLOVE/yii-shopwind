<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\plugins\connect\weixinapp;

use yii;

/**
 * @Id SDK.php 2018.6.6 $
 * @author mosir
 */

class SDK extends \common\plugins\connect\weixin\SDK
{
	public $gateway = 'https://open.weixin.qq.com/connect/qrconnect';

	public function getAuthorizeURL()
	{
		return $this->gateway . '?appid=' . $this->appId .
			'&redirect_uri=' . $this->redirect_uri .
			'&response_type=code&scope=snsapi_login&state=' . mt_rand() . '#wechat_redirect';
	}
}
