<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\library;

use yii;

use common\models\WeixinSettingModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Timezone;

/**
 * @Id Weixin.php 2018.8.28 $
 * @author mosir
 */
 
class Weixin
{
	public $userid = 0;
	public $code = 'mp';
	public $config = null;
	public $errors = null;
	
	public function __construct($config = null, $userid = 0, $code = 'mp')
	{
		$this->userid = $userid;
		$this->code  = $code;

		$this->config = ($config !== null) ? $config : WeixinSettingModel::getConfig($userid, $code);
	}
	
	public static function getInstance($config = null, $userid = 0, $code = 'mp')
	{
		return new Weixin($config, $userid, $code);
	}
	
	/* 生成自定义菜单 */
	public function createMenus($data = null)
	{
		if(!($accessToken = $this->getAccessToken())) {
			return false;
		}

		$api = $this->apiList('weixinMenus');
		$param = array('access_token' => $accessToken);
		$response = json_decode(Basewind::curl($this->combineUrl($api, $param), 'post', $data, true));
		if($response->errcode) {
			$this->errors = $response->errmsg;
			return false;
		}
		return $response;
	}
	
	/* 获取access_token */
	public function getAccessToken()
	{
		$api = $this->apiList('AccessToken');
		$param = array('appid' => $this->config['appid'], 'secret' => $this->config['appsecret']);
		
		$response = json_decode(Basewind::curl($this->combineUrl($api, $param)));
		if($response->errcode) {
			$this->errors = $response->errmsg;
			return false;
		}
		return $response->access_token;
	}
	
	/* 微信配置验证 */
	public function valid()
    {
		if($this->config['if_valid']){
			return true;
		}
		
		if(!$this->checkSignature()) {
			$this->errors = Language::get('signature invalid');
			return false;
		}
		if(!($model = WeixinSettingModel::find()->where(['userid' => $this->userid, 'code' => $this->code, 'id' => $this->config['id']])->one())) {
			$this->errors = Language::get('config invalid');
			return false;
		}
		
		$model->if_valid = 1;
		if(!$model->save()) {
			$this->errors = $model->errors;
			return false;
		}
		if(!($echoStr = Yii::$app->request->get('echostr'))) {
			$this->errors = Language::get('echostr empty');
			return false;
		}
		exit($echoStr);
    }
	
