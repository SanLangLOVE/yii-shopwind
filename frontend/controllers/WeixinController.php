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
use yii\helpers\Url;
use yii\helpers\ArrayHelper;

use common\models\UserModel;
use common\models\BindModel;
use common\models\WeixinReplyModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Page;
use common\library\Weixin;

/**
 * @Id WeixinController.php 2018.12.5 $
 * @author luckey
 */

class WeixinController extends \common\controllers\BaseMallController
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
		$this->params = ArrayHelper::merge($this->params, Page::getAssign('mall'));
	}

	public function actionIndex()
	{
		$wx = new Weixin();

		// 验证
		if (!$wx->valid()) {
			return Message::warning($wx->errors);
		}

		$postData = $wx->getPostData();
		if ($postData['MsgType'] == 'event') //接收事件推送
		{
			if ($postData['Event'] == 'subscribe') //关注事件
			{
				if ($wx->config['autologin']) {
					$this->register($postData['FromUserName']);
				}

				if (($reply = WeixinReplyModel::find()->where(['userid' => 0, 'action' => 'beadded'])->asArray()->one())) {
					if ($reply['type']) $content[] = $reply;
					else $content = $reply['description'];
				} else {
					$wxInfo = $wx->getUserInfo($postData['FromUserName']);
					$content = $wxInfo->nickname . ' 您好，欢迎您关注' . $wx->config['name'] . '! <a href="' . Url::toRoute(['weixin/signin', 'openid' => $postData['FromUserName']]) . '">【会员中心】</a>';
				}
			} elseif ($postData['Event'] == 'CLICK') //点击菜单拉取消息时的事件推送,后台设定为图文消息
			{
				if (!($reply = WeixinReplyModel::find()->where(['reply_id' => intval($postData['EventKey'])])->asArray()->one())) {
					return false;
				}
				$content[] = $reply;
			}
		} else {
			//先执行回复命令，再找关键字，再自动回复
			$getContent = $postData['Content'];

			//关键词命令
			if ($getContent) $reply = $this->checkKeywords($getContent);

			//关键字回复
			if ($reply) {
				//图文消息
				$content = $reply;
			} else {
				//自动回复
				if (($autoreply = WeixinReplyModel::find()->where(['userid' => 0, 'action' => 'autoreply'])->asArray()->one())) {
					//图文消息
					if ($autoreply['type']) $content = $autoreply;
					else $content = $autoreply['description'];
				}
			}
		}

		if (!($resultStr = $wx->getMsgXML($postData['FromUserName'], $postData['ToUserName'], $content))) {
			return false;
		}

		exit($resultStr);
	}

	/* 自动注册并登陆 */
	private function register($openid = '')
	{
		if (!$openid) {
			return false;
		}

		$wx = new Weixin();

		if (!($wxInfo = $wx->getUserInfo($openid))) {
			return false;
		}
		$query = BindModel::find()->where(['and', ['or', 'openid' => $openid, 'unionid' => $openid], ['app' => 'weixin']])->one();
		if ($query && UserModel::find()->where(['userid' => $query->userid])->exists()) {
			return false;
		}

		$bind = (object)array('code' => 'weixin', 'unionid' => $openid, 'portrait' => $wxInfo->headimgurl, 'nickname' => $wxInfo->nickname, 'access_token' => $wxInfo->access_token);

		do {
			$model = new \frontend\models\UserRegisterForm();
			if ($bind->nickname) {
				$model->username = Basewind::checkUser($bind->nickname) ? $bind->nickname : UserModel::generateName($bind->nickname);
			} else $model->username = UserModel::generateName($bind->code);
			$model->password  = mt_rand(1000, 9999);
			$user = $model->register(['real_name' => $bind->nickname, 'portrait' => $bind->portrait]);
		} while (!$user);

		// 将绑定信息插入数据库
		if (!BindModel::bindUser($bind, $user->userid)) {
			return false;
		}

		return $user->userid;
	}

	private function checkKeywords($word = '')
	{
		$result = [];
		$replys = WeixinReplyModel::find()->where(['and', ['userid' => 0, 'action' => 'smartreply'], ['like', 'keywords', $word]])->asArray()->all();

		foreach ($replys as $key => $val) {
			$keywords = explode(',', str_replace('，', ',', trim($val['keywords'])));

			if (in_array($word, $keywords)) {
				$result[] = $val;
			}
		}
		return $result;
	}
}
