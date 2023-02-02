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

use common\models\OrderModel;
use common\models\AddressModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Resource;
use common\library\Business;
use common\library\Page;
use common\library\Def;

/**
 * @Id OrderController.php 2018.7.12 $
 * @author mosir
 */

class OrderController extends \common\controllers\BaseUserController
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
		return $this->redirect(['order/normal']);
	}
	
	/* 从购物车取商品 */
	public function actionNormal()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['store_id']);
		return $this->build('normal', Url::toRoute(['order/normal', 'store_id' => $post->store_id]));
	}
	
	/* 从搭配套餐取商品 */
	public function actionMeal()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['id']);
		return $this->build('mealbuy', Url::toRoute(['order/meal', 'id' => $post->id, 'specs' => $post->specs]));
	}

	private function build($otype = 'normal', $redirect = null)
	{
		$get = Basewind::trimAll(Yii::$app->request->get(), true, ['store_id', 'id']);
	
		//  收货地址判断
		if(!AddressModel::find()->where(['userid' => Yii::$app->user->id])->exists()) {
			return Message::warning(Language::get('please_add_address'), false, ['label' => Language::get('add_address'), 'url' => Url::toRoute(['my_address/index', 'redirect' => $redirect])]);
		}
		
		$model = new \frontend\models\OrderForm(['otype' => $otype]);
		if(($goods_info = $model->getGoodsInfo($get)) === false) {
			return Message::warning($model->errors);
		}
		
		// 如果是自己店铺的商品，则不能购买
		if ($this->visitor['store_id'] && in_array($this->visitor['store_id'], $goods_info['storeIds'])) {
			return Message::warning(Language::get('can_not_buy_yourself'));
		}
		
		if(!Yii::$app->request->isPost)
		{
			// 获取订单模型
            $order_type = Business::getInstance('order')->build($otype);
			
            // 获取表单数据
            if(($form = $order_type->formData($goods_info)) === false) {
				return Message::warning($order_type->errors);
			}
			$this->params = array_merge($this->params, ['goods_info' => $goods_info, 'redirect' => $redirect], $form);
			
			$this->params['_foot_tags'] = Resource::import([
				'script' => 'jquery.ui/jquery.ui.js,jquery.ui/i18n/' . Yii::$app->language . '.js,jquery.plugins/jquery.validate.js,dialog/dialog.js,mlselection.js,user.js,jquery.plugins/jquery.form.js',
            	'style' =>  'jquery.ui/themes/smoothness/jquery.ui.css,dialog/dialog.css'
			]);
			
			$this->params['page'] = Page::seo(['title' => Language::get('confirm_order')]);
			return $this->render('../order.form.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post(), true);

			// 获取订单模型
            $order_type = Business::getInstance('order')->build($otype, $post);
			$result = $order_type->submit(array(
				'goods_info' => $goods_info
			));
			if(empty($result)) {
				return Message::warning($order_type->errors);
			}
	
			// 清理购物车商品等操作
			foreach($result as $store_id => $order_id) {
				$order_type->afterInsertOrder($order_id,  $store_id, $goods_info['orderList'][$store_id]);
			}
			
			// 有可能是支付多个订单
			$bizOrderId = implode(',', OrderModel::find()->select('order_sn')->where(['in', 'order_id', array_values($result)])->column());
			
			// 到收银台付款
			return $this->redirect(['cashier/gateway', 'bizOrderId' => $bizOrderId, 'bizIdentity' => Def::TRADE_ORDER]);
		}
	}
}