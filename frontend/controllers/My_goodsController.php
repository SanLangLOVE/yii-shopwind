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

use common\models\GoodsModel;
use common\models\GoodsSpecModel;
use common\models\GoodsImageModel;
use common\models\GcategoryModel;
use common\models\StoreModel;
use common\models\BrandModel;
use common\models\CatePvsModel;
use common\models\GoodsPvsModel;
use common\models\IntegralSettingModel;
use common\models\GoodsIntegralModel;
use common\models\DeliveryTemplateModel;
use common\models\UploadedFileModel;
use common\models\CategoryGoodsModel;
use common\models\CartModel;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Resource;
use common\library\Timezone;
use common\library\Page;
use common\library\Def;
use common\library\Promotool;
use common\library\Plugin;

/**
 * @Id My_goodsController.php 2018.4.26 $
 * @author mosir
 */

class My_goodsController extends \common\controllers\BaseSellerController
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
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['sgcate_id']);

		$query = GoodsModel::find()->alias('g')->select('g.goods_id,g.goods_name,g.cate_name,g.default_image,g.brand,g.spec_qty,g.price,g.if_show,g.isnew,g.recommended,g.closed,gs.stock,gs.sku')->joinWith('goodsDefaultSpec gs', false)->where(['store_id' => $this->visitor['store_id']])->orderBy(['goods_id' => SORT_DESC]);
		if ($post->keyword) {
			$query->andWhere(['or', ['like', 'goods_name', $post->keyword], ['like', 'brand', $post->keyword], ['like', 'cate_name', $post->keyword]]);
		}
		if ($post->character) {
			if ($post->character == 'show') $query->andWhere(['if_show' => 1]);
			if ($post->character == 'hide') $query->andWhere(['if_show' => 0]);
			if ($post->character == 'closed') $query->andWhere(['closed' => 1]);
			if ($post->character == 'recommended') $query->andWhere(['g.recommended' => 1]);
		}
		if ($post->sgcate_id > 0) {
			$cateIds = GcategoryModel::getDescendantIds($post->sgcate_id, $this->visitor['store_id']);
			$goodsIds = CategoryGoodsModel::find()->select('goods_id')->where(['in', 'cate_id', $cateIds])->column();
			$query->andWhere(['in', 'g.goods_id', $goodsIds]);
		}

		$page = Page::getPage($query->count(), 10);
		$goodsList = $query->offset($page->offset)->limit($page->limit)->asArray()->all();
		foreach ($goodsList as $key => $goods) {
			$goodsList[$key]['cate_name'] = GcategoryModel::formatCateName($goods['cate_name']);
			$goods['default_image'] || $goodsList[$key]['default_image'] = Yii::$app->params['default_goods_image'];
		}
		$this->params['goods_list'] = $goodsList;
		$this->params['pagination'] = Page::formatPage($page);

		// 取得店铺商品分类
		$this->params['sgcategories'] = GcategoryModel::getOptions($this->visitor['store_id']);
		$this->params['filtered'] = ($post->keyword || $post->character || $post->sgcate_id) ? 1 : 0;

		// 当前位置
		$this->params['_curlocal'] = Page::setLocal(Language::get('my_goods'), Url::toRoute('my_goods/index'), Language::get('goods_list'));

		// 当前用户中心菜单
		$this->params['_usermenu'] = Page::setMenu('my_goods', 'goods_list');

		$this->params['_foot_tags'] = Resource::import([
			'script' => 'jquery.ui/jquery.ui.js,jquery.ui/i18n/' . Yii::$app->language . '.js, dialog/dialog.js,inline_edit.js',
			'style' =>  'jquery.ui/themes/smoothness/jquery.ui.css,dialog/dialog.css'
		]);
		$this->params['page'] = Page::seo(['title' => Language::get('goods_list')]);
		return $this->render('../my_goods.index.html', $this->params);
	}

	/* 商品发布 */
	public function actionAdd()
	{
		$cateId = intval(Yii::$app->request->get('cate_id', 0));

		if ($this->addible() !== true) {
			return Message::warning($this->errors);
		}

		if (!$cateId) {
			return $this->redirect(['my_goods/publish']);
		}

		// 选择的分类不是最末级，返回选择分类页面
		if (GcategoryModel::find()->where(['if_show' => 1, 'store_id' => 0, 'parent_id' => $cateId])->exists()) {
			return Message::warning(Language::get('select_leaf_category'));
		}

		if (!Yii::$app->request->isPost) {
			$this->params['goods'] = $this->getGoodsInfo($cateId, 0);

			// 取得当前商品分类的属性
			$this->params['propList'] = CatePvsModel::getList($cateId, 0);

			// 取得品牌列表
			$this->params['brandList'] = BrandModel::find()->where(['in', 'store_id', [0, $this->visitor['store_id']]])->andWhere(['if_show' => 1])->asArray()->all();

			// 店铺分类
			$this->params['sgcategories'] = $this->getScategoryList($this->visitor['store_id']);

			// 商品图片批量上传器
			$this->params['images_upload'] = Plugin::getInstance('uploader')->autoBuild(true)->create([
				'obj' 			=> 'GOODS_SWFU',
				'belong' 		=> Def::BELONG_GOODS,
				'item_id'		=> 0,
				'button_id' 	=> 'goods_upload_button',
				'button_text'	=> Language::get('upload_goods_image'),
				'progress_id' 	=> 'goods_upload_progress',
				'upload_url' 	=> Url::toRoute(['upload/add', 'instance' => 'goods_image']),
				'multiple' 		=> true
			]);

			// 编辑器图片批量上传器
			$this->params['build_upload'] = Plugin::getInstance('uploader')->autoBuild(true)->create([
				'obj' 			=> 'EDITOR_SWFU',
				'belong' 		=> Def::BELONG_GOODS,
				'item_id' 		=> 0,
				'button_id' 	=> 'editor_upload_button',
				'button_text'	=> Language::get('uploadedfile'),
				'progress_id' 	=> 'editor_upload_progress',
				'upload_url' 	=> Url::toRoute(['upload/add', 'instance' => 'desc_image']),
				'multiple' 		=> true,
				'ext_js' 		=> false,
				'ext_css' 		=> false,
				'compress'		=> false
			]);

			// 所见即所得编辑器
			$this->params['build_editor'] = Plugin::getInstance('editor')->autoBuild(true)->create(['name' => 'description']);

			// 运费模板列表
			$this->params['deliverys'] = DeliveryTemplateModel::find()->select('template_id,name')->where(['store_id' => $this->visitor['store_id']])->asArray()->all();

			// 手机专享
			$exclusiveTool = Promotool::getInstance('exclusive')->build(['store_id' => $this->visitor['store_id']]);
			if ($exclusiveTool->checkAvailable(false)) {
				$this->params['exclusive'] = $exclusiveTool->getExclusive();
			}

			$this->params['_foot_tags'] = Resource::import([
				'script' => 'jquery.ui/jquery.ui.js,jquery.ui/i18n/' . Yii::$app->language . '.js,jquery.plugins/jquery.validate.js, dialog/dialog.js,mlselection.js,my_goods.js,jquery.plugins/jquery.form.js',
				'style' =>  'jquery.ui/themes/smoothness/jquery.ui.css,dialog/dialog.css'
			]);

			// 当前位置
			$this->params['_curlocal'] = Page::setLocal(Language::get('my_goods'), Url::toRoute('my_goods/add'), Language::get('goods_add'));

			// 当前用户中心菜单
			$this->params['_usermenu'] = Page::setMenu('my_goods', 'goods_add');

			$this->params['page'] = Page::seo(['title' => Language::get('goods_add')]);
			return $this->render('../my_goods.form.html', $this->params);
		} else {
			$post = Basewind::trimAll(array_merge(Yii::$app->request->post(), ['cate_id' => $cateId]));
			$data = $this->getPostData($post);
			if ($this->checkPostData($data) !== true) {
				return Message::warning($this->errors);
			}
			if (!$this->savePostData($data)) {
				return Message::warning($this->errors);
			}
			return Message::display(Language::get('add_ok'), ['my_goods/index']);
		}
	}

	/* 商品编辑 */
	public function actionEdit()
	{
		$id = intval(Yii::$app->request->get('id', 0));

		if (!$id) {
			return Message::warning(Language::get('no_such_goods'));
		}

		if (!Yii::$app->request->isPost) {
			$cateId = intval(Yii::$app->request->get('cate_id', 0));

			if (($goods = $this->getGoodsInfo($cateId, $id)) === false) {
				return Message::warning($this->errors);
			}
			$this->params['goods'] = $goods;

			// 取得当前商品分类的属性
			$this->params['propList'] = CatePvsModel::getList($cateId, $id);

			// 取得品牌列表
			$this->params['brandList'] = BrandModel::find()->where(['in', 'store_id', [0, $this->visitor['store_id']]])->andWhere(['if_show' => 1])->asArray()->all();

			// 店铺分类
			$this->params['sgcategories'] = $this->getScategoryList($this->visitor['store_id'], $id);

			// 上传文件需要的参数
			$this->params['id'] = $id;
			$this->params['belong'] = Def::BELONG_GOODS;

			// 商品图片批量上传器
			$this->params['images_upload'] = Plugin::getInstance('uploader')->autoBuild(true)->create([
				'obj' 			=> 'GOODS_SWFU',
				'belong' 		=> Def::BELONG_GOODS,
				'item_id'		=> $id,
				'button_id' 	=> 'goods_upload_button',
				'button_text'	=> Language::get('upload_goods_image'),
				'progress_id' 	=> 'goods_upload_progress',
				'upload_url' 	=> Url::toRoute(['upload/add', 'instance' => 'goods_image']),
				'multiple' 		=> true
			]);

			// 编辑器图片批量上传器
			$this->params['build_upload'] = Plugin::getInstance('uploader')->autoBuild(true)->create([
				'obj' 			=> 'EDITOR_SWFU',
				'belong' 		=> Def::BELONG_GOODS,
				'item_id' 		=> $id,
				'button_id' 	=> 'editor_upload_button',
				'button_text'	=> Language::get('uploadedfile'),
				'progress_id' 	=> 'editor_upload_progress',
				'upload_url' 	=> Url::toRoute(['upload/add', 'instance' => 'desc_image']),
				'multiple' 		=> true,
				'ext_js' 		=> false,
				'ext_css' 		=> false,
				'compress'		=> false
			]);

			// 所见即所得编辑器
			$this->params['build_editor'] = Plugin::getInstance('editor')->autoBuild(true)->create(['name' => 'description']);

			// 运费模板列表
			$this->params['deliverys'] = DeliveryTemplateModel::find()->select('template_id,name')->where(['store_id' => $this->visitor['store_id']])->asArray()->all();

			// 手机专享
			$exclusiveTool = Promotool::getInstance('exclusive')->build(['store_id' => $this->visitor['store_id']]);
			if ($exclusiveTool->checkAvailable(false)) {
				$this->params['exclusive'] = $exclusiveTool->getExclusive($id);
			}

			$this->params['_foot_tags'] = Resource::import([
				'script' => 'jquery.ui/jquery.ui.js,jquery.ui/i18n/' . Yii::$app->language . '.js,jquery.plugins/jquery.validate.js,dialog/dialog.js,mlselection.js,my_goods.js,jquery.plugins/jquery.form.js',
				'style' =>  'jquery.ui/themes/smoothness/jquery.ui.css,dialog/dialog.css'
			]);

			// 当前位置
			$this->params['_curlocal'] = Page::setLocal(Language::get('my_goods'), Url::toRoute(['my_goods/edit', 'id' => $id]), Language::get('goods_edit'));

			// 当前用户中心菜单
			$this->params['_usermenu'] = Page::setMenu('my_goods', 'goods_edit');

			$this->params['page'] = Page::seo(['title' => Language::get('goods_edit')]);
			return $this->render('../my_goods.form.html', $this->params);
		} else {
			$post = Basewind::trimAll(Yii::$app->request->post());
			$data = $this->getPostData($post, $id);
			if ($this->checkPostData($data, $id) !== true) {
				return Message::warning($this->errors);
			}
			if (!$this->savePostData($data, $id)) {
				return Message::warning($this->errors);
			}
			return Message::display(Language::get('edit_ok'), ['my_goods/index', 'page' => Yii::$app->request->get('ret_page')]);
		}
	}

	public function actionDelete()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);
		if (!$post->id) {
			return Message::warning(Language::get('no_such_goods'));
		}
		$model = new \frontend\models\GoodsDeleteForm(['goods_id' => $post->id, 'store_id' => $this->visitor['store_id']]);
		if (!$model->delete($post, true)) {
			return Message::warning($model->errors);
		}
		return Message::display(Language::get('drop_ok'), ['my_goods/index']);
	}

	/* 商品分布，选择分类 */
	public function actionPublish()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['id']);

		// 商城分类第一级
		$gcategories = GcategoryModel::getList(0, 0, true);
		foreach ($gcategories as $key => $val) {
			if (GcategoryModel::find()->where(['parent_id' => $val['cate_id'], 'if_show' => 1, 'store_id' => 0])->exists()) {
				$gcategories[$key]['hasChild'] = 1;
			} else $gcategories[$key]['hasChild'] = 0;
		}
		$this->params['categories'] = $gcategories;
		$this->params['action'] = $post->action ? $post->action : ($post->id ? 'edit' : 'add');

		// 发布商品协议文章，可以修改文章id
		$this->params['article'] = \common\models\ArticleModel::find()->select('description')->where(['article_id' => 1])->asArray()->one();

		$this->params['page'] = Page::seo(['title' => Language::get('goods_add')]);
		return $this->render('../my_goods.publish.html', $this->params);
	}

	/**
	 * 商品采集
	 */
	public function actionPicker()
	{
		if (!Yii::$app->request->isPost) {
			$post = Basewind::trimAll(Yii::$app->request->get(), true, ['cate_id']);

			$gcategories = GcategoryModel::getAncestor($post->cate_id);
			$goods['catalogs'] = $gcategories;
			$this->params['goods'] = $goods;
			if (($client = Plugin::getInstance('datapicker')->autoBuild(false))) {
				$this->params['platforms'] = $client->platforms();
			}

			// 当前位置
			$this->params['_curlocal'] = Page::setLocal(Language::get('my_goods'), Url::toRoute('my_goods/index'), Language::get('goods_picker'));

			// 当前用户中心菜单
			$this->params['_usermenu'] = Page::setMenu('my_goods', 'goods_picker');

			$this->params['page'] = Page::seo(['title' => Language::get('goods_picker')]);
			return $this->render('../my_goods.picker.html', $this->params);
		} else {
			$post = Basewind::trimAll(Yii::$app->request->post(), true, ['cate_id']);
			$client = Plugin::getInstance('datapicker')->autoBuild(false, ['platform' => $post->platform]);
			if (!$client) {
				return Message::warning(Language::get('no_plugin'));
			}

			if (empty($post->urls) || !($post->urls = explode(PHP_EOL, $post->urls))) {
				return Message::warning(Language::get('no_data'));
			}

			$items = [];
			foreach ($post->urls as $url) {
				if (($id = $client->getItemId($url))) {
					$items[] = $id;
				}
			}

			return Message::display(Language::get('pickering'), Url::toRoute(['my_goods/dopicker', 'platform' => $post->platform, 'cate_id' => $post->cate_id, 'items' => implode('-', $items)]));
		}
	}

	/**
	 * 采集到商品数据后
	 * 执行单个商品循环导入本地数据库
	 */
	public function actionDopicker()
	{
		if ($this->addible() !== true) {
			return Message::warning($this->errors);
		}

		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['cate_id']);

		if ($post->items) {
			if (!isset($post->platform) || empty($post->platform)) {
				return Message::warning(Language::get('platform_empty'));
			}

			$items = explode('-',  $post->items);
			$itemid = $items[0];

			$model = new \frontend\models\GoodsForm(['store_id' => $this->params['visitor']['store_id']]);
			if (!$model->import($post, $itemid)) {
				return Message::warning($model->errors);
			}
			unset($items[0]);
			array_values($items);

			if ($items) {
				return Message::display(sprintf("成功导入【%s】%s", $itemid, $items ? '，开始导入下一个商品' : ''), Url::toRoute(['my_goods/dopicker', 'cate_id' => $post->cate_id, 'platform' => $post->platform, 'items' => implode('-', $items)]));
			}
		}
		return Message::display(Language::get('import_ok'), Url::toRoute('my_goods/index'));
	}

	public function actionDeleteimage()
	{
		$id = intval(Yii::$app->request->get('id', 0));

		$uploadedfile = UploadedFileModel::find()->alias('f')->select('f.file_id, f.file_path')->where(['f.file_id' => $id, 'store_id' => $this->visitor['store_id']])->asArray()->one();
		if (UploadedFileModel::deleteFileByQuery(array($uploadedfile))) {
			return Message::display($id);
		}
		return Message::warning(Language::get('no_image_droped'));
	}

	public function actionBatchedit()
	{
		$ids = Basewind::trimAll(Yii::$app->request->get('id', 0));

		if (!Yii::$app->request->isPost) {
			$this->params['mgcategories'] = GcategoryModel::find()->select('cate_name')->where(['parent_id' => 0, 'if_show' => 1, 'store_id' => 0])->indexBy('cate_id')->column();

			$this->params['sgcategories'] = GcategoryModel::getOptions($this->visitor['store_id']);

			$this->params['_foot_tags'] = Resource::import('my_goods.js,mlselection.js,jquery.plugins/jquery.validate.js');

			// 当前位置
			$this->params['_curlocal'] = Page::setLocal(Language::get('my_goods'), Url::toRoute(['my_goods/index']), Language::get('batchedit'));

			// 当前用户中心菜单
			$this->params['_usermenu'] = Page::setMenu('my_goods', 'batchedit');

			$this->params['page'] = Page::seo(['title' => Language::get('batchedit')]);
			return $this->render('../my_goods.batch.html', $this->params);
		} else {
			$post = Basewind::trimAll(Yii::$app->request->post(), true);

			// 过滤掉非本店的ID
			$goodsIds = GoodsModel::find()->select('goods_id')->where(['store_id' => $this->visitor['store_id']])->andWhere(['in', 'goods_id', explode(',', $ids)])->column();

			$params = array();
			if ($post->cate_id > 0) {
				$params['cate_id'] = $post->cate_id;
				$params['cate_name'] = $post->cate_name;
			}
			if (in_array($post->if_show, [0, 1])) {
				$params['if_show'] = $post->if_show;
			}
			if (in_array($post->recommended, [0, 1])) {
				$params['recommended'] = $post->recommended;
			}
			if ($post->brand) {
				$params['brand'] = $post->brand;
			}
			if ($params) {
				GoodsModel::updateAll($params, ['in', 'goods_id', $goodsIds]);
			}

			if ($array = ArrayHelper::toArray($post->sgcate_id)) {
				$scateIds = array_unique($array);
				foreach ($scateIds as $key => $value) {
					if (!$value) unset($scateIds[$key]);
				}

				if ($scateIds) {
					CategoryGoodsModel::deleteAll(['in', 'goods_id', $goodsIds]);

					foreach ($goodsIds as $goods_id) {
						foreach ($scateIds as $cate_id) {
							$model = new CategoryGoodsModel();
							$model->goods_id = $goods_id;
							$model->cate_id = $cate_id;
							$model->save();
						}
					}
				}
			}

			if ($post->price_change) {
				$setData = '';
				$change = round(abs(floatval($post->price)), 2);
				if (in_array($post->price_change, ['change_to'])) {
					$setData = 'price=:price';
				} elseif (in_array($post->price_change, ['inc_by'])) {
					$setData = 'price=price+:price';
				} elseif (in_array($post->price_change, ['dec_by'])) {
					$setData = 'price = IF((price - :price) <0 , 0, price - :price)';
				}
				if ($setData) {
					Yii::$app->db->createCommand('UPDATE {{%goods}} SET ' . $setData . ' WHERE goods_id IN(:id)')->bindValues([':price' => $change, ':id' => $goodsIds])->execute();
					Yii::$app->db->createCommand('UPDATE {{%goods_spec}} SET ' . $setData . ' WHERE goods_id IN(:id)')->bindValues([':price' => $change, ':id' => $goodsIds])->execute();
				}
			}

			if ($post->stock_change) {
				$setData = '';
				$change = round(abs(floatval($post->stock)), 2);
				if (in_array($post->stock_change, ['change_to'])) {
					$setData = 'stock=:stock';
				} elseif (in_array($post->stock_change, ['inc_by'])) {
					$setData = 'stock=stock+:stock';
				} elseif (in_array($post->stock_change, ['dec_by'])) {
					$setData = 'stock = IF((stock - :stock) <0 , 0, stock - :stock)';
				}
				if ($setData) {
					Yii::$app->db->createCommand('UPDATE {{%goods_spec}} SET ' . $setData . ' WHERE goods_id IN(:id)')->bindValues([':stock' => $change, ':id' => $goodsIds])->execute();
				}
			}
			return Message::display(Language::get('edit_ok'), ['my_goods/index', 'page' => Yii::$app->request->get('ret_page')]);
		}
	}

	/* 取得商城商品分类JSON数据， 同时处理点击和检索 */
	public function actionAjaxgcategory()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true);

		$data = array();
		if (!$post->multilevel) {
			// 通过关键检索类目
			if (!empty($post->keyword)) {
				$gcategories = GcategoryModel::find()->where(['if_show' => 1, 'store_id' => 0])->andWhere(['like', 'cate_name', $post->keyword])->orderBy(['sort_order' => SORT_ASC])->indexBy('cate_id')->asArray()->all();

				//  寻找分类的上级，组成完整的分类路径
				foreach ($gcategories as $key => $val) {
					$parents = GcategoryModel::getAncestor($val['cate_id']);

					$str = '';
					foreach ($parents as $k1 => $v1) {
						$str .= $v1['cate_name'] . " > ";
					}
					$data[$key] = $str;
				}
			} else {
				$gcategories = GcategoryModel::getList($post->cate_id);
				foreach ($gcategories as $key => $val) {
					if (GcategoryModel::find()->where(['parent_id' => $val['cate_id'], 'if_show' => 1, 'store_id' => 0])->exists()) {
						$gcategories[$key]['hasChild'] = 1;
					} else $gcategories[$key]['hasChild'] = 0;
				}
				$data[$post->cate_id] = $gcategories;
			}
		} else {
			// 获取他的上级及本身
			$parents = GcategoryModel::getAncestor($post->cate_id);

			$parents_id = array();
			foreach ($parents as $key => $val) {
				$parents_id[] = $val['cate_id'];
			}

			foreach ($parents as $key => $val) {
				$gcategories = GcategoryModel::getList($val['parent_id']);
				foreach ($gcategories as $k => $v) {
					if (GcategoryModel::find()->where(['if_show' => 1, 'store_id' => 0, 'parent_id' => $v['cate_id']])->exists()) {
						$gcategories[$k]['hasChild'] = 1;
					} else $gcategories[$k]['hasChild'] = 0;

					if (in_array($v['cate_id'], $parents_id)) {
						$gcategories[$k]['selected'] = 1;
					} else $gcategories[$k]['selected'] = 0;
				}
				$data[] = $gcategories;
			}
			// 检索出来的分类的最后一级是否有子分类
			if ($children = GcategoryModel::getList($post->cate_id)) {
				foreach ($children as $child_key => $child_val) {
					if (GcategoryModel::find()->where(['if_show' => 1, 'store_id' => 0, 'parent_id' => $child_val['cate_id']])->exists()) {
						$children[$child_key]['hasChild'] = 1;
					} else $children[$child_key]['hasChild'] = 0;
				}
				$data[] = $children;
			}
		}
		return Message::result($data);
	}

	/* 获取提交的数据 */
	private function getPostData($post = [], $id = 0)
	{
		$goods = array(
			'store_id'				=> $this->visitor['store_id'],
			'goods_name'       		=> $post['goods_name'],
			'description'     		=> $post['description'],
			'cate_id'             	=> intval($post['cate_id']),
			'cate_name'        		=> $post['cate_name'],
			'brand'            		=> $post['brand'],
			'if_show'             	=> intval($post['if_show']),
			'last_update'      		=> Timezone::gmtime(),
			'isnew'					=> intval($post['isnew']),
			'video'					=> $post['video'],
			'recommended'      		=> intval($post['recommended']),
			'dt_id' 				=> intval($post['dt_id']),
			'tags'             		=> $post['tags'],
			'mkprice'				=> abs(floatval($post['mkprice']))
		);
		if ($id <= 0) {
			$goods['type'] 		= 'material';
			$goods['closed'] 	= 0;
			$goods['add_time'] 	= Timezone::gmtime();
		}

		$specs = array();
		$spec_name_1 = (isset($post['spec_name_1']) && $post['spec_name_1']) ? $post['spec_name_1'] : '';
		$spec_name_2 = (isset($post['spec_name_2']) && $post['spec_name_2']) ? $post['spec_name_2'] : '';
		$goods['spec_qty'] = ($spec_name_1 && $spec_name_2) ? 2 : (($spec_name_1 || $spec_name_2) ? 1 : 0);

		// 没有规格
		if ($goods['spec_qty'] == 0) {
			$specs[intval($post['spec_id'])] = $this->getSpecArray($post, false, intval($post['spec_id']));
			$goods['spec_name_1'] = '';
			$goods['spec_name_2'] = '';
		}
		// 一个规格
		elseif ($goods['spec_qty'] == 1) {
			$goods['spec_name_1'] = $spec_name_1 ? $spec_name_1 : $spec_name_2;
			$goods['spec_name_2'] = '';
			$spec_data = $spec_name_1 ? $post['spec_1'] : $post['spec_2'];
			foreach ($spec_data as $key => $spec_1) {
				$spec_1 = trim($spec_1);
				if ($spec_1) {
					if (($spec_id = intval($post['spec_id'][$key]))) // 已有规格ID的
					{
						$specs[$key] = $this->getSpecArray($post, $key, $spec_id, $spec_1);
					} else  // 新增的规格
					{
						$specs[$key] = $this->getSpecArray($post, $key, 0, $spec_1);
					}
				}
			}
		}
		// 二个规格
		elseif ($goods['spec_qty'] == 2) {
			$goods['spec_name_1'] = $spec_name_1;
			$goods['spec_name_2'] = $spec_name_2;
			foreach ($post['spec_1'] as $key => $spec_1) {
				$spec_1 = trim($spec_1);
				$spec_2 = trim($post['spec_2'][$key]);
				if ($spec_1 && $spec_2) {
					if (($spec_id = intval($post['spec_id'][$key]))) // 已有规格ID的
					{
						$specs[$key] = $this->getSpecArray($post, $key, $spec_id, $spec_1, $spec_2);
					} else // 新增的规格
					{
						$specs[$key] = $this->getSpecArray($post, $key, 0, $spec_1, $spec_2);
					}
				}
			}
		}

		// 分类
		$cates = array();
		if (isset($post['sgcate_id']) && is_array($post['sgcate_id'])) {
			foreach ($post['sgcate_id'] as $cate_id) {
				$cate_id = intval($cate_id);
				$cate_id > 0 && $cates[] = $cate_id;
			}
			$cates = array_unique($cates);
		}

		// 属性
		if (isset($post['props']) && is_array($post['props'])) {
			foreach ($post['props'] as $key => $val) {
				foreach ($val as $k => $v) {
					if (empty($v)) unset($post['props'][$key][$k]);
				}
				if (empty($post['props'][$key])) unset($post['props'][$key]);
			}
		}

		$data = array(
			'goods' 		=> $goods,
			'specs' 		=> $specs,
			'cates' 		=> $cates,
			'goods_file_id' => $post['goods_file_id'] ? $post['goods_file_id'] : array(),
			'desc_file_id' 	=> $post['desc_file_id'] ? $post['desc_file_id'] : array(),
			'props' 		=> isset($post['props']) ? $post['props'] : array(),
			'max_exchange'	=> isset($post['max_exchange']) ? $post['max_exchange'] : 0,
			'exclusive'		=> isset($post['exclusive']) ? $post['exclusive'] : array()
		);

		return $data;
	}
	private function getSpecArray($post = null, $key, $spec_id = 0, $spec_1 = '', $spec_2 = '')
	{
		$spec = array(
			'spec_1'    	=> $spec_1,
			'spec_2'    	=> $spec_2,
			'price'  		=> ($key === false) ? abs(floatval($post['price'])) : abs(floatval($post['price'][$key])),
			'stock'  		=> ($key === false) ? intval($post['stock']) : intval($post['stock'][$key]),
			'sku'       	=> ($key === false) ? $post['sku'] : $post['sku'][$key],
			'image'	=> ($key === false) || empty($post['image'][$key]) ? '' : $post['image'][$key],
			'sort_order'	=> ($key === false) ? 1 : intval($post['sort_order'][$key]),
		);
		if ($spec_id) $spec['spec_id'] = $spec_id;
		if (!$spec_1 && !$spec_2) unset($spec['spec_1'], $spec['spec_2']);

		return $spec;
	}

	/* 检查提交的数据 */
	private function checkPostData($data, $id = 0)
	{
		// 类目判断
		if (!isset($data['goods']['cate_id']) || !GcategoryModel::find()->where(['cate_id' => $data['goods']['cate_id'], 'if_show' => 1, 'store_id' => 0])->exists()) {
			$this->errors = Language::get('select_leaf_category');
			return false;
		}

		$data['max_exchange'] = intval($data['max_exchange']);

		// 最低抵扣不能超过商品的最大价格
		$max_exchange = IntegralSettingModel::getSysSetting('rate') * $data['max_exchange'];
		$spec = array();
		foreach ($data['specs'] as $key => $val) {
			$spec[] = $val['price'];
		}
		if (($max_exchange > 0 && ($max_exchange >= max($spec))) || ($data['max_exchange'] < 0) || ($max_exchange < 0)) {
			$this->errors = Language::get('max_exchange_illege');
			return false;
		}
		if ($data['exclusive']['decrease']) {
			if ($data['exclusive']['decrease'] >= min($spec)) {
				$this->errors = Language::get('exclusive_decrease_illege');
				return false;
			}
		}
		if ($data['exclusive']['discount']) {
			if ($data['exclusive']['discount'] >= 9.99 || $data['exclusive']['discount'] <= 0.01) {
				$this->errors = Language::get('discount_invalid');
				return false;
			}
		}
		if (!isset($data['goods']['dt_id']) || !intval($data['goods']['dt_id'])) {
			$this->errors = Language::get('select_delivery_template');
			return false;
		}
		if ($data['goods']['spec_qty'] == 1 && empty($data['goods']['spec_name_1']) || $data['goods']['spec_qty'] == 2 && (empty($data['goods']['spec_name_1']) || empty($data['goods']['spec_name_2']))) {
			$this->errors = Language::get('fill_spec_name');
			return false;
		}
		if (empty($data['specs'])) {
			$this->errors = Language::get('fill_spec');
			return false;
		}

		return true;
	}

	/* 保存数据 */
	private function savePostData($data, $id = 0)
	{
		// 保存商品
		if ($id > 0) {
			$model = GoodsModel::findOne($id);
			foreach ($data['goods'] as $key => $val) {
				$model->$key = $val;
			}
			if ($model->save() === false) {
				$this->errors = $model->errors;
				return false;
			}
			$goods_id = $model->goods_id;
		} else {
			$model = new GoodsModel();
			foreach ($data['goods'] as $key => $val) {
				$model->$key = $val;
			}
			if ($model->save() === false) {
				$this->errors = $model->errors;
				return false;
			}

			if (($data['goods_file_id'] || $data['desc_file_id'])) {
				$uploadedfiles = array_merge($data['goods_file_id'], $data['desc_file_id']);
				UploadedFileModel::updateAll(['item_id' => $model->goods_id], ['in', 'file_id', $uploadedfiles]);
			}

			if (!empty($data['goods_file_id'])) {
				GoodsImageModel::updateAll(['goods_id' => $model->goods_id], ['in', 'file_id', $data['goods_file_id']]);
			}
			$goods_id = $model->goods_id;
		}

		// 保存商品积分设置
		if (IntegralSettingModel::getSysSetting('enabled') && $data['max_exchange']) {
			if (($model = GoodsIntegralModel::find()->where(['goods_id' => $goods_id])->one())) {
				$model->max_exchange = $data['max_exchange'];
			} else {
				$model = new GoodsIntegralModel();
				$model->goods_id = $goods_id;
				$model->max_exchange = $data['max_exchange'];
			}
			$model->save();
		}

		// 保存规格
		if ($id > 0) {
			// 删除的规格
			$goods_specs = GoodsSpecModel::find()->select('spec_id')->where(['goods_id' => $id])->column();
			$drop_spec_ids = array_diff($goods_specs, array_keys($data['specs']));
			if (!empty($drop_spec_ids)) {
				GoodsSpecModel::deleteAll(['in', 'spec_id', $drop_spec_ids]);
				CartModel::deleteAll(['in', 'spec_id', $drop_spec_ids]);
			}
		}

		$default_spec = array(); // 初始化默认规格
		foreach ($data['specs'] as $key => $spec) {
			// 更新已有规格ID
			if (!empty($spec['spec_id']) && substr($spec['spec_id'], 0, 1) != 'N') {
				$spec_id = $spec['spec_id'];
				GoodsSpecModel::updateAll(array_merge(['spec_1' => '', 'spec_2' => ''], $spec), ['spec_id' => $spec_id]);
			}
			// 新加规格ID
			else {
				$spec['goods_id'] = $goods_id;
				$model = new GoodsSpecModel();
				$model->goods_id = $goods_id;
				foreach ($spec as $k => $v) {
					$model->$k = $v;
				}
				$model->save();
				$spec_id = $model->spec_id;
			}
			if (empty($default_spec)) {
				$default_spec = array('default_spec' => $spec_id, 'price' => $spec['price']);
			}
		}

		// 更新默认规格
		if ($default_spec) {
			GoodsModel::updateAll($default_spec, ['goods_id' => $goods_id]);
		}

		// 保存店铺商品分类
		CategoryGoodsModel::deleteAll(['goods_id' => $goods_id]);
		if ($data['cates']) {
			foreach ($data['cates'] as $key => $val) {
				$model = new CategoryGoodsModel();
				$model->goods_id = $goods_id;
				$model->cate_id = $val;
				$model->save();
			}
		}

		// 设置默认图片
		if (isset($data['goods_file_id'][0])) {
			$default_image = GoodsImageModel::find()->select('thumbnail')->where(['goods_id' => $goods_id, 'file_id' => $data['goods_file_id'][0]])->scalar();
			if ($default_image) {
				GoodsModel::updateAll(['default_image' => $default_image], ['goods_id' => $goods_id]);
			}
			GoodsImageModel::updateAll(['sort_order' => 255], ['goods_id' => $goods_id]);
			GoodsImageModel::updateAll(['sort_order' => 1], ['goods_id' => $goods_id, 'file_id' => $data['goods_file_id'][0]]);
		}

		// 保存商品属性
		if (isset($data['props']) && !empty($data['props']) && is_array($data['props'])) {
			// 构造新的数组和去空值
			$props = array();
			foreach ($data['props'] as $key => $val) {
				if (empty($val)) continue;
				foreach ($val as $k => $v) {
					if (!empty($v)) $props[] = $v;
				}
			}
			if (($model = GoodsPvsModel::find()->where(['goods_id' => $goods_id])->one())) {
				$model->pvs = implode(';', $props); // eg: 6:1;20:4
				$model->save();
			} else {
				$model = new GoodsPvsModel();
				$model->goods_id = $goods_id;
				$model->pvs = implode(';', $props);
				$model->save();
			}
		}

		// 保存手机专享设置
		$exclusiveTool = Promotool::getInstance('exclusive')->build(['store_id' => $this->visitor['store_id']]);
		if ($exclusiveTool->checkAvailable(false)) {
			$config = $data['exclusive'];
			unset($config['status']);
			if ($config['discount']) unset($config['decrease']);

			!isset($data['exclusive']['status']) && $data['exclusive']['status'] = 0;
			$exclusiveTool->savePromotoolItem(['goods_id' => $goods_id, 'config' => $config, 'status' => intval($data['exclusive']['status'])]);
		}

		// 保存主图视频 && 商品长图
		if (!($video = UploadedFileModel::getInstance()->upload('filevideo', $data['goods']['store_id'], "video/", 0, "goods-{$goods_id}", 2))) {
			$video = Yii::$app->request->post('video', '');
		}
		if (!($image = UploadedFileModel::getInstance()->upload('longimage', $data['goods']['store_id'], "goods/", 0, "long-{$goods_id}"))) {
			$image = Yii::$app->request->post('long_image', '');
		}
		GoodsModel::updateAll(['long_image' => $image, 'video' => $video], ['goods_id' => $goods_id]);

		return true;
	}

	/* 异步修改数据 */
	public function actionEditcol()
	{
		$post = Basewind::trimAll(Yii::$app->request->get(), true, ['id', 'if_show', 'closed', 'recommended']);
		if (in_array($post->column, ['goods_name', 'recommended', 'if_show', 'closed'])) {
			$query = GoodsModel::findOne($post->id);
			$query->{$post->column} = $post->value;
			if (!$query->save()) {
				return Message::warning($query->errors);
			}
			return Message::display(Language::get('edit_ok'));
		}
	}

	/* 异步修改规格 */
	public function actionEditspec()
	{
		$id = intval(Yii::$app->request->get('id'));
		if (!$id || !$goods = GoodsModel::find()->select('goods_id,goods_name,spec_name_1,spec_name_2,default_spec')->where(['goods_id' => $id])->with('goodsSpec')->asArray()->one()) {
			return Message::warning(Language::get('no_such_goods'));
		}
		if (!Yii::$app->request->isPost) {
			$this->params['goods'] = $goods;
			return $this->render('../my_goods.editspec.html', $this->params);
		} else {
			$post = Basewind::trimAll(Yii::$app->request->post(), false);
			foreach ($post['specs'] as $key => $val) {
				GoodsSpecModel::updateAll(['price' => round(abs(floatval($val['price'])), 2), 'stock' => intval($val['stock'])], ['spec_id' => $key]);
				//更新默认规格
				if ($goods['default_spec'] == $key) {
					GoodsModel::updateAll(['price' => round(abs(floatval($val['price'])), 2)], ['goods_id' => $id]);
				}
			}
			return Message::popSuccess();
		}
	}

	/* 取得商品信息 */
	private function getGoodsInfo(&$cate_id, $id = 0)
	{
		if ($id > 0) {
			$goods = GoodsModel::find()->alias('g')->joinWith('goodsIntegral integral', false)->with(['goodsSpec' => function ($query) {
				$query->orderBy(['sort_order' => SORT_ASC]);
			}])->where(['g.goods_id' => $id, 'store_id' => $this->visitor['store_id']])->asArray()->one();
			if (empty($goods)) {
				$this->errors = Language::get('no_such_goods');
				return false;
			}
			$goods['default_goods_image'] = Yii::$app->params['default_goods_image'];
			if (empty($goods['default_image'])) {
				$goods['default_image'] = Yii::$app->params['default_goods_image'];
			}
			$cate_id && $goods['cate_id'] = $cate_id;
			!$cate_id && $cate_id = $goods['cate_id'];

			// 数据兼容处理
			$goods['_specs'] = $goods['goodsSpec'];
			unset($goods['goodsSpec']);
		} else {
			$goods = array(
				'cate_id' 				=> 0,
				'if_show' 				=> 1,
				'recommended' 			=> 1,
				'price' 				=> 1,
				'stock' 				=> 1,
				'spec_qty' 				=> 0,
				'spec_name_1' 			=> Language::get('color'),
				'spec_name_2' 			=> Language::get('size'),
				'default_goods_image' 	=> Yii::$app->params['default_goods_image'],
			);
		}

		if (isset($goods['_specs']) && !empty($goods['_specs'])) {
			$goods['_specs'] = array_values($goods['_specs']);
		}
		$goods['spec_json'] = json_encode(array(
			'spec_qty' 		=> $goods['spec_qty'],
			'spec_name_1' 	=> isset($goods['spec_name_1']) ? $goods['spec_name_1'] : '',
			'spec_name_2' 	=> isset($goods['spec_name_2']) ? $goods['spec_name_2'] : '',
			'specs' 		=> $goods['_specs'],
		));

		// 商品主图/描述图
		$uploadedfiles = UploadedFileModel::find()->alias('f')->select('f.file_id,f.file_type,f.file_name,f.file_path,gi.goods_id,gi.thumbnail')->joinWith('goodsImage gi', false)->where(['store_id' => $this->visitor['store_id'], 'item_id' => $id, 'belong' => Def::BELONG_GOODS])->orderBy(['sort_order' => SORT_ASC, 'file_id' => SORT_ASC])->asArray()->all();
		if ($uploadedfiles) {
			foreach ($uploadedfiles as $key => $file) {
				if (!$file['goods_id']) {
					$goods['desc_images'][] = $file;
				} else $goods['goods_images'][] = $file;
			}
		}

		// 如果开启积分功能，则：读取商品积分设置
		if (IntegralSettingModel::getSysSetting('enabled')) {
			$this->params['integral_enabled'] = true;
			if (($goodsIntegral = GoodsIntegralModel::find()->where(['goods_id' => $id])->asArray()->one())) {
				$goods += $goodsIntegral ? $goodsIntegral : array();
			}
		}

		$goods['cate_name'] = '';
		$gcategories = GcategoryModel::getAncestor($cate_id);
		foreach ($gcategories as $key => $val) {
			$goods['cate_name'] .= (($key == 0) ? "" : "\t") . $val['cate_name'];
		}
		$goods['catalogs'] = $gcategories;
		return $goods;
	}

	private function getScategoryList($store_id = null, $id = 0)
	{
		$result = GcategoryModel::getTree($store_id);

		// 设置选中状态
		if ($id && ($gcateId = CategoryGoodsModel::find()->select('cate_id')->where(['goods_id' => $id])->column())) {
			foreach ($result as $key => $val) {
				if (in_array($val['id'], $gcateId)) $result[$key]['selected'] = true;
				foreach ($val['children'] as $k => $v) {
					if (in_array($v['id'], $gcateId)) $result[$key]['children'][$k]['selected'] = true;
				}
			}
		}

		return $result;
	}

	/**
	 * 检测店铺是否能添加商品
	 * 目前仅判断商品数，不判断支付方式和运费模板
	 */
	private function addible()
	{
		// 判断商品数是否已超过限制
		$settings = StoreModel::find()->select('goods_limit')->joinWith('sgrade', false)->where(['store_id' => $this->visitor['store_id']])->asArray()->one();
		if ($settings['goods_limit'] > 0) {
			if ($settings['goods_limit'] <= GoodsModel::find()->where(['store_id' => $this->visitor['store_id']])->count()) {
				$this->errors = Language::get('goods_limit_arrived');
				return false;
			}
		}
		return true;
	}

	/* 三级菜单 */
	public function getUserSubmenu()
	{
		$submenus =  array(
			array(
				'name'  => 'goods_list',
				'url'   => Url::toRoute('my_goods/index'),
			),
			array(
				'name'  => 'goods_add',
				'url'   => Url::toRoute('my_goods/add'),
			),
			array(
				'name' 	=> 'goods_picker',
				'url' 	=> Url::toRoute('my_goods/picker')
			)
		);
		if (in_array($this->action->id, ['edit'])) {
			$submenus[] = array(
				'name'	=>	'goods_edit',
				'url'	=>	''
			);
		}
		if (in_array($this->action->id, ['batchedit'])) {
			$submenus[] = array(
				'name'	=>	'batchedit',
				'url'	=>	''
			);
		}

		return $submenus;
	}
}
