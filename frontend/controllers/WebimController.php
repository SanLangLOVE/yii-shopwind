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

use common\models\UserModel;
use common\models\WebimModel;
use common\models\StoreModel;
use common\models\UserPrivModel;


use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Page;
use common\library\Timezone;

/**
 * @Id WebimController.php 2022.7.24 $
 * @author mosir
 */

class WebimController extends \common\controllers\BaseUserController
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
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['toid','store_id']);
		if(!$post->toid){
			// 跟平台回话
			$admin = UserPrivModel::find()->where(['store_id' => 0, 'privs' => 'all'])->one();
            $post->toid = $admin->userid;
			// 如果是平台客服点击，有对话才可以进入回话
			if($this->visitor['userid'] == $post->toid){
				$unread = WebimModel::find()->where(['toid' => $post->toid])->orderBy(['unread' => SORT_DESC, 'id' => SORT_DESC])->one();
				if(!empty($unread)){
				    $post->toid = $unread->fromid;
				}else{
				    return Message::warning(Language::get('talk_empty'));
				}
			}
			Yii::$app->getResponse()->redirect(Url::toRoute(['webim/index', 'toid' => $post->toid]));
		 	return false;
		}else{
			if(!UserModel::find()->where(['userid' => $post->toid])->exists()){
				return Message::warning(Language::get('talk_empty'));
			}
		}
		if($post->toid == $this->visitor['userid']){
			return Message::warning(Language::get('talk_yourself'));
		}
		$this->params['page'] = Page::seo();
		$logs = $this->getLogs($post->toid);
		$this->params['lists'] = $this->getList();
		$this->params['logs'] = $logs;
		$this->params['user'] = $this->getUser($post->toid, $post->store_id);
		$this->params['logs_counts'] = WebimModel::find()->where(['or', ['fromid' => $this->visitor['userid'], 'toid' => $post->toid], ['toid' => $this->visitor['userid'], 'fromid' => $post->toid]])->count();
		$this->params['lists_counts'] = WebimModel::find()->where(['or', ['fromid' => $this->visitor['userid']], ['toid' => $this->visitor['userid']]])->groupBy('groupid')->count();
		return $this->render('../webim.index.html', $this->params);
	}

	public function getUser($userid, $store_id=0)
	{
		if(!$userid || (!$user = UserModel::find()->select('userid,username,nickname,portrait')->where(['userid' => $userid])->asArray()->one())){
			return Message::warning(Language::get('no_such_user'));
		}
		$user['portrait'] = empty($user['portrait']) ? Yii::$app->params['default_user_portrait'] : $user['portrait'];
		if($store_id){
			$store = StoreModel::find()->select('store_name')->where(['store_id' => $store_id])->one();
			$user['nickname'] = $store['store_name'];
		}
		return $user;
	}

	/**
	 * 获取会话列表
	 */
	public function getList()
	{
		$list = WebimModel::find()->select('fromid,toid,store_id,store_name')->where(['or', ['fromid' => $this->visitor['userid']], ['toid' => $this->visitor['userid']]])->groupBy('groupid')->limit(100)->asArray()->all();
		foreach ($list as $key => $value) {
			if ($user = UserModel::find()->select('userid,username,nickname,portrait')->where(['userid' => $value['fromid'] == $this->visitor['userid'] ? $value['toid'] : $value['fromid']])->asArray()->one()) {
				$user['portrait'] = empty($user['portrait']) ? Yii::$app->params['default_user_portrait'] : $user['portrait'];
				$value['to'] = $user;
			}

			// 获取最后一条信息
			$record = WebimModel::find()->select('fromid,toid,content, created')->where(['or', ['fromid' => $value['fromid'], 'toid' => $value['toid']], ['fromid' => $value['toid'], 'toid' => $value['fromid']]])->orderBy(['id' => SORT_DESC])->asArray()->one();
			$record['created'] = Timezone::localDate('m-d H:i:s', $record['created']);

			// 获取未读消息数量
			$record['unreads'] = intval(WebimModel::find()->where(['fromid' => $record['fromid'], 'toid' => $this->visitor['userid'], 'unread' => 1])->sum('unread'));
			$list[$key] = array_merge($value, $record);
		}
		return $list;
	}

	/**
	 * 获取客服聊天记录
	 */
	public function getLogs($toid)
	{
		$query = WebimModel::find()->where(['or', ['fromid' => $this->visitor['userid'], 'toid' => $toid], ['toid' => $this->visitor['userid'], 'fromid' => $toid]])->orderBy(['id' => SORT_DESC]);
		$list = $query->limit(100)->asArray()->all();

		$readarray = [];
		foreach ($list as $key => $value) {
			if ($users = UserModel::find()->select('userid,username,nickname,portrait')->where(['in', 'userid', [$value['fromid'], $value['toid']]])->asArray()->all()) {
				foreach ($users as $user) {
					$user['portrait'] = empty($user['portrait']) ? Yii::$app->params['default_user_portrait'] : $user['portrait'];
					$list[$key][$user['userid'] == $value['fromid'] ? 'from' : 'to'] = $user;
				}
			}
			if ($value['toid'] == $this->visitor['userid'] && $value['unread']) {
				$readarray[] = $value['id'];
			}
			$list[$key]['created'] = Timezone::localDate('m-d H:i:s', $value['created']);
		}

		// 设置为已读
		if ($readarray) {
			WebimModel::updateAll(['unread' => 0], ['in', 'id', $readarray]);
		}

		// 使最新的记录显示在后面
		array_multisort(array_column($list, 'id'), SORT_ASC, $list);

		return $list;
	}

	/**
	 * 发送消息
	 */
	public function actionSend()
	{
		// 业务参数
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['toid', 'store_id']);

		if (empty($post->content)) {
			return Message::warning(Language::get('content_empty'));
		}
		if(!$post->toid){
			$admin = UserPrivModel::find()->select('userid')->where(['store_id' => 0, 'privs' => 'all'])->one();
			$post->toid = $admin->userid;
		}
		if ($post->toid == $this->visitor['userid']) {
			return Message::warning(Language::get('talk_yourself'));
		}else{
			if(!UserModel::find()->where(['userid' => $post->toid])->exists()){
				return Message::warning(Language::get('talk_empty'));
			}
		}

		$model = new WebimModel();
		$model->toid = $post->toid;
		$model->fromid = $this->visitor['userid'];
		$model->store_id = intval($post->store_id);
		$model->content = $post->content;
		$model->unread = 1;
		$model->created = Timezone::gmtime();

		if ($model->store_id && ($store = StoreModel::find()->select('store_name')->where(['store_id' => $model->store_id])->one())) {
			$model->store_name = $store->store_name;
		}
		if ($query = WebimModel::find()->select('groupid')->where(['or', ['fromid' => $model->fromid, 'toid' => $model->toid], ['fromid' => $model->toid, 'toid' => $model->fromid]])->one()) {
			$model->groupid = $query->groupid;
		} else $model->groupid = md5($model->fromid . ':' . $model->toid);

		if (!$model->save()) {
			return Message::warning($model->errors);
		}

		return Message::result(true);
	}

	public function actionGetnewlogs()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['count', 'toid']);
		$toid = $post->toid;
		$counts = WebimModel::find()->where(['or', ['fromid' => $this->visitor['userid'], 'toid' => $toid], ['toid' => $this->visitor['userid'], 'fromid' => $toid]])->count();
		if ($counts > $post->count) {
			$query = WebimModel::find()->where(['or', ['fromid' => $this->visitor['userid'], 'toid' => $toid], ['toid' => $this->visitor['userid'], 'fromid' => $toid]])->orderBy(['id' => SORT_DESC]);
			$list = $query->limit($counts - $post->count)->asArray()->all();
			$readarray = [];
			foreach ($list as $key => $value) {
				if ($users = UserModel::find()->select('userid,username,nickname,portrait')->where(['in', 'userid', [$value['fromid'], $value['toid']]])->asArray()->all()) {
					foreach ($users as $user) {
						$user['portrait'] = empty($user['portrait']) ? Yii::$app->params['default_user_portrait'] : $user['portrait'];
						$list[$key][$user['userid'] == $value['fromid'] ? 'from' : 'to'] = $user;
					}
				}
				if ($value['toid'] == $this->visitor['userid'] && $value['unread']) {
					$readarray[] = $value['id'];
				}
				$list[$key]['created'] = Timezone::localDate('m-d H:i:s', $value['created']);
			}
			// 设置为已读
			if ($readarray) {
				WebimModel::updateAll(['unread' => 0], ['in', 'id', $readarray]);
			}
			// 使最新的记录显示在后面
			array_multisort(array_column($list, 'id'), SORT_ASC, $list);
			return Message::result(array('logs_counts' => $counts, 'lists' => $list));
		} else {
			return Message::warning('no_news');
		}
	}

	public function actionGetnewlist()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['count']);
		$counts = WebimModel::find()->where(['or', ['fromid' => $this->visitor['userid']], ['toid' => $this->visitor['userid']]])->groupBy('groupid')->count();
		if ($counts > $post->count) {
			$list = WebimModel::find()->select('fromid,toid,store_id,store_name')->where(['or', ['fromid' => $this->visitor['userid']], ['toid' => $this->visitor['userid']]])->groupBy('groupid')->limit($counts - $post->count)->orderBy(['id' => SORT_DESC])->asArray()->all();
			foreach ($list as $key => $value) {
				if ($user = UserModel::find()->select('userid,username,nickname,portrait')->where(['userid' => $value['fromid'] == $this->visitor['userid'] ? $value['toid'] : $value['fromid']])->asArray()->one()) {
					$user['portrait'] = empty($user['portrait']) ? Yii::$app->params['default_user_portrait'] : $user['portrait'];
					$value['to'] = $user;
				}
				// 获取最后一条信息
				$record = WebimModel::find()->select('fromid,toid,content, created')->where(['or', ['fromid' => $value['fromid'], 'toid' => $value['toid']], ['fromid' => $value['toid'], 'toid' => $value['fromid']]])->orderBy(['id' => SORT_DESC])->asArray()->one();
				$record['created'] = Timezone::localDate('m-d H:i:s', $record['created']);

				// 获取未读消息数量
				$record['unreads'] = intval(WebimModel::find()->where(['fromid' => $record['fromid'], 'toid' => $this->visitor['userid'], 'unread' => 1])->sum('unread'));
				$list[$key] = array_merge($value, $record);
			}
			return Message::result(array('lists_counts' => $counts, 'lists' => $list));
		} else {
			return Message::warning('no_news');
		}
	}
}
