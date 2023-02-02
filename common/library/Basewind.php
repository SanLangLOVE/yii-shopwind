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
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;

use common\models\UserModel;
use common\models\StoreModel;
use common\models\DistributeSettingModel;
use common\models\WebimModel;

use common\library\Language;
use common\library\Timezone;
use common\library\Def;
use common\library\Plugin;
use common\library\Mailer;

/**
 * @Id Basewind.php 2018.3.2 $
 * @author mosir
 */

class Basewind
{
	/**
	 * 获取当前系统版本
	 * @return string 
	 */
	public static function getVersion()
	{
		return '3.4.3';
	}

	/**
	 * 环境检测/环境初始化/安装检测等，如果没有安装，跳转到安装界面
	 * @param bool $toInstall 如果没有安装，是否跳转至安装程序
	 */
	public static function environment($toInstall = true)
	{
		// 安装检测
		if (self::isInstall()) {

			// 如果关闭了站点
			if (Basewind::getCurrentApp() != 'admin' && !Yii::$app->params['site_status']) {
				Yii::$app->response->redirect(['site/closed'])->send();
				exit();
			}
		} elseif ($toInstall) {
			// 没有安装站点，跳转安装（目前存在情况：无法在其他应用中跳转到前台的安装页面）
			Yii::$app->response->redirect(['install/index'])->send();
			exit();
		}
	}

	/**
	 * 安装检测
	 * @return bool	 
	 */
	public static function isInstall()
	{
		$file = Yii::getAlias('@frontend') . '/web/data/install.lock';

		// 已经安装了网站
		if (file_exists($file)) {
			return true;
		}
		return false;
	}

	/**
	 * 获取当前运行的应用程序名（充分考虑了即使修改应用程序文件夹名也不受影响） 
	 * 如果找不到匹配的，默认PC端
	 * @return string
	 */
	public static function getCurrentApp()
	{
		// 当前应用程序名
		$current = substr(Yii::getAlias('@app'), strripos(Yii::getAlias('@app'), DIRECTORY_SEPARATOR) + 1);

		// 允许的应用程序列表
		$list = ['backend' => 'admin', 'frontend' => 'pc', 'mobile' => 'wap'];
		foreach($list as $key => $value) {
			$app = substr(Yii::getAlias('@'.$key), strripos(Yii::getAlias('@'.$key), '/') + 1);
			if($app == $current) {
				return $value;
			}
		}
		return 'pc';
	}

	/**
	 * 给视图调用的用户登录信息
	 * @return array $visitor
	 */
	public static function getVisitor()
	{
		$visitor = null;

		if (Yii::$app->user->isGuest) {
			$visitor = ['userid' => 0, 'username' => Language::get('guest'), 'store_id' => 0, 'portrait' => Yii::$app->params['default_user_portrait']];
		} else {
			$identity = Yii::$app->user->identity;
			if (!$identity->portrait) $identity->portrait = Yii::$app->params['default_user_portrait'];

			// 去掉敏感字段
			unset($identity->password, $identity->password_reset_token, $identity->auth_key);

			// 此处不要限制state=1（会对申请开店取值有影响）
			$visitor = ArrayHelper::merge(ArrayHelper::toArray($identity), [
				'store_id' => StoreModel::find()->select('store_id')->where(['and', ['store_id' => Yii::$app->user->id], ['!=', 'state', Def::STORE_APPLYING]])->scalar()
			]);

			$visitor['unread'] = intval(WebimModel::find()->where(['toid' => Yii::$app->user->id, 'unread' => 1])->sum('unread'));
		}

		// 分销功能，此处抓取访客的邀请并保存
		DistributeSettingModel::saveInvites();

		return $visitor;
	}

	/**
	 * 检测用户名是否存在
	 * @param string $username 要检测的用户名
	 * @param int $userid 是否排除指定用户
	 */
	public static function checkUser($username = '', $userid = 0)
	{
		if (!$username) return false;
		$query = UserModel::find()->where(['username' => $username]);
		if ($userid) $query->andWhere(['<>', 'userid', $userid]);
		if ($query->exists()) {
			return false;
		}
		return true;
	}

	/**
	 * 判断是否为正确的电子邮件地址
	 * @param string $email
	 */
	public static function isEmail($email = '')
	{
		if (!$email) return false;
		$chars = "/^([a-z0-9+_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,5}\$/i";
		if (strpos($email, '@') !== false && strpos($email, '.') !== false) {
			if (preg_match($chars, $email)) {
				return true;
			}
			return false;
		}
		return false;
	}

	/**
	 * 检测电子邮件地址是否存在
	 * @param string $email 要检测的电子邮件地址
	 * @param int $userid 是否排除指定用户
	 */
	public static function checkEmail($email = '', $userid = 0)
	{
		if (!$email) return false;
		$query = UserModel::find()->where(['email' => $email]);
		if ($userid) $query->andWhere(['<>', 'userid', $userid]);
		if ($query->exists()) {
			return false;
		}
		return true;
	}

