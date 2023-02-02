<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */
 
namespace common\business\depopaytypes;

use yii;

use common\models\DepositTradeModel;
use common\models\DepositWithdrawModel;

use common\library\Language;
use common\library\Timezone;
use common\library\Def;

/**
 * @Id WithdrawDepopay.php 2018.4.16 $
 * @author mosir
 */

class WithdrawDepopay extends OutlayDepopay
{
    /**
	 * 针对交易记录的交易分类，值有：购物：SHOPPING； 理财：FINANCE；缴费：CHARGE； 还款：CCR；转账：TRANSFER ...
	 */
	public $_tradeCat	= 'WITHDRAW'; 
	
	/**
	 * 针对财务明细的资金用途，值有：在线支付：PAY；充值：RECHARGE；提现：WITHDRAW; 服务费：SERVICE；转账：TRANSFER
	 */
    public $_tradeType 	= 'WITHDRAW';
	
	public function submit($data = array())
	{
        extract($data);
		
        // 处理交易基本信息
        $base_info = parent::_handle_trade_info($trade_info, $extra_info);
		if(!$base_info) {
			return false;
		}
		
		//$tradeNo = $extra_info['tradeNo'];
		
		// 开始插入收支记录
		if(!$this->_insert_record_info($trade_info, $extra_info)) {
			$this->setErrors("50016");
			return false;
		}
		
		// 将提现的金额(加手续费)设置为冻结金额
		if(!parent::_update_deposit_frozen($trade_info['userid'], $trade_info['amount'], 'add')) {
			$this->setErrors("50017");
			return false;
		}
		
		// 保存提现信息
		if(!$this->_insert_withdraw_info($trade_info, $extra_info)){
			$this->setErrors("50019");
			return false;
		}
					
		return true;
	}
	
	/* 插入收支记录，并变更账户余额 */
	public function _insert_record_info($trade_info, $extra_info)
	{
		$time 				= Timezone::gmtime();
		$bizOrderId			= DepositTradeModel::genTradeNo(12, 'bizOrderId');
		
		$data_trade = array(
			'tradeNo'		=>	$extra_info['tradeNo'],
			'bizOrderId'	=>  $bizOrderId,
			'bizIdentity'	=>  Def::TRADE_DRAW,
			'buyer_id'		=>	$trade_info['userid'],
			'seller_id'		=>	0,
			'amount'		=>	$trade_info['amount'],
			'status'		=>	'VERIFY',
			'payment_code'  =>  'deposit',
			'tradeCat'		=>	$this->_tradeCat,
			'payType'		=>  $this->_payType,
			'flow'			=>	$this->_flow,
			'title'			=>  Language::get(strtoupper($this->_tradeType)),
			'buyer_remark'	=>	$this->post->remark ? $this->post->remark : '',
			'add_time'		=>	$time,
			'pay_time'		=>	$time,
		);
		
		$model = new DepositTradeModel();
		foreach($data_trade as $key => $val) {
			$model->$key = $val;
		}
		
		if($model->save(false))
		{
			$data_record = array(
				'tradeNo'		=>	$extra_info['tradeNo'],
				'userid'		=> 	$trade_info['userid'],
				'amount'		=>  $trade_info['amount'],
				'balance'		=>	parent::_update_deposit_money($trade_info['userid'], $trade_info['amount'], 'reduce'), // 扣除后的余额
				'tradeType'		=>  $this->_tradeType,
				'flow'			=>	$this->_flow,
			);
			return parent::_insert_deposit_record($data_record, false);
		}
	}
	
	public function _insert_withdraw_info($trade_info, $extra_info)
	{
		$model = new DepositWithdrawModel();
		$model->orderId = DepositTradeModel::find()->select('bizOrderId')->where(['tradeNo' => $extra_info['tradeNo']])->scalar();
		$model->userid = $trade_info['userid'];
		$model->drawtype = $this->post->drawtype;
		$model->terminal = isset($this->post->terminal) ? $this->post->terminal : '';
		$model->account = $this->post->account;
		$model->name = $this->post->name;
		
		if($this->post->drawtype == 'bank') {
			$model->bank = $this->post->bank;
		}
		
		return $model->save(false);
	}
}