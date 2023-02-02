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


/**
 * @Id BankModel.php 2018.7.4 $
 * @author mosir
 */

class CartModel extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%cart}}';
    }

    // 关联表
    public function getGoodsSpec()
    {
        return parent::hasOne(GoodsSpecModel::className(), ['spec_id' => 'spec_id']);
    }

    /**
     * 根据购买量执行阶梯价策略
     */
    public static function reBuildByQuantity($list = [], $otype = 'normal')
    {
        // 针对批发模式
        return WholesaleModel::reBuildByQuantity($list, $otype);
    }
}
