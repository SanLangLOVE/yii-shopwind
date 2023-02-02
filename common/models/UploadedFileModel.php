<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;

use common\models\GoodsImageModel;

use common\library\Language;
use common\library\Def;
use common\library\Plugin;

/**
 * @Id UploadedFileModel.php 2018.4.4 $
 * @author mosir
 */

class UploadedFileModel extends ActiveRecord
{
	public $file;
	public $errors;
	
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%uploaded_file}}';
    }
	
	// 关联表
	public function getGoodsImage()
	{
		return parent::hasOne(GoodsImageModel::className(), ['file_id' => 'file_id']);
	}
	
	public static function getInstance()
	{
		return new UploadedFileModel();
	}
	
	/**
	 * 只上传文件，不保存到表
	 * @param int $archived  0=仅允许上传图片;1=允许上传图片和文档;2=仅允许上传视频(mp4)
	 */
	public function upload($fileVal = '', $store_id = 0, $belong = '', $unionid = 0, $filename = false, $archived = 0)
	{
		$model = new \frontend\models\UploadForm();
		$this->file = UploadedFile::getInstanceByName($fileVal);

		$model->file = $this->file;
		$model->allowed_size = Def::IMAGE_FILE_SIZE;
		$model->allowed_type = Def::IMAGE_FILE_TYPE;
		if($archived == 1) {
			$model->allowed_size = Def::ARCHIVE_FILE_SIZE;
			$model->allowed_type .= ',' . Def::ARCHIVE_FILE_TYPE;
		} else if($archived == 2) {
			$model->allowed_size = Def::ARCHIVE_FILE_SIZE;
			$model->allowed_type = Def::VIDEO_FILE_TYPE;
		}

		if(!$model->file) {
			$this->errors = Language::get('no_uploaded_file');
			return false;
		}
        if (!$model->validate()) {
			$this->errors = is_array($model->errors) ? $model->errors['file'][0] : $model->errors;
			return false;
		}
		if(!$this->validImageType($model->file)) {
			$this->errors = Language::get('invalid_image');
			return false;
		}
		
		$filename = $filename ? $filename : $model->filename();
		$savePath = self::getSavePath($store_id, $belong, $unionid);
		if(!$savePath || ($filePath = $model->upload($savePath, $filename)) === false) {
			$this->errors = $model->errors ? $model->errors : Language::get('file_save_error');
			return false;
		}
		
		return $filePath;
	}

	/**
	 * 生成缩微图/图片缩放
	 * MODE: THUMBNAIL_INSET|THUMBNAIL_OUTBOUND 
	 */
	public function thumbnail($file, $width = 400, $height = 400, $extension = '')
	{
		if(($oss = Plugin::getInstance('oss')->autoBuild())) {
			return $oss->thumbnail($file, $width, $height);
		}

		if(!$extension) {
			$extension = substr($file, strripos($file, '.') + 1);
		}
		$thumbnail = substr($file, 0, strripos($file, '.')) . '.thumb.' . $extension;

		\yii\imagine\Image::thumbnail(
			Def::fileSavePath() . DIRECTORY_SEPARATOR . $file, 
			$width, 
			$height, 
			\Imagine\Image\ManipulatorInterface::THUMBNAIL_OUTBOUND)->save(Def::fileSavePath() . DIRECTORY_SEPARATOR . $thumbnail, ['quality' => 100]
		);
		
		return $thumbnail;	
	}
	
	/**
	 * 删除商品图片，一个商品可能有多张图片
	 * @param array|int $goodsIds
	 * @param int $store_id
	 */
	public static function deleteGoodsFile($goodsIds = null, $store_id = 0)
	{
		if(!is_array($goodsIds)) $goodsIds = array($goodsIds);
		$query = parent::find()->alias('f')->select('f.file_id, f.file_path, gi.image_id, gi.image_url, gi.thumbnail')->joinWith('goodsImage gi', false)->where(['in', 'f.item_id', $goodsIds])->andWhere(['belong' => Def::BELONG_GOODS]);
		
		// 后台删除等不要验证店家身份
		if($store_id !== false) {
			$query->andWhere(['store_id' => $store_id]);
		}
		return self::deleteFileByQuery($query->asArray()->all());
	}
	
	/**
	 * 通过模型获取文件 进行删除文件操作,当上传文件是用OSS，如果删除时不是同一个OSS插件，可能会导致删除OSS文件失败
	 * @param array $uploadedfiles 支持删除多条
	 */
	public static function deleteFileByQuery($uploadedfiles = null)
	{
		$deleteNum = 0;
		if($uploadedfiles)
		{
			foreach($uploadedfiles as $uploadedfile)
			{
				if(($model = self::findOne($uploadedfile['file_id'])) && $model->delete()) {
				
					self::deleteFile($uploadedfile['file_path']);
					$deleteNum++;
				}
				if(($model = GoodsImageModel::find()->where(['file_id' => $uploadedfile['file_id']])->one())) {
					$thumbnail = $model->thumbnail;
					if($model->delete()) {
						self::deleteFile($thumbnail);
					}
				}
			}
		}
		return $deleteNum;
	}
	
	/**
	 * 根据文件名删除文件,当上传文件是用OSS，如果删除时不是同一个OSS插件，可能会导致删除OSS文件失败
	 * @param array $uploadedfiles 支持删除多条
	 */
	public static function deleteFileByName($uploadedfiles = null)
	{
		$deleteNum = 0;
		if($uploadedfiles)
		{
			if(is_string($uploadedfiles)) $uploadedfiles = array($uploadedfiles);
			foreach($uploadedfiles as $uploadedfile)
			{
				self::deleteFile($uploadedfile);
				$deleteNum++;
			}
		}
		return $deleteNum;
	}

	/**
	 * 执行文件删除
	 * @param string $file
	 */
	private static function deleteFile($file = null)
	{
		// 获取真实的物理路径
		list($localFile, $ossFile) = self::splitFile($file);
		
		if($localFile) {
			file_exists($localFile) && @unlink($localFile);
		}
		if($ossFile && ($model = Plugin::getInstance('oss')->autoBuild())) {
			$model->delete($ossFile);
		}
	}

	/**
	 * 获取文件本地路径和OSS云存储路径
	 * @param string $file 文件路径
	 */
	public static function splitFile($file = null) 
	{
		$array = explode('data/', $file);
		if(empty($array[0])) {
			return array(Def::fileSavePath(). '/'. $file, null);
		}
		
		// 删除本地文件需要全路径，删除OSS云存储文件需要相对路径
		return array(Def::fileSavePath().'/data/'.$array[1], 'data/'.$array[1]);
	}
	
	/* 统计某店铺已使用空间（单位：字节） */
    public static function getFileSize($store_id = 0)
    {
		return parent::find()->select('file_size')->where(['store_id' => $store_id])->sum('file_size');
    }
	
	/**
	 * 图片上传路径一律到前台
	 * 后台和前台均可使用此上传图片
	 */
	public static function getSavePath($store_id = 0, $belong = '', $unionid = 0)
	{
		// 如果是目录
		if(is_string($belong))
		{
			if($store_id) {
				$savePath = 'data/files/store_' . $store_id . '/' . $belong;
			} else {
				$savePath = 'data/files/mall/' . $belong;
			}
		} 
		else 
		{
			switch ($belong)
			{
				case Def::BELONG_ARTICLE 	:	$savePath = 'data/files/mall/article/';
				break;
				case Def::BELONG_STORE 		: 	$savePath = 'data/files/store_' . $store_id . '/other/';
				break;
				case Def::BELONG_GOODS 		:  	$savePath = 'data/files/store_' . $store_id . '/goods/';
				break;
				case Def::BELONG_MEAL  		: 	$savePath = 'data/files/store_' . $store_id . '/meal/';
				break;
				case Def::BELONG_GOODS_SPEC	:  	$savePath = 'data/files/store_' . $store_id . '/spec/';
				break;
				case Def::BELONG_STORE_SWIPER : $savePath = 'data/files/store_' . $store_id . '/swiper/';
				break;
				case Def::BELONG_LIMITBUY   :	$savePath = 'data/files/store_' . $store_id . '/limitbuy/';
				break;
				case Def::BELONG_IDENTITY	:	$savePath = 'data/files/store_' . ($store_id ? $store_id : $unionid) . '/identity/';
				break;
				case Def::BELONG_PORTRAIT	:	$savePath = 'data/files/mall/profile/'.$unionid.'/';
				break;
				case Def::BELONG_GCATEGORY_ICON	:	$savePath = 'data/files/mall/gcategory/icon/'.$unionid.'/';
				break;
				case Def::BELONG_GCATEGORY_AD	:	$savePath = 'data/files/mall/gcategory/ad/'.$unionid.'/';
				break;
				case Def::BELONG_BRAND_LOGO		:	$savePath = 'data/files/mall/brand/'.$unionid.'/';
				break;
				case Def::BELONG_BRAND_IMAGE	:	$savePath = 'data/files/mall/brand/'.$unionid.'/';
				break;
				case Def::BELONG_APPMARKET		:	$savePath = 'data/files/mall/appmarket/'.$unionid.'/';
				break;
				case Def::BELONG_REFUND_MESSAGE : 	$savePath = 'data/files/mall/refund/'.$unionid.'/message/';
				break;
				case Def::BELONG_WEIXIN			:	$savePath = 'data/files/mall/weixin/';
				break;
				case Def::BELONG_SETTING		:	$savePath = 'data/files/mall/setting/';
				break;
				case Def::BELONG_TEMPLATE		:	$savePath = 'data/files/mall/template/';
				break;
				case Def::BELONG_GUIDESHOP		:	$savePath = 'data/files/mall/guideshop/'.$unionid.'/';
				break;
				case Def::BELONG_POSTER			:	$savePath = 'data/files/mall/qrcode/poster/';
				break;
				case Def::BELONG_EVALUATE		:	$savePath = 'data/files/mall/evaluate/';
				break;
			}
		}

		if($savePath) {
			$savePath = Def::fileSavePath() . '/' . $savePath;
			if(!is_dir($savePath)) {
				\yii\helpers\FileHelper::createDirectory($savePath);
			}
			return $savePath;
		}
		return false;
	}

	/**
	 * 验证图片真实类型,读取一个图像的第一个字节并检查其签名
	 * @param $file
	 */
	private function validImageType($file)
	{
		// 目前仅验证图像类型的文件
		if(!in_array($file->extension, explode(',', Def::IMAGE_FILE_TYPE))) {
			return true;
		}

		// 允许的类型
		$allow = [IMAGETYPE_GIF,IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_BMP];
	
		if (!function_exists('exif_imagetype')) {
			list($width, $height, $type, $attr) = getimagesize($file->tempName);
			return in_array($type, $allow);
		}

		// 返回值和 getimagesize() 返回的数组中的索引 2 的值是一样的，但本函数快得多
		$type = exif_imagetype($file->tempName);
		return in_array($type, $allow);
	}
}
