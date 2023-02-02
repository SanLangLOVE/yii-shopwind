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

use common\models\WholesaleModel;
use common\models\GoodsModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Resource;
use common\library\Page;
use common\library\Promotool;

/**
 * @Id WholesaleController.php 2021.5.13 $
 * @author mosir
 */

class WholesaleController extends \common\controllers\BaseSellerController
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
		$post = Basewind::trimAll(Yii::$app->request->get(), true);
		
		if($post->title) {
			$params = ['like', 'goods_name', $post->title];
			$this->params['filtered'] = true;
		}
		
		$page = array('pageSize' => 15);
		$wholesaleTool = Promotool::getInstance('wholesale')->build(['store_id' => $this->visitor['store_id']]);
		if(($message = $wholesaleTool->checkAvailable()) !== true) {
			$this->params['tooldisabled'] = $message;
		}
	
		$this->params['list'] = $wholesaleTool->getList($params, $page);
		$this->params['pagination'] = Page::formatPage($page);
		
		// 当前位置
		$this->params['_curlocal'] = Page::setLocal(Language::get('wholesale'), Url::toRoute('wholesale/index'), Language::get('goods_list'));
		
		// 当前用户中心菜单
		$this->params['_usermenu'] = Page::setMenu('wholesale', 'goods_list');

		$this->params['page'] = Page::seo(['title' => Language::get('wholesale')]);
        return $this->render('../wholesale.index.html', $this->params);
	}
	
	public function actionAdd()
    {
        if(!Yii::$app->request->isPost)
		{
			if(($message = Promotool::getInstance('wholesale')->build(['store_id' => $this->visitor['store_id']])->checkAvailable()) !== true) {
				$this->params['tooldisabled'] = $message;
			}
			
			$this->params['_foot_tags'] = Resource::import([
				'script' => 'jquery.ui/jquery.ui.js,jquery.ui/i18n/' . Yii::$app->language . '.js,dialog/dialog.js,jquery.plugins/jquery.form.js,gselector.js',
            	'style' =>  'jquery.ui/themes/smoothness/jquery.ui.css,dialog/dialog.css'
			]);
		
			// 当前位置
			$this->params['_curlocal'] = Page::setLocal(Language::get('wholesale'), Url::toRoute('wholesale/index'), Language::get('add'));
		
			// 当前用户中心菜单
			$this->params['_usermenu'] = Page::setMenu('wholesale', 'add');

			$this->params['page'] = Page::seo(['title' => Language::get('add')]);
        	return $this->render('../wholesale.form.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post());

			$rules = [];
			foreach($post['rules']['quantity'] as  $key => $value) {
				$rules[$key] = ['quantity' => $value, 'price' => $post['rules']['price'][$key]];
			}
			$post['rules'] = $rules;
			
			$model = new \frontend\models\WholesaleForm(['store_id' => $this->visitor['store_id']]);
			if(!$model->save(Basewind::trimAll($post, true), true)) {
				return Message::warning($model->errors);
			}
			
			return Message::display(Language::get('add_ok'), ['wholesale/index', 'page' => Yii::$app->request->get('ret_page')]);
        }
    }
	
	public function actionEdit()
    {
        if(!Yii::$app->request->isPost)
		{
			$post = Basewind::trimAll(Yii::$app->request->get(), true, ['goods_id']);
		
			if(!$post->goods_id || !($list = WholesaleModel::find()->where(['goods_id' => $post->goods_id, 'store_id' => $this->visitor['store_id']])->orderBy(['id' => SORT_ASC])->asArray()->all())) {
				return Message::warning(Language::get('no_such_goods'));
			}

			if(($message = Promotool::getInstance('wholesale')->build(['store_id' => $this->visitor['store_id']])->checkAvailable()) !== true) {
				$this->params['tooldisabled'] = $message;
			}
			
			$this->params['list'] = json_encode($list);
			
			$this->params['_foot_tags'] = Resource::import([
				'script' => 'jquery.ui/jquery.ui.js,jquery.ui/i18n/' . Yii::$app->language . '.js,dialog/dialog.js,jquery.plugins/jquery.form.js,gselector.js',
            	'style' =>  'jquery.ui/themes/smoothness/jquery.ui.css,dialog/dialog.css'
			]);
		
			// 当前位置
			$this->params['_curlocal'] = Page::setLocal(Language::get('wholesale'), Url::toRoute('wholesale/index'), Language::get('edit'));
		
			// 当前用户中心菜单
			$this->params['_usermenu'] = Page::setMenu('wholesale', 'edit');

			$this->params['page'] = Page::seo(['title' => Language::get('edit')]);
        	return $this->render('../wholesale.form.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post());

			$rules = [];
			foreach($post['rules']['quantity'] as  $key => $value) {
				$rules[$key] = ['quantity' => $value, 'price' => $post['rules']['price'][$key]];
			}
			$post['rules'] = $rules;

			$model = new \frontend\models\WholesaleForm(['store_id' => $this->visitor['store_id']]);
			if(!$model->save(Basewind::trimAll($post, true), true)) {
				return Message::warning($model->errors);
			}

			return Message::display(Language::get('edit_ok'), ['wholesale/index', 'page' => $post->ret_page]);
        }
    }
	
	public function actionDelete()
    {
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['goods_id']);

		if(!$post->goods_id) {
			return Message::warning(Language::get('no_such_item'));
		}
		
		if(!WholesaleModel::deleteAll(['goods_id' => $post->goods_id, 'store_id' => $this->visitor['store_id']])) {
			return Message::warning(Language::get('drop_fail'));
		}

        return Message::display(Language::get('drop_ok'));
    }

	public function actionClosed()
    {
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['goods_id', 'status']);

		if(!$post->goods_id) {
			return Message::warning(Language::get('no_such_item'));
		}
		
		WholesaleModel::updateAll(['status' => $post->status ? 0 : 1], ['goods_id' => $post->goods_id, 'store_id' => $this->visitor['store_id']]);
        return Message::display($post->status ? Language::get('closed_ok') : Language::get('start_ok'));
    }
	
	public function actionQuery() 
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['id']);
		if(!$post->id) {
			return Message::warning(Language::get('no_such_goods'));
		}
		
		$goods = GoodsModel::find()->select('goods_id,goods_name,spec_name_1,spec_name_2,spec_qty,default_spec,default_image')->with('goodsSpec')->where(['goods_id' => $post->id, 'store_id' => $this->visitor['store_id']])->asArray()->one();
		if(!$goods) {
			return Message::warning(Language::get('no_such_goods'));
		}
		
		empty($goods['default_image']) && $goods['default_image'] = Yii::$app->params['default_goods_image'];
		
        if ($goods['spec_qty'] == 1 || $goods['spec_qty'] == 2) {
            $goods['spec_name'] = htmlspecialchars($goods['spec_name_1'] . ($goods['spec_name_2'] ? ' ' . $goods['spec_name_2'] : ''));
        }
        else {
            $goods['spec_name'] = Language::get('spec');
        }
		
        foreach ($goods['goodsSpec'] as $key => $spec)
        {	
            if ($goods['spec_qty'] == 1 || $goods['spec_qty'] == 2) {
                $goods['goodsSpec'][$key]['spec'] = htmlspecialchars($spec['spec_1'] . ($spec['spec_2'] ? ' ' . $spec['spec_2'] : ''));
			}
		    else {
                $goods['goodsSpec'][$key]['spec'] = Language::get('default_spec');
            }
        }
		return Message::result($goods);
	}
	
	/* 三级菜单 */
    public function getUserSubmenu()
    {
        $submenus =  array(
            array(
                'name' => 'goods_list',
                'url'  => Url::toRoute(['wholesale/index']),
            ),
			array(
                'name' => 'add',
                'url'  => Url::toRoute(['wholesale/add']),
            )
        );
		if(in_array($this->action->id, ['edit'])) {
			$submenus[] = array(
				'name' => 'edit',
				'url'  => ''
			);
		}

        return $submenus;
    }
}