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

use common\models\DeliveryTemplateModel;
use common\models\RegionModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Resource;
use common\library\Page;

/**
 * @Id My_deliveryController.php 2018.5.19 $
 * @author mosir
 */

class My_deliveryController extends \common\controllers\BaseSellerController
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
		// 取得列表数据
		$query = DeliveryTemplateModel::find()->where(['store_id' => $this->visitor['store_id']])->orderBy(['template_id' => SORT_DESC]);
		$page = Page::getPage($query->count(), 3);
		$this->params['deliverys'] = DeliveryTemplateModel::formatTemplate($query->offset($page->offset)->limit($page->limit)->asArray()->all());
		$this->params['pagination'] = Page::formatPage($page);
		
		// 当前位置
		$this->params['_curlocal'] = Page::setLocal(Language::get('my_delivery'), Url::toRoute('my_delivery/index'), Language::get('delivery_list'));
		
		// 当前用户中心菜单
		$this->params['_usermenu'] = Page::setMenu('my_delivery', 'delivery_list');

		$this->params['page'] = Page::seo(['title' => Language::get('delivery_list')]);
        return $this->render('../my_delivery.index.html', $this->params);
	}
	
	public function actionAdd()
	{
		if(!Yii::$app->request->isPost)
		{
			$this->params['delivery'] = ['plus_type' => DeliveryTemplateModel::getPlusType()];
			$this->params['regions'] = RegionModel::find()->select('region_name')->where(['parent_id' => 0])->column();
			
			$this->params['_foot_tags'] = Resource::import([
				'script' => 'jquery.ui/jquery.ui.js,jquery.ui/i18n/' . Yii::$app->language . '.js,jquery.plugins/jquery.validate.js,dialog/dialog.js,mlselection.js,delivery.js',
            	'style' =>  'jquery.ui/themes/smoothness/jquery.ui.css,dialog/dialog.css'
			]);
			
			// 当前位置
			$this->params['_curlocal'] = Page::setLocal(Language::get('my_delivery'), Url::toRoute('my_delivery/index'), Language::get('delivery_add'));
		
			// 当前用户中心菜单
			$this->params['_usermenu'] = Page::setMenu('my_delivery', 'delivery_add');	

			$this->params['page'] = Page::seo(['title' => Language::get('delivery_add')]);
        	return $this->render('../my_delivery.form.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post(), true);
			
			$model = new \frontend\models\My_deliveryForm(['store_id' => $this->visitor['store_id']]);
			if(!$model->save($post, true)) {
				return Message::warning($model->errors);
			}
			return Message::display(Language::get('add_ok'), ['my_delivery/index']);
		}
	}
	
	public function actionEdit()
	{
		$template_id = intval(Yii::$app->request->get('id'));
		if(!($model = DeliveryTemplateModel::find()->where(['store_id' => $this->visitor['store_id'], 'template_id' => $template_id])->one())) {
			return Message::warning(Language::get('no_such_template'));
		}
		
		if(!Yii::$app->request->isPost)
		{
			$this->params['delivery'] = DeliveryTemplateModel::formatTemplateForEdit(ArrayHelper::toArray($model));
			
			$this->params['regions'] = RegionModel::find()->select('region_name')->where(['parent_id' => 0])->column();
			
			$this->params['_foot_tags'] = Resource::import([
				'script' => 'jquery.ui/jquery.ui.js,jquery.ui/i18n/' . Yii::$app->language . '.js,jquery.plugins/jquery.validate.js,dialog/dialog.js,mlselection.js,delivery.js',
            	'style' =>  'jquery.ui/themes/smoothness/jquery.ui.css,dialog/dialog.css'
			]);
			
			// 当前位置
			$this->params['_curlocal'] = Page::setLocal(Language::get('my_delivery'), Url::toRoute('my_delivery/index'), Language::get('delivery_edit'));
		
			// 当前用户中心菜单
			$this->params['_usermenu'] = Page::setMenu('my_delivery', 'delivery_edit');	

			$this->params['page'] = Page::seo(['title' => Language::get('delivery_edit')]);
        	return $this->render('../my_delivery.form.html', $this->params);
		}
		else 
		{
			$post = Basewind::trimAll(Yii::$app->request->post(), true);
			
			$model = new \frontend\models\My_deliveryForm(['store_id' => $this->visitor['store_id'], 'id' => $template_id]);
			if(!$model->save($post, true)) {
				return Message::warning($model->errors);
			}
			return Message::display(Language::get('edit_ok'), ['my_delivery/index']);
		}
	}
	
	public function actionCopytpl()
	{
		$template_id = intval(Yii::$app->request->get('id'));
		if(!($template = DeliveryTemplateModel::find()->where(['store_id' => $this->visitor['store_id'], 'template_id' => $template_id])->asArray()->one())) {
			return Message::warning(Language::get('no_such_template'));
		}
			
		$template['name'] = $template['name'] . Language::get('copy_word');
		unset($template['template_id']);
		
		$model = new DeliveryTemplateModel();
		foreach($template as $key => $val) {
			$model->$key = $val;
		}
		if($model->save() === false) {
			return Message::warning($model->errors);
		}
		return Message::display(Language::get('copy_ok'));		
	}
	
	public function actionDelete()
	{
		$template_id = intval(Yii::$app->request->get('id'));
		
		if(!DeliveryTemplateModel::find()->where(['store_id' => $this->visitor['store_id']])->andWhere(['<>', 'template_id', $template_id])->exists()) {
			return Message::warning(Language::get('no_allow_drop_last_delivery_template'));
		}
		if(!DeliveryTemplateModel::deleteAll(['store_id' => $this->visitor['store_id'], 'template_id' => $template_id])) {
			return Message::warning(Language::get('drop_fail'));
		}
		return Message::display(Language::get('drop_ok'), ['my_delivery/index']);
	}
	
	
	/* 三级菜单 */
    public function getUserSubmenu()
    {
        $submenus =  array(
            array(
                'name' => 'delivery_list',
                'url'  => Url::toRoute(['my_delivery/index']),
            ),
			array(
                'name' => 'delivery_add',
                'url'  => Url::toRoute(['my_delivery/add']),
            )
        );
		if(in_array($this->action->id, ['edit'])) {
			$submenus[] = array(
				'name' => 'delivery_edit',
				'url' => ''
			);
		}

        return $submenus;
    }
}