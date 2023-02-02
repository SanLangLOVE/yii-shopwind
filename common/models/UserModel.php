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
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use yii\helpers\ArrayHelper;

use common\models\StoreModel;
use common\models\UserEnterModel;

use common\library\Basewind;
use common\library\Timezone;
use common\library\Language;
use common\library\Def;
use common\library\Page;

/**
 * @Id UserModel.php 2018.3.5 $
 * @author mosir
 *
 * @property integer $userid
 * @property string $username
 * @property string $password
 * @property string $password_reset_token
 * @property string $email
 * @property string $auth_key
 * //@property integer $status
 * //@property integer $created_at
 * //@property integer $updated_at
 */
class UserModel extends ActiveRecord implements IdentityInterface
{
    const STATUS_DELETED = 0;
    const STATUS_ACTIVE = 10;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%user}}';
    }
	
	// 关联表
	public function getUserPriv()
	{
		return parent::hasMany(UserPrivModel::className(), ['userid' => 'userid']);
	}
	
	// 关联表
	public function getStore()
	{
		return parent::hasOne(StoreModel::className(), ['store_id' => 'userid']);
	}
	// 关联表
	public function getIntegral()
	{
		return parent::hasOne(IntegralModel::className(), ['userid' => 'userid']);
	}
	// 关联表
	public function getDepositAccount()
	{
		return parent::hasOne(DepositAccountModel::className(), ['userid' => 'userid']);
	}
	
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
		return [
   			[
   				'class' => TimestampBehavior::className(),
				'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['create_time', 'update_time'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['update_time']
                ],
              	'value' => Timezone::gmtime()
          	]
      	];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            //['status', 'default', 'value' => self::STATUS_ACTIVE],
            //['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_DELETED]],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne(['userid' => $id/*, 'status' => self::STATUS_ACTIVE*/]);
    }
	
    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username/*, 'status' => self::STATUS_ACTIVE*/]);
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            //'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return bool
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int) substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        return $timestamp + $expire >= Timezone::gmtime();
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, /*$this->password_hash*/ $this->password);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        //$this->password_hash = Yii::$app->security->generatePasswordHash($password);
		$this->password = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . Timezone::gmtime();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
	}
	
	/* 获取用户信息，去掉不安全字段 */
	public static function get($id)
	{
		$identity = static::findIdentity($id);
		unset($identity->password, $identity->password_reset_token, $identity->auth_key);
		return ArrayHelper::toArray($identity);
	}
	
	/* 登录成功后处理（临时方案）*/
	public static function afterLogin($identity)
	{
		$identity->last_login = Timezone::gmtime();
		$identity->last_ip = Yii::$app->request->userIP;
		$identity->logins++;
		$identity->save(false);
		
		// 只记录登入后台的记录
		if(Basewind::getCurrentApp() == 'admin') {
			UserEnterModel::enter($identity);
		}
		
		// 暂时不用这种权限分配方式了，先保留
		//self::assignRole($identity); 
		
		// 将session中的购物车商品移到数据库
		Yii::$app->cart->move();
	}
	
	/* 用户登录后的权限分配（临时方案）*/
	public static function assignRole($identity)
	{
		$auth = Yii::$app->authManager;
		if(StoreModel::find()->where(['store_id' => $identity->userid])->exists())
		{
			// 增加卖家角色
			if(!($role = $auth->getRule('seller'))) {
				$role = $auth->createRole('seller');
				$auth->add($role);
			}
			if(!$auth->getAssignment('seller', $identity->userid)) {
				$auth->assign($role, $identity->userid);
			}
		}	
	}
    
    /**
     * 生成不重复的用户名
     */
	public static function generateName($prefix = 'sw')
	{
        if($prefix === null) {
            $prefix = 'sw';
        }
		$username = $prefix . Timezone::localDate('shymd', true) . mt_rand(100,999);
		if(!self::findByUsername($username)) {
			return $username;
		}
		return self::generateName($prefix);	
	}
}
