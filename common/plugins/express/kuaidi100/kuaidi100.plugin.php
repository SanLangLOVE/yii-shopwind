<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\plugins\express\kuaidi100;

use yii;

use common\library\Basewind;
use common\library\Language;
use common\plugins\BaseExpress;

/**
 * @Id kuaidi100.plugin.php 2018.9.5 $
 * @author mosir
 */

class Kuaidi100 extends BaseExpress
{
	/**
	 * 网关地址
	 * @var string $gateway
	 */
	protected $gateway = 'http://poll.kuaidi100.com/poll/query.do';

	/**
     * 插件实例
	 * @var string $code
	 */
	protected $code = 'kuaidi100';

	/**
     * SDK实例
	 * @var object $client
     */
	private $client = null;

	
	/**
	 * 对数据进行验证
	 */
	public function valid($post = null, $order = null)
	{
		if(empty($order->express_comkey) || empty($order->express_no)) {
			$this->errors = Language::get('invoice_or_company_empty');
			return false;
		}
		return true;
	}
	
	/**
	 * 发送请求获取数据
	 */
	public function submit($post = null, $order = null, $valid = true)
    {
		if($valid === true && !($this->valid($post, $order))) {
			return false;
		}
		
		// 企业版优先
		if($this->config['customer']) {
			$result = $this->queryPoll($post, $order);
		}
		
		// 免费版（JSON）
		if(!$result['done']) {
			$result = $this->queryApi($post, $order);
		}
		
		// 免费版（URL）
		if(!$result['done']) {
			$result = $this->queryIframe($post, $order);
		}
		
		if($result) {
			$result = array_merge($result, [
				'order_id' 	=> $order->order_id, 
				'order_sn' 	=> $order->order_sn, 
				'company' 	=> $order->express_company,
				'number' 	=> $order->express_no, 
				'details' 	=> $result['data']
			]);
			
			// 去掉接口返回的多余字段
			unset($result['data'], $result['done'], $result['nu'], $result['ischeck'], $result['condition']);
			unset($result['message'], $result['com'], $result['state']);
		}
		
		return $result;
	}
	
	/* 企业版 返回JSON 稳定 */
	private function queryPoll($post = null, $order = null)
	{
		$params['customer'] = $this->config['customer'];
		$params['param'] 	= json_encode(['com' => $order->express_comkey, 'num' => $order->express_no]);
		$params['sign'] 	= strtoupper(md5($params['param'] . $this->config['key'] . $this->config['customer']));
		
		$result = Basewind::curl($this->gateway, 'post', $params);
		$result = json_decode(str_replace("\"",'"', $result), true);
			
		// 快递单当前签收状态，包括0在途中、1已揽收、2疑难、3已签收、4退签、5同城派送中、6退回、7转单等7个状态，其中4-7需要另外开通才有效
		if(isset($result['state']) && in_array($result['state'], array(0,1,2,3,4,5,6,7))) {
			$result['status'] = 1; // 兼容免费版接口状态值（企业版：返回200，免费版返回1）
			$result['done'] = true;
		}
		return $result;	
	}
	
	/* 免费版（暂时保留）返回JSON 但是该网关不支持EMS、顺丰和申通 且不稳定 */
	private function queryApi($post = null, $order = null)
	{
		$this->gateway = 'http://api.kuaidi100.com/api';
		
		$params['id'] 	= $this->config['key'];
		$params['com'] 	= $order->express_comkey;
		$params['nu']	= $order->express_no;
		$params['show'] = 2;
		$params['muti'] = 1;
		$params['order']= 'desc';
		
		$result = Basewind::curl($this->gateway.'?'.http_build_query($params));
		$result = json_decode($result, true);
			
		// status 查询的结果状态。0：运单暂无结果，1：查询成功，2：接口出现异常，408：验证码出错（仅适用于APICode url，可忽略)   
		if($result && ($result['status'] == 0 || $result['status'] == 1)) {
			$result['done'] = true;
		}
		return $result;
	}
	
	/* 免费版（暂时先保留）返回固定格式的HTML（一个URL链接），并带广告 较稳定，体验不友好 */
	private function queryIframe($post = null, $order = null)
	{
		$this->gateway = 'http://www.kuaidi100.com/applyurl';
		
		$params['key']  = $this->config['key'];
		$params['com'] 	= $order->express_comkey;
		$params['nu']	= $order->express_no;
		
		$result = Basewind::curl($this->gateway.'?'. http_build_query($params));
		$array = json_decode($result, true);
		if(isset($array['status'])) {
			$result = $array;
		}
		
		return is_string($result) ? ['url' => $result] : $result;
	}

	/**
     * 获取SDK实例
     */
    public function getClient()
    {
        if($this->client === null) {
            $this->client = new SDK($this->config);
        }
        return $this->client;
    }
}