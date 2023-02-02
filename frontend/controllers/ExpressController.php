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

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Page;
use common\library\Def;
use common\library\Plugin;


/**
 * @Id ExpressController.php 2018.10.22 $
 * @author mosir
 */

class ExpressController extends \common\controllers\BaseUserController
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
		$this->params = ArrayHelper::merge($this->params, Page::getAssign('user'));
	}
	
    public function actionIndex()
    {
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['order_id']);
		if(in_array($post->role, ['buyer', 'seller'])) {
            Yii::$app->session->set('userRole', $post->role);
        }

		if(!$post->order_id || !($order = OrderModel::find()->select('express_code,express_no,express_comkey,express_company,buyer_id,seller_id')->where(['order_id' => $post->order_id])->andWhere(['in', 'status', [Def::ORDER_SHIPPED,Def::ORDER_FINISHED]])->andWhere(['or', ['buyer_id' => Yii::$app->user->id], ['seller_id' => Yii::$app->user->id]])->one())) {
			return Message::warning(Language::get('no_such_order'));
		}
		
		// 每个订单发货用的快递插件会有不同
		$model = Plugin::getInstance('express')->build($order->express_code);
		if(!$model->isInstall()) {
			return Message::warning(Language::get('no_such_express_plugin'));
		}
		
		
		if(($result = $model->submit($post, $order)) === false) {
			return Message::warning($model->errors);
		}
		$this->params['kuaidi_info'] = $result;
		
		// 判断来访者是当前订单的买家还是卖家，以便设置不同的当前地址和左栏菜单
		if($order->seller_id == Yii::$app->user->id) // 是当前订单的卖家
		{
			$curlocal = 'seller_order/index';
			$curitem  = 'order_manage';
		}
		else
		{
			$curlocal = 'buyer_order/index';
			$curitem  = 'my_order';
		}
	
		// 当前位置
		$this->params['_curlocal'] = Page::setLocal(Language::get($curitem), Url::toRoute($curlocal), Language::get('express'));
		
		// 当前用户中心菜单
		$this->params['_usermenu'] = Page::setMenu($curitem, 'express');
		
		$this->params['page'] = Page::seo(['title' => Language::get('express')]);
		return $this->render('../express.index.html', $this->params);
	}
	
	/* 三级菜单 */
    public function getUserSubmenu()
    {
        $submenus =  array(
            array(
                'name'  => 'express',
                'url'   => Url::toRoute('express/index'),
            ),
        );

        return $submenus;
    }
}