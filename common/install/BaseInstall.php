<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\install;

use yii;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;

use common\library\Basewind;
use common\library\Language;
use common\library\Arrayfile;
use common\library\Setting;

/**
 * @Id BaseInstall.php 2018.10.31 $
 * @author mosir
 */

class BaseInstall
{
	/**
	 * 数据库实例
	 */
	public $db_type	= null;

	/**
	 * 数据库连接地址
	 */
	public $db_host = '127.0.0.1';

	/**
	 * 数据库连接端口
	 */
	public $db_port = '3306';

	/**
	 * 数据库名
	 */
	public $db_name = 'shopwind';

	/**
	 * 数据库用户
	 */
	public $db_user = 'root';

	/**
	 * 数据库密码
	 */
	public $db_password = '';

	/**
	 * 数据库表前缀
	 */
	public $db_prefix = 'swd_';

	/**
	 * 错误抓取
	 */
	public $errors = null;

	/**
	 * 原生数据库连接
	 */
	public function connect()
	{
		return false;
	}

	/**
	 * Yii数据库连接
	 * @param string $dns
	 */
	public function yiiconnect($dsn = '')
	{
		$connection = new \yii\db\Connection([
			'dsn' 		=> $dsn,
			'username' 	=> $this->db_user,
			'password' 	=> $this->db_password,
		]);

		try {
			$connection->open();
		} catch (\yii\db\Exception $e) {
			exit($e->errorInfo);
		}
		return $connection;
	}

	public function checkDb()
	{
		return false;
	}

	public function createDb()
	{
		return false;
	}

	public function checkTable($force = false)
	{
		return false;
	}

	/* 保存数据库配置信息 */
	public function saveConfig($post = null, $dsn = null)
	{
		if ($dsn == null) {
			return false;
		}

		// 此处可以根据情况配置多个DB
		$config = array(
			'db' => [
				'class' 		=> 'yii\db\Connection',
				'dsn' 			=> $dsn,
				'username' 		=> $this->db_user,
				'password' 		=> $this->db_password,
				'charset'  		=> strtolower(str_replace('-', '', Yii::$app->charset)), // utf8mb4
				'tablePrefix' 	=> $this->db_prefix,

				// 该处可以避免从数据库读取的部分数值型字段转化为字符串的情况
				// 但也要注意：因为增加了下面两个PDO参数后，Yii2.0的原有嵌套事务执行会报错，主要原因是框架本身的嵌套事务依赖于模拟预处理
				// 所以开启下面2个参数后，要慎重考虑
				//'attributes' => [
  					//PDO::ATTR_STRINGIFY_FETCHES => false,
 					//PDO::ATTR_EMULATE_PREPARES => false,
  				//]
			]
		);

		$setting = new Arrayfile();
		$setting->savefile = Yii::getAlias('@frontend') . '/web/data/config.php';
		$setting->setAll($config);
	}

	/* 保存站点配置 */
	public function saveSetting($post = null)
	{
		$config = ArrayHelper::merge([
			'frontendUrl' 	=> Basewind::siteUrl(),
			'mobileUrl'		=> '',
			'backendUrl'	=> Basewind::backendUrl()
		], Setting::getDefault());

		return Setting::getInstance()->setAll($config);
	}

	/* 安装结束 */
	public function finished($showprocess = true)
	{
		if ($showprocess) {
			$this->showProcess(Language::get('install_done'), true, 'parent.install_successed();');
		}

		// 锁定安装程序
		touch(Yii::getAlias('@frontend') . '/web/data/install.lock');
	}

	/* 检测是否安装测试数据 */
	public static function isInitdata()
	{
		$file = Yii::getAlias('@frontend') . '/web/data/initdata.lock';

		// 已经安装了
		if (file_exists($file)) {
			return true;
		}
		return false;
	}

	/* 复制文件 */
	public function copyFiles()
	{
		FileHelper::copyDirectory(Yii::getAlias('@common') . '/install/initdata/data', Yii::getAlias('@frontend') . '/web/data');
	}

	/**
	 * 插入测试数据
	 * 该操作必须再安装站点，取得DB实例后执行
	 */
	public function createInitRecord($seller_id, $buyer_id)
	{
		$connection = Yii::$app->db;
		$sqls = $this->getSql(Yii::getAlias('@common') . '/install/versions/initdata.sql');
		foreach ($sqls as $sql) {
			$sql = $this->replacePrefix('swd_', $connection->tablePrefix, $sql);
			$sql = str_replace('{seller_id}', $seller_id, $sql);
			$sql = str_replace('{buyer_id}', $buyer_id, $sql);
			$sql = str_replace('{site_url}', Yii::$app->params['frontendUrl'], $sql);
			$connection->createCommand($sql)->execute();
		}
	}

	/* 安装测试数据结束 */
	public function initend()
	{
		// 清空缓存
		Yii::$app->cache->flush();

		// 锁定文件
		touch(Yii::getAlias('@frontend') . '/web/data/initdata.lock');
	}

