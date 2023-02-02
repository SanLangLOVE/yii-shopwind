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

use common\models\MealModel;
use common\models\MealGoodsModel;
use common\models\UploadedFileModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Resource;
use common\library\Page;
use common\library\Def;
use common\library\Promotool;
use common\library\Plugin;

/**
 * @Id Seller_mealController.php 2018.5.23 $
 * @author mosir
 */

class Seller_mealController extends \common\controllers\BaseSellerController
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
		$page = array('pageSize' => 15);
		$mealTool = Promotool::getInstance('mealbuy')->build(['store_id' => $this->visitor['store_id']]);
		if(($message = $mealTool->checkAvailable()) !== true) {
			$this->params['tooldisabled'] = $message;
		}
		
		$this->params['meals'] = $mealTool->getList($params, $page);
		$this->params['pagination'] = Page::formatPage($page);
		
		// 当前位置
		$this->params['_curlocal'] = Page::setLocal(Language::get('seller_meal'), Url::toRoute('seller_meal/index'), Language::get('meal_list'));
		
		// 当前用户中心菜单
		$this->params['_usermenu'] = Page::setMenu('seller_meal', 'meal_list');

		$this->params['page'] = Page::seo(['title' => Language::get('meal_list')]);
        return $this->render('../seller_meal.index.html', $this->params);
	}
	
	public function actionAdd()
    {
        if(!Yii::$app->request->isPost)
		{
			$this->params['store_id'] = $this->visitor['store_id'];
			
			if(($message = Promotool::getInstance('mealbuy')->build(['store_id' => $this->visitor['store_id']])->checkAvailable()) !== true) {
				$this->params['tooldisabled'] = $message;
			}
			
			// 取得游离状的图片
            $meal['desc_images'] = UploadedFileModel::find()->select('file_id,file_name,file_path')->where(['store_id' => $this->visitor['store_id'], 'belong' => Def::BELONG_MEAL, 'item_id' => 0])->orderBy(['add_time' => SORT_DESC])->asArray()->all();	
			$this->params['meal'] = $meal;		
			
			// 编辑器图片批量上传器
			$this->params['build_upload'] = Plugin::getInstance('uploader')->autoBuild(true)->create([
                'belong' 		=> Def::BELONG_MEAL,
                'item_id' 		=> 0,
                'upload_url' 	=> Url::toRoute(['upload/add', 'instance' => 'desc_image'])
			]);
			
			// 所见即所得编辑器
            $this->params['build_editor'] = Plugin::getInstance('editor')->autoBuild(true)->create(['name' => 'description']);
			
			$this->params['_foot_tags'] = Resource::import([
				'script' => 'jquery.ui/jquery.ui.js,jquery.ui/i18n/' . Yii::$app->language . '.js,dialog/dialog.js,webuploader/webuploader.js,webuploader/webuploader.compressupload.js,gselector.js',
            	'style' =>  'jquery.ui/themes/smoothness/jquery.ui.css,dialog/dialog.css'
			]);
		
			// 当前位置
			$this->params['_curlocal'] = Page::setLocal(Language::get('seller_meal'), Url::toRoute('seller_meal/index'), Language::get('meal_add'));
		
			// 当前用户中心菜单
			$this->params['_usermenu'] = Page::setMenu('seller_meal', 'meal_add');

			$this->params['page'] = Page::seo(['title' => Language::get('meal_add')]);
        	return $this->render('../seller_meal.form.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post(), true);
			
			$model = new \frontend\models\Seller_mealForm(['store_id' => $this->visitor['store_id']]);
			if(!$model->save($post, true)) {
				return Message::warning($model->errors);
			}
			return Message::display(Language::get('add_ok'), ['seller_meal/index', 'page' => Yii::$app->request->get('ret_page')]);
        }
    }
	
	public function actionEdit()
    {
        $id = intval(Yii::$app->request->get('id'));
		if(!$id || !($meal = MealModel::find()->where(['store_id' => $this->visitor['store_id'], 'meal_id' => $id])->asArray()->one())) {
			return Message::warning('no_such_meal');
		}
		
        if(!Yii::$app->request->isPost)
		{
			$this->params['store_id'] = $this->visitor['store_id'];
			
			if(($message = Promotool::getInstance('mealbuy')->build(['store_id' => $this->visitor['store_id']])->checkAvailable()) !== true) {
				$this->params['tooldisabled'] = $message;
			}
			
			// 取得游离状的图片
            $meal['desc_images'] = UploadedFileModel::find()->select('file_id,file_name,file_path')->where(['store_id' => $this->visitor['store_id'], 'belong' => Def::BELONG_MEAL, 'item_id' => $id])->orderBy(['add_time' => SORT_DESC])->asArray()->all();	
			$this->params['meal'] = $meal;
			
			// 取得游离状的图片
            $meal['desc_images'] = UploadedFileModel::find()->select('file_id,file_name,file_path')->where(['store_id' => $this->visitor['store_id'], 'belong' => Def::BELONG_MEAL, 'item_id' => $id])->orderBy(['add_time' => SORT_DESC])->asArray()->all();	
			$this->params['meal'] = $meal;		
			
			// 编辑器图片批量上传器
			$this->params['build_upload'] = Plugin::getInstance('uploader')->autoBuild(true)->create([
                'belong' 		=> Def::BELONG_MEAL,
                'item_id' 		=> $id,
                'upload_url' 	=> Url::toRoute(['upload/add', 'instance' => 'desc_image'])
			]);
			
			// 所见即所得编辑器
            $this->params['build_editor'] = Plugin::getInstance('editor')->autoBuild(true)->create(['name' => 'description']);
			
			$this->params['_foot_tags'] = Resource::import([
				'script' => 'jquery.ui/jquery.ui.js,jquery.ui/i18n/' . Yii::$app->language . '.js,dialog/dialog.js,webuploader/webuploader.js,webuploader/webuploader.compressupload.js,gselector.js',
            	'style' =>  'jquery.ui/themes/smoothness/jquery.ui.css,dialog/dialog.css'
			]);
		
			// 当前位置
			$this->params['_curlocal'] = Page::setLocal(Language::get('seller_meal'), Url::toRoute('seller_meal/index'), Language::get('meal_edit'));
		
			// 当前用户中心菜单
			$this->params['_usermenu'] = Page::setMenu('seller_meal', 'meal_edit');

			$this->params['page'] = Page::seo(['title' => Language::get('meal_edit')]);
        	return $this->render('../seller_meal.form.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post(), true);
			
			$model = new \frontend\models\Seller_mealForm(['store_id' => $this->visitor['store_id'], 'meal_id' => $id]);
			if(!$model->save($post, true)) {
				return Message::warning($model->errors);
			}
			return Message::display(Language::get('edit_ok'), ['seller_meal/index', 'page' => Yii::$app->request->get('ret_page')]);
        }
    }
	
	public function actionDelete()
    {
        $id = Basewind::trimAll(Yii::$app->request->get('id'));
		
		if(!$id) {
			return Message::warning(Language::get('no_such_meal'));
		}
		
		if(!MealModel::deleteAll(['meal_id' => $id, 'store_id' => $this->visitor['store_id']])) {
			return Message::warning(Language::get('drop_fail'));
		}
		MealGoodsModel::deleteAll(['meal_id' => $id]);
		
		$uploadedfile = UploadedFileModel::find()->select('file_id, file_path')->where(['store_id' => $this->visitor['store_id'], 'belong' => Def::BELONG_MEAL, 'item_id' => $id])->asArray()->all();
		UploadedFileModel::deleteFileByQuery($uploadedfile);
		
        return Message::display(Language::get('drop_ok'));
    }
	
	public function actionQuery() 
	{
		if(($id = Yii::$app->request->get('toolId', 0))) {
			$meal = MealModel::find()->where(['store_id' => $this->visitor['store_id'], 'meal_id' => $id])->asArray()->one();
		}
		$model = new \frontend\models\Seller_mealForm(['store_id' => $this->visitor['store_id']]);
		$goodsList = $model->queryInfo(Yii::$app->request->get('id'), $meal);
		return Message::result(['goodsList' => $goodsList]);
	}
	
	public function actionDeleteimage()
	{
		$id = intval(Yii::$app->request->get('id', 0));

		$uploadedfile = UploadedFileModel::find()->select('file_id, file_path')->where(['file_id' => $id, 'store_id' => $this->visitor['store_id']])->asArray()->one();
		if(UploadedFileModel::deleteFileByQuery(array($uploadedfile))) {
			return Message::display($id);
		}
        return Message::warning(Language::get('no_image_droped'));
	}
	

	
	/* 三级菜单 */
    public function getUserSubmenu()
    {
        $submenus =  array(
            array(
                'name' => 'meal_list',
                'url'  => Url::toRoute(['seller_meal/index']),
            ),
			array(
                'name' => 'meal_add',
                'url'  => Url::toRoute(['seller_meal/add']),
            )
        );
		if(in_array($this->action->id, ['edit'])) {
			$submenus[] = array(
				'name' => 'meal_edit',
				'url'  => ''
			);
		}

        return $submenus;
    }
}