	/**
	 * 判断是否为正确的手机号
	 * @param string $phone
	 */
	public static function isPhone($phone = '')
	{
		if (!$phone) return false;
		if (preg_match("/^[1][3456789][0-9]{9}$/", $phone)) {
			return true;
		}
		return false;
	}

	/**
	 * 检测手机号是否可用，已存在返回不可用
	 * @param string $phone 要检测的手机号
	 * @param int $userid 是否排除指定用户
	 */
	public static function checkPhone($phone = '', $userid = 0)
	{
		if (!$phone) return false;
		$query = UserModel::find()->where(['phone_mob' => $phone]);
		if ($userid) $query->andWhere(['<>', 'userid', $userid]);
		if ($query->exists()) {
			return false;
		}
		return true;
	}

	/**
	 * 仅用于AJAX提交的验证码验证，请不要用于FORM提交的验证码验证 
	 * @param string $captcha
	 */
	public static function checkCaptcha($captcha = '')
	{
		$code = Yii::$app->controller->createAction('captcha')->getVerifyCode();
		if (strcasecmp($captcha, $code) === 0) { // 忽略大小写
			return true;
		}
		return false;
	}

	/**
	 * 数组转对象（并去掉字符串前后空格） 
	 * @param array|string|int $params
	 * @param bool $toObject 是否转成对象
	 * @param array $intvalFields 需要将$params中哪些字段的值转成整型
	 */
	public static function trimAll($params = null, $toObject = false, $intvalFields = array())
	{
		if (!is_array($params)) {
			if ($intvalFields === true) {
				return intval($params);
			} elseif (is_null($params) && $toObject == true) {
				return (object) $params;
			}
			return trim($params);
		}

		foreach ($params as $k => $v) {
			if (is_string($v)) {
				$params[$k] = (in_array($k, $intvalFields) ? intval($v) : trim($v));
			} elseif (is_array($v) || is_object($v)) {
				$params[$k] = self::trimAll($v, $toObject);
			}
		}
		return $toObject ? (object)$params : $params;
	}

	/**
	 * 如果是数组，取第一条信息
	 * @param array|string $message
	 */
	public static function getFirstLine($message)
	{
		if (!is_array($message)) return $message;
		return self::getFirstLine(current($message));
	}

	/**
	 * 发送邮件需要用到邮件模板，模板中的变量需要兼容smarty解析，故先生成html视图，才能解析变量 
	 * @param string $view
	 * @param string $key
	 * @param string $template
	 */
	public static function createMailFile($view, $key, $template = 'mailtemplate')
	{
		$array = include(Yii::getAlias('@common') . '/mail/' . $template . '/' . $view . '.php');

		$path = dirname(Yii::getAlias('@runtime') . '/mail/' . $template . '/cache/' . $key);
		$file = $path . "/{$view}_{$key}.html";
		if (!file_exists($file)) {
			FileHelper::createDirectory($path);
			file_put_contents($file, $array[$key]);
		}
		return $file;
	}

	/**
	 * 获取站内信发送内容（暂时用Mail的模板） 
	 * @param string $view
	 * @param array $params
	 */
	public static function getPmer($view, $params = [])
	{
		$params = ArrayHelper::merge([
			'site_url'  => Yii::$app->params['frontendUrl'],
			'site_name' => Yii::$app->params['site_name'],
			'send_time' => Timezone::localDate('Y-m-d H:i:s', Timezone::gmtime())
		], $params);

		$subject = Yii::$app->controller->renderFile(self::createMailFile($view, 'subject'), $params);
		$content = Yii::$app->controller->renderFile(self::createMailFile($view, 'content'), $params);

		$model = new \frontend\models\PmSendForm();
		$model->title = $subject;
		$model->content = $content;

		return $model;
	}

	/**
	 * 获取邮件发送对象 
	 * @param string $view
	 * @param array $params
	 */
	public static function getMailer($view, $params = [])
	{
		if (!isset(Yii::$app->params['mailer']) || empty(Yii::$app->params['mailer'])) {
			return false;
		}

		$params = ArrayHelper::merge([
			'site_url'  => Yii::$app->params['frontendUrl'],
			'site_name' => Yii::$app->params['site_name'],
			'send_time' => Timezone::localDate('Y-m-d H:i:s', Timezone::gmtime())
		], $params);

		$subject = Yii::$app->controller->renderFile(self::createMailFile($view, 'subject'), $params);
		$content = Yii::$app->controller->renderFile(self::createMailFile($view, 'content'), $params);

		try {
		    $mailer = new Mailer();
		    return $mailer->compose()->setSubject($subject)->setHtmlBody($content);
		} catch(\Exception $exception) {
		    //print_r($exception->getMessage());
		    return false;
		}
	}

