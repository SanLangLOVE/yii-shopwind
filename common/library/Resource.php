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

use yii\helpers\FileHelper;

/**
 * @Id Resource.php 2018.3.13 $
 * @author mosir
 */
 
class Resource
{
	/**
     * 获取图片/文件绝对路径
     * @desc 注意不要返回相对路径，因为在视图中可能会使用|url_fromat标签，导致转义路径不对
     * @param array|string $params
     */
	public static function getThemeAssetsUrl($params = null)
	{
        $file = is_string($params) ? $params : ((is_array($params) && isset($params['file'])) ? $params['file'] : '');
        $baseUrl = (is_array($params) && isset($params['baseUrl'])) ? $params['baseUrl'] : Basewind::siteUrl();

		return $baseUrl . '/static/' . $file;
	}
    
    /**
     * 发布资源到WEB可访问的目录，并返回资源在发布目录的文件路径
     * @param array $params
     * @var string $depends 可单独指定依赖（建议由系统自动寻找模糊依赖，除非系统寻找的模糊依赖比较大，可采用手动指定）
     * @demo in PHP: import('dialog/dialog.js|depends:dialog')
     *       in VIEW: {lib file='dialog/dialog.js' depends='dialog'}
     * @desc 通过该参数，可以把dialog.js需要的资源，如dialog/dialog.css,dialog/images 一起发布到同目录下
     * @param bool $forceCopy  强制发布：为了保证修改资源文件后，能即时同步到发布目录，可设置改参数为true
     *       但同时也会严重影响网站打开速度（消耗资源），建议如果需要修改资源文件，则到后台开启网站调试模式，修改完毕修改为正常模式
     */
	public static function getResourceUrl($params = null)
	{
        // 源资源文件地址
        $jsRootPath = Yii::getAlias('@frontend') . '/resource/javascript/';

        // 如果没有该文件夹，则创建，避免发布失败
        FileHelper::createDirectory(Yii::getAlias('@webroot') .'/assets');

        // 当网站模式为调试模式时，发布资源时，强制更新
        $forceCopy = (Yii::$app->params['site_mode'] == 'debug') ? true : false;

        // 如果有指定依赖目录（比如JS需要加载CSS，CSS需要加载IMG
        if(isset($params['depends']) && !empty($params['depends'])) {
            if(is_dir($dir = $jsRootPath . $params['depends'])) {
                
                // 发布指定的依赖目录
                list($folderPath, $folderUrl) = Yii::$app->AssetManager->publish($dir, ['forceCopy' => $forceCopy]);
                return $folderUrl . '/'. substr($params['file'], strlen($params['depends'])+1);
            }
        }
        // 如果发布的文件为非根目录，则考虑模糊依赖关系，避免资源重复发布或缺少依赖文件
        else
        {
            // 如果不是一个有效的路径，则不发布
            if(!is_file($jsRootPath . $params['file'])) {
                return null;
            }

            $array = explode('/', $params['file']);

            // 如果是目录
            if(is_dir($dir = $jsRootPath . $array[0])) {

                // 发布模糊依赖目录
                list($folderPath, $folderUrl) = Yii::$app->AssetManager->publish($dir, ['forceCopy' => $forceCopy]);
                return $folderUrl . '/'. substr($params['file'], strlen($array[0])+1);
            }
            else
            {
                $filePath = $jsRootPath . $params['file'];

                 // 如果是文件
                if(is_file($filePath)) {

                    list($filePath, $fileUrl) = Yii::$app->AssetManager->publish($filePath, ['forceCopy' => $forceCopy]);
                    return $fileUrl;
                }
            }
        }
        return null;
    }
    
    /**
     * 导入资源到视图
     * @param string $spec_type 支持多个资源文件JS/CSS
     */
    public static function import($resources, $spec_type = null)
    {
		$headtag = '';
        if (is_string($resources) || $spec_type)
        {
            !$spec_type && $spec_type = 'script';
            $resources = self::getResourceData($resources);
            foreach ($resources as $params) {
                $headtag .= self::getResourceCode($spec_type, $params) . PHP_EOL;
            }
        }
        elseif (is_array($resources))
        {
            foreach ($resources as $type => $res) {
                $headtag .= self::import($res, $type);
            }
        }
		return $headtag ?  rtrim($headtag, PHP_EOL) : null;
	}
	
	/**
     * 获取资源数据
     * @param mixed $resources
     */
    public static function getResourceData($resources)
    {
        $result = array();
        if (is_string($resources))
        {
            $items = explode(',', $resources);
            //array_walk($items, create_function('&$val, $key', '$val = trim($val);'));
			
			// 去掉所有项目的空格 for PHP >= 7
			array_walk($items, function(&$val, $key) {
				$val = trim($val);
			});

            foreach ($items as $value)
            {
                list($path, $depends) = stripos($value, '|') > -1 ? explode('|', $value) : [$value, ''];
                if($depends) {
                    $array = explode(':', $depends);
                    !empty($array[1]) && $depends = $array[1];
                }
                $result[] = array('file' => $path, 'depends' => $depends);
            }
        }
        return $result;
    }
	
	/**
     * 获取资源文件的HTML代
     * @param string $type
     * @param array  $params
     */
    public static function getResourceCode($type, $params)
    {
        switch ($type)
        {
            // 资源目录下的JS文件
            case 'script':
                $pre = '<script type="text/javascript"';
                $path= ' src="' . self::getResourceUrl($params) . '"';
                $attr= ' charset="'.Yii::$app->charset.'" ';
                $tail= '></script>';
            break;
            // 加载远程的JS
            case 'remote':
                $pre = '<script type="text/javascript"';
                $path= ' src="' . $params['file'] . '"';
                $attr= ' charset="'.Yii::$app->charset.'" ';
                $tail= '></script>';
            break;
            // 资源目录下的CSS文件
            case 'style':
                $pre = '<link type="text/css" ';
                $path= ' href="' . self::getResourceUrl($params) . '"';
                $attr= ' rel="stylesheet" ';
                $tail= ' />';
            break;
        }
        $html = $pre . $path . $attr . $tail;

        return $html;
    }
}