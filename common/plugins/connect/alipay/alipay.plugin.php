<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\plugins\connect\alipay;

use yii;
use yii\helpers\Url;

use common\library\Basewind;
use common\library\Language;

use common\plugins\BaseConnect;
use common\plugins\connect\alipay\SDK;

/**
 * @Id alipay.plugin.php 2018.6.3 $
 * @author mosir
 */

class Alipay extends BaseConnect
{
	/**
	 * 插件网关
	 * @var string $gateway
	 */
	protected $gateway = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm';

	/**
	 * 插件实例
	 * @var string $code
	 */
	protected $code = 'alipay';

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

		// 更换为移动端的秘钥（因为授权域名不同，需要用不同的秘钥）
		$fields = ['appId', 'rsaPublicKey', 'rsaPrivateKey', 'alipayrsaPublicKey', 'signType'];
		if(Basewind::getCurrentApp() == 'wap') 
		{
			foreach($fields as $key) {
				$this->config[$key] = $this->config[$key.'_wap'];
				unset($this->config[$key.'_wap']);
			}
			
		} else {
			foreach($fields as $key) {
				unset($this->config[$key.'_wap']);
			}
		}
	}

	public function login($redirect = true)
	{
		$authorizeUrl = $this->getAuthorizeURL();
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

		if((($response = $this->getClient()->getAccessToken($this->params->auth_code)) == false) || !$response->access_token) {
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
			$response->portrait	= $userInfo->avatar;
			$response->nickname = $userInfo->nick_name;
		}
		return $response;
	}
	
	public function getReturnUrl()
	{
		// for API
		if($this->params->callback) {
			return $this->params->callback;
		}
		
		return urlencode(Url::toRoute(['connect/alipaycallback'], true));
	}

	/**
	 * 参阅：https://opendocs.alipay.com/open/284
	 */
	public function getAuthorizeURL()
	{
		$url = $this->gateway.'?app_id='.$this->config['appId'].'&scope=auth_user&redirect_uri='.$this->config['redirect_uri'].'&state='.mt_rand();
		if(in_array(Basewind::getCurrentApp(), ['api'])) {
			$url = 'alipays://platformapi/startapp?appId=20000067&url='.urlencode($url);
		}
		
		return $url;
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