	/**
	 * 针对订单通知的邮件发送和短信发送 
	 * @param array $order 订单信息
	 * @param array $mail 邮件内容体
	 * @param array $sms 短信内容体
	 */
	public static function sendMailMsgNotify($order = [], $mail = [], $sms = [])
	{
		// 发送邮件提醒
		if ($mail) {
			$receiver = isset($mail['receiver']) ? intval($mail['receiver']) : $order['seller_id'];
			$toEmail = UserModel::find()->select('email')->where(['userid' => $receiver])->scalar();

			$mailer = self::getMailer($mail['key'], ['order' => $order]);
			if (!$mailer || !$toEmail) return false;

			$mailer->setTo($toEmail)->send();
		}

		// 发送短信提醒
		if ($sms) {
			$smser = Plugin::getInstance('sms')->autoBuild();
			if (!$smser) return false;

			$smser->sender = isset($sms['sender']) ? intval($sms['sender']) : $order['seller_id'];
			$smser->receiver = isset($sms['receiver']) ? $sms['receiver'] : null;
			$smser->scene = $sms['key'];
			$smser->templateParams = $order;
			$smser->send();
		}
	}

	/**
	 * 判断是否为微信客户端
	 * @return bool
	 */
	public static function isWeixin()
	{
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
			return true;
		}
		return false;
	}

	/**
	 * 判断是否为QQ内置浏览器
	 * @return bool
	 */
	public static function isQqbrowser()
	{
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'QQ') !== false) {
			return true;
		}
		return false;
	}

	/**
	 * 判断是否为支付宝客户端
	 * @return bool
	 */
	public static function isAlipay()
	{
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient') !== false) {
			return true;
		}
		return false;
	}

	/**
	 * 判断是否为头条小程序端
	 * @return bool
	 */
	public static function isToutiao()
	{
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'ToutiaoMicroApp') !== false) {
			return true;
		}
		return false;
	}

	/**
	 * 判断是否为移动设备
	 * @return bool
	 */
	public static function isMobileDevice()
	{
		// 如果有HTTP_X_WAP_PROFILE则一定是移动设备
		if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
			return true;
		}
		// 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
		if (isset($_SERVER['HTTP_VIA'])) {
			// 找不到为flase,否则为true
			return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
		}
		// 脑残法，判断手机发送的客户端标志,兼容性有待提高
		if (isset($_SERVER['HTTP_USER_AGENT'])) {
			$clientkeywords = array('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
			// 从HTTP_USER_AGENT中查找手机浏览器的关键字
			if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
				return true;
			}
		}
		// 协议法，因为有可能不准确，放到最后判断
		if (isset($_SERVER['HTTP_ACCEPT'])) {
			// 如果只支持wml并且不支持html那一定是移动设备
			// 如果支持wml和html但是wml在html之前则是移动设备
			if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
				return true;
			}
		}
		return false;
	}

	/**
	 * 发送CURL请求
	 * @param string $url 发送地址
	 * @param string $method get|post
	 * @param string $postJSON 是否为JSON提交
	 * @param string $cacert_url 证书地址
	 */
	public static function curl($url, $method = 'GET', $post = '', $postJSON = false, $cacert_url = '')
	{
		//初始化curl
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		if ($cacert_url) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); //SSL证书认证
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); //严格认证
			curl_setopt($ch, CURLOPT_CAINFO, $cacert_url); //证书地址
		}

		// 以JSON数组的形式提交
		if ($postJSON == true) {
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Content-Type: application/json; charset=utf-8",
					"Content-Length: " . strlen($post)
				)
			);
		}

		//设置超时
		//curl_setopt($ch, CURLOP_TIMEOUT, $this->curl_timeout);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if (strtoupper($method) == 'POST') {
			curl_setopt($ch, CURLOPT_POST, 1); //post提交方式
			curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post) ? http_build_query($post) : $post);
		}

		//运行curl，结果以jason形式返回
		$res = curl_exec($ch);
		//var_dump( curl_error($ch) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
		curl_close($ch);
		return $res;
	}

	/**
	 * 获得访问网站首页的URL地址
	 * @desc 如果配置了多个域名指向网站，获取的是实际访问的域名地址
	 * @return string
	 */
	public static function siteUrl()
	{
		$phpself = htmlentities(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME']);
		return Yii::$app->request->hostInfo . substr($phpself, 0, strrpos($phpself, '/'));
	}

	/**
	 * 获取安装时网站前台首页地址
	 * @return string
	 */
	public static function homeUrl()
	{
		return isset(Yii::$app->params['frontendUrl']) ? Yii::$app->params['frontendUrl'] : self::siteUrl();
	}

	/**
	 * 获取安装时网站后台首页地址
	 * @return string
	 */
	public static function backendUrl()
	{
		if (isset(Yii::$app->params['backendUrl']) && !empty(Yii::$app->params['backendUrl'])) {
			return Yii::$app->params['backendUrl'];
		}

		return self::homeUrl() . '/admin';
	}

	/**
	 * 获取移动端的首页地址
	 * @return string
	 */
	public static function mobileUrl($isConfig = true, $strict = false)
	{
		// DIY配置移动端
		if ($isConfig || !$strict) {
			return self::homeUrl() . '/mobile';
		}

		// 移动端实际访问地址
		return Yii::$app->params['mobileUrl'];
	}
}