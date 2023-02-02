<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\plugins\connect\xwb;

use yii;
use yii\helpers\Url;

use common\library\Language;

use common\plugins\BaseConnect;
use common\plugins\connect\xwb\SDK;

/**
 * @Id xwb.plugin.php 2018.6.5 $
 * @author mosir
 */

class Xwb extends BaseConnect
{
	/**
	 * 插件网关
	 * @var string $gateway
	 */
	protected $gateway = 'https://api.weibo.com/oauth2/authorize';
	
	/**
	 * 插件实例
	 * @var string $code
	 */
	protected $code = 'xwb';
	
	/**
     * SDK实例
	 * @var object $client
     */
	private $client = null;

	/**
	 * 用户编号
	 * @var int $userid
	 */
	public $userid;
	
	/**
	 * 构造函数
	 */
	public function __construct($params = null)
	{
		parent::__construct($params);
		
		$this->config['redirect_uri'] = $this->getReturnUrl();
	}

	public function login($redirect = true)
	{
		$authorizeUrl = $this->getClient()->getAuthorizeURL();
		if($redirect) {
			return Yii::$app->response->redirect($authorizeUrl);
		}
		return $authorizeUrl;
	}

	public function callback($autobind = false)
	{
		$response = $this->getAccessToken();
		if(!$response) {
			return false;
		}

		// 已经绑定
		if(($userid = parent::isBind($response->unionid))) {
			$this->userid = $userid;
			return true;
		}

		// 没有绑定，自动绑定
		if($autobind) {
			if(($identity = parent::autoBind($this->getUserInfo($response)))) {
				$this->userid = $identity->userid;
				return true;
			}
			return false;
		}

		// 跳转到绑定页面
		return parent::goBind($this->getUserInfo($response));
	}

	/**
	 * 通过CODE获取用户唯一标识
	 */
	public function getAccessToken()
	{
		// APP
		if($this->params->unionid) {
			return $this->params;
		}
		
		if((($response = $this->getClient()->getAccessToken($this->params->code)) == false) || !$response->access_token) {
			$this->errors = Language::get('get_access_token_fail');
			return false;
		}
		if(!$response->unionid) {
			$this->errors = Language::get('unionid_empty');
			return false;
		}

		return $response;
	}
	
	public function getUserInfo($response = null)
	{
		if(($userInfo = $this->getClient()->getUserInfo($response)) != false) {
			//$response->portrait	= '';
			//$response->nickname	= '';
		}
		return $response;
	}
	
	public function getReturnUrl()
	{
		return Url::toRoute(['connect/xwbcallback'], true);
	}

	/**
     * 获取SDK实例
     */
    public function getClient()
    {
        if($this->client === null) {
            $this->client = new SDK($this->config);
        }
        return $this->client;
    }
}