	public function checkSignature()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);
		
        if (!($token = $this->config['token'])) {
			$this->errors = Language::get('TOKEN is not defined!');
			return false;
        }

		$tmpArr = array($token, $post->timestamp, $post->nonce);
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode($tmpArr);
		$tmpStr = sha1($tmpStr);
		
		return ($tmpStr == $post->signature) ? true : false;
	}
	
	public function apiList($api = null)
	{
		$list = array(
			'AccessToken' 		=> 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential',
			'weixinMenus' 		=> 'https://api.weixin.qq.com/cgi-bin/menu/create?',
			'userInfo'	  		=> 'https://api.weixin.qq.com/cgi-bin/user/info?',
			'createQrcode' 		=> 'https://api.weixin.qq.com/cgi-bin/qrcode/create?',
			'showQrcode' 		=> 'https://mp.weixin.qq.com/cgi-bin/showqrcode?',
			'getTicket'			=> 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?',
			'getWxaCodeUnlimit' => 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?',
			'getWxaCode' 		=> 'https://api.weixin.qq.com/wxa/getwxacode?',
			'getWxaUrlLink'		=> 'https://api.weixin.qq.com/wxa/generate_urllink?'
		);
		if($api !== null) {
			return isset($list[$api]) ? $list[$api] : '';
		}
		return $list;
	}

	/**
	 * 获取小程序码，没有数量限制
	 * 但是参数scene支持的最大长度是32个字符
	 */
	public function getWxaCodeUnlimit($post = [], $savePath = null)
	{
		if(!($accessToken = $this->getAccessToken())) {
			return false;
		}

		$api = $this->apiList('getWxaCodeUnlimit');
		$param = array('access_token' => $accessToken);
	
		$buffer = Basewind::curl($this->combineUrl($api, $param), 'post', json_encode($post), true);
		$result = json_decode($buffer);
		if($result->errcode) {
			$this->errors = $result->errmsg;
			return false;
		}

		// 保存图片
		if($savePath) {
			if(file_put_contents($savePath, $buffer, LOCK_EX) === false) {
				return false;
			}
			return true;
		}
	
		// 返回的图片Buffer
		return $buffer;
	}

	/**
	 * 获取小程序码，数量限制100000个
	 * 页面路径，最大长度 128 字节
	 */
	public function getWxaCode($post = [], $savePath = null)
	{
		if(!($accessToken = $this->getAccessToken())) {
			return false;
		}

		$api = $this->apiList('getWxaCode');
		$param = array('access_token' => $accessToken);
		
		$buffer = Basewind::curl($this->combineUrl($api, $param), 'post', json_encode($post), true);
		$result = json_decode($buffer);
		if($result->errcode) {
			$this->errors = $result->errmsg;
			return false;
		}

		// 保存图片
		if($savePath) {
			if(file_put_contents($savePath, $buffer, LOCK_EX) === false) {
				return false;
			}
			return true;
		}
	
		// 返回的图片Buffer
		return $buffer;
	}

	/**
	 * 获取小程序 URL Link
	 * 自 2022 年 4 月 11 日起，URL Link有效期最长 30 天，不再支持永久有效的URL Link、不再区分短期有效URL Link与长期有效URL Link。
	 * 若在微信外打开，用户可以在浏览器页面点击进入小程序。每个独立的URL Link被用户访问后，仅此用户可以再次访问并打开对应小程序，
	 * 其他用户无法再次通过相同URL Link打开该小程序
	 */
	public function getWxalink($post = [])
	{
		if(!($accessToken = $this->getAccessToken())) {
			return false;
		}

		$api = $this->apiList('getWxaUrlLink');
		$param = array('access_token' => $accessToken);
		
		if(!($urllink = Basewind::curl($this->combineUrl($api, $param), 'post', json_encode($post), true))) {
			return false;
		}

		return $urllink;
	}
	
	public function combineUrl($url, $param)
	{
		$newParam = array('url' => $url);
		
		if(!empty($param))
		{
			foreach($param as $key => $val)
			{
				$newParam[] = $key.'='.$val;
			}
		}
		return implode('&', $newParam);
	}
	
	/* 获取用户向公众平台发送的信息 */
	public function getPostData()
	{
		$xml = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : file_get_contents("php://input");
		
		// 禁止引用外部xml实体
		libxml_disable_entity_loader(true);
		$data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

		return $data;
	}
	
	/**
	 * 获取文本消息和图文消息XML模板 
	 * 从2018年10月12日起，微信公众平台图文消息被限制为1条
	 */
    public function getMsgXML($ToUserName, $FromUserName, $param) 
	{
		if(empty($param)){
			return false;
		}
		// $param 必须设定为二维数组
		if(is_array($param))
		{
			$resultStr = "<xml>
						 <ToUserName><![CDATA[" . $ToUserName . "]]></ToUserName>
						 <FromUserName><![CDATA[" . $FromUserName . "]]></FromUserName>
						 <CreateTime>" . Timezone::gmtime() . "</CreateTime>
						 <MsgType><![CDATA[news]]></MsgType>
						 <ArticleCount>" . count($param) . "</ArticleCount>
						 <Articles>";
			foreach ($param as $key => $val) 
			{
				$resultStr .= "<item>
							   <Title><![CDATA[" . $val['title'] . "]]></Title> 
							   <Description><![CDATA[" . $val['description'] . "]]></Description>
							   <PicUrl><![CDATA[" . Yii::$app->params['frontendUrl'] . '/' . $val['image'] . "]]></PicUrl>
							   <Url><![CDATA[" . $val['link'] . "]]></Url>
							   </item>";
			}
			$resultStr .= "</Articles></xml>";
		}
		else
		{
			$tpl = "<xml>
			  <ToUserName><![CDATA[%s]]></ToUserName>
			  <FromUserName><![CDATA[%s]]></FromUserName>
			  <CreateTime>%s</CreateTime>
			  <MsgType><![CDATA[text]]></MsgType>
			  <Content><![CDATA[%s]]></Content>
			  </xml>"; 
			$resultStr = sprintf($tpl, $ToUserName, $FromUserName, Timezone::gmtime(), $param);
		}
		
        return $resultStr;
    }
	
	/* 获取微信用户信息 */
	public function getUserInfo($FromUserName = null)
	{
		if(empty($FromUserName)) {
			$this->errors = Language::get('fromUserName empty');
			return false;
		}

		if(!($accessToken = $this->getAccessToken())) {
			return false;
		}

		$api = $this->apiList('userInfo');
		$param = array('access_token' => $accessToken, 'openid' => $FromUserName, 'lang' => 'zh_CN');
		$response = json_decode(Basewind::curl($this->combineUrl($api, $param)));
		if($response->errcode) {
			$this->errors = $response->errmsg;
			return false;
		}
		return $response;
	}
	
	/*  微信分享JSSDK */
	public function getSignPackage($post = null) 
	{
		$jsapiTicket = $this->getJsApiTicket();
	
		$timestamp = Timezone::gmtime();
		$nonceStr = $this->createNonceStr();
	
		// 这里参数的顺序要按照 key 值 ASCII 码升序排序
		$string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$post->url";
		$signature = sha1($string);
	
		$signPackage = array(
		  "appId"     => $this->config['appid'],
		  "nonceStr"  => $nonceStr,
		  "timestamp" => $timestamp,
		  "url"       => $post->url,
		  "signature" => $signature,
		  "rawString" => $string
		);
		return json_encode($signPackage); 
	}
	
	private function createNonceStr($length = 16) 
	{
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$str = "";
		for ($i = 0; $i < $length; $i++) {
		  $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}
		return $str;
	}
	
	private function getJsApiTicket() 
	{
		if(!($accessToken = $this->getAccessToken())) {
			return false;
		}

		$api = $this->apiList('getTicket');
		$param = array('type' => 'jsapi', 'access_token' => $accessToken);

		$response = json_decode(Basewind::curl($this->combineUrl($api, $param)));
		if($response->errcode) {
			$this->errors = $response->errmsg;
			return false;
		}
		return $response->ticket;
	}
}
