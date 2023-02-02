<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace frontend\models;

use common\library\Basewind;
use Yii;
use yii\base\Model;
use yii\captcha\CaptchaValidator;

use common\models\DepositTradeModel;
use common\models\DepositAccountModel;

use common\library\Language;
use common\library\Business;

/**
 * @Id DepositWithdrawconfirmForm.php 2018.9.30 $
 * @author mosir
 */
class DepositWithdrawconfirmForm extends Model
{
	public $errors = null;

	public function valid($post, $strict = true)
	{
		if (empty($post->account)) {
			$this->errors = Language::get('withdraw_account_empty');
			return false;
		}

		// 提现到银行卡，验证银行卡信息
		if ($post->drawtype == 'bank' && empty($post->bank)) {
			$this->errors = Language::get('bank_error');
			return false;
		}

		if (empty($post->name)) {
			$this->errors = Language::get('withdraw_name_error');
			return false;
		}

		// 验证提现金额
		if (empty($post->money) || !is_numeric($post->money) || ($post->money <= 0)) {
			$this->errors = Language::get('money_error');
			return false;
		}

		// 提现金额要减掉不可提现的部分
		$query = DepositAccountModel::find()->select('money,nodrawal')->where(['userid' => Yii::$app->user->id])->one();
		if (!$query || ($query->money - $query->nodrawal < $post->money)) {
			$this->errors = Language::get('money_not_enough');
			return false;
		}

		if ($strict) {
			if (Basewind::getCurrentApp() != 'api') {
				$captchaValidator = new CaptchaValidator(['captchaAction' => 'default/captcha']);
				if (!$captchaValidator->validate($post->captcha)) {
					$this->errors = Language::get('captcha_failed');
					return false;
				}
			}

			if (!DepositAccountModel::checkAccountPassword($post->password, Yii::$app->user->id)) {
				$this->errors = Language::get('password_error');
				return false;
			}
		}

		return true;
	}

	public function save($post, $valid = true)
	{
		if ($valid === true && !$this->valid($post)) {
			return false;
		}

		$tradeNo = DepositTradeModel::genTradeNo();

		// 转到对应的业务实例，不同的业务实例用不同的文件处理，如购物，卖出商品，充值，提现等，每个业务实例又继承支出或者收入
		$depopay_type = Business::getInstance('depopay')->build('withdraw', $post);
		$result = $depopay_type->submit(array(
			'trade_info' => array('userid' => Yii::$app->user->id, 'party_id' => 0, 'amount' => $post->money),
			'extra_info' => array('tradeNo' => $tradeNo)
		));

		if (!$result) {
			$this->errors = $depopay_type->errors;
			return false;
		}
		return $tradeNo;
	}
}