	public function replacePrefix($orig, $target, $sql)
	{
		return str_replace('`' . $orig, '`' . $target, $sql);
	}

	public function getSql($file)
	{
		$contents = file_get_contents($file);
		$contents = str_replace("\r\n", "\n", $contents);
		$contents = trim(str_replace("\r", "\n", $contents));
		$return_items = $items = array();
		$items = explode(";\n", $contents);
		foreach ($items as $item) {
			$return_item = '';
			$item = trim($item);
			$lines = explode("\n", $item);
			foreach ($lines as $line) {
				if (isset($line[0]) && $line[0] == '#') {
					continue;
				}
				if (isset($line[1]) && $line[0] . $line[1] == '--') {
					continue;
				}

				$return_item .= $line;
			}
			if ($return_item) {
				$return_items[] = $return_item;
			}
		}
		return $return_items;
	}

	/* 检查环境 */
	public function checkEnv($required)
	{
		$result  = array('detail' => array(), 'compatible' => true, 'msg' => array());
		foreach ($required as $key => $value) {
			$checker = $value['checker'];
			$method = $this->$checker();
			$result['detail'][$key] = array(
				'required'  => $value['required'],
				'current'   => $method['current'],
				'result'    => $method['result'] ? 'pass' : 'failed',
			);
			if (!$method['result']) {
				$result['compatible'] = false;
				$result['msg'][] = Language::get($key . '_error');
			}
		}
		return $result;
	}

	/* 检查文件是否可写 */
	public function checkFile($file)
	{
		if (!is_array($file)) $file = array($file);
		$result = array('detail' => array(), 'compatible' => true, 'msg' => array());
		foreach ($file as $key => $value) {
			$writabled = $this->isWriteabled(Yii::getAlias('@common') . '/' . $value);
			$result['detail'][] = array(
				'file' 		=> $value,
				'result'	=> $writabled ? 'pass' : 'failed',
				'current'   => $writabled ? Language::get('writable') : Language::get('unwritable'),
			);
			if (!$writabled) {
				$result['compatible'] = false;
				$result['msg'][] = sprintf(Language::get('file_error'), $value);
			}
		}
		return $result;
	}

	/* 检查文件是否可写 */
	public function isWriteabled($file)
	{
		if (!file_exists($file)) {
			// 不存在，如果创建失败，则不可写
			if (!FileHelper::createDirectory($file)) {
				return false;
			}
		}
		// 非Windows服务器
		if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
			return is_writable($file);
		}

		// 在Windows的服务器上可能会存在问题，待发现
		if (is_dir($file)) {
			// 如果是目录，则尝试创建文件并修改
			$trail = substr($file, -1);
			if ($trail == '/' || $trail == '\\') {
				$tmpfile = $file . '_temp_file.txt';
			} else {
				$tmpfile = $file . '/' . '_temp_file.txt';
			}
			// 尝试创建文件
			if (false === @touch($tmpfile)) {
				// 不可写
				return false;
			}
			// 创建文件成功
			// 尝试修改该文件
			if (false === @touch($tmpfile)) {
				return false;
			}
			// 修改文件成功
			// 删除文件
			@unlink($tmpfile);
			return true;
		} else {
			// 如果是文件，则尝试修改文件
			if (false === @touch($file)) {
				// 修改不成功，不可写
				return false;
			} else {
				// 修改成功，可写
				return true;
			}
		}
	}

	/* 检查PHP版本 */
	public function phpChecker()
	{
		return array(
			'current' => PHP_VERSION,
			'result'  => (PHP_VERSION >= 5.4),
		);
	}

	/* 检查GD版本 */
	public function gdChecker()
	{
		$result = array('current' => null, 'result' => false);
		$gd_info = function_exists('gd_info') ? gd_info() : array();
		$result['current'] = empty($gd_info['GD Version']) ? Language::get('gd_missing') : $gd_info['GD Version'];
		$result['result']  = empty($gd_info['GD Version']) ? false : true;

		return $result;
	}

	/* 显示进程 */
	public function showProcess($msg, $result = true, $script = '')
	{
		ob_implicit_flush(true);

		$class = $result ? 'successed' : 'failed';
		$status = $result ? Language::get('successed') : Language::get('failed');
		$html = "<p>{$msg} <span class=\"{$class}\">{$status}</span></p>";

		echo '<script type="text/javascript">parent.show_process(\'' . $html . '\');' . $script . '</script>';
		ob_flush();
		flush();
	}

	/**
	 * 初始数据，创建用户
	 * @param string $username
	 * @param string $password
	 */
	public function userRegister($username, $password = '')
	{
		$user = new \common\models\UserModel();
		$user->username = $username;
		$user->setPassword($password);
		$user->generateAuthKey();
		if (!$user->save()) {
			return false;
		}
		return $user->userid;
	}
}