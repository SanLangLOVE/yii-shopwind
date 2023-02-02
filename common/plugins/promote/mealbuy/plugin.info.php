<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\plugins\promote\mealbuy;

use yii;
use yii\helpers\Url;

use common\library\Basewind;
use common\library\Language;

/**
 * @Id plugin.info.php 2018.8.3 $
 * @author mosir
 */
return array(
    'code' => 'mealbuy',
    'name' => '搭配购',
    'desc' => '设置一次购买多个商品执行组合优惠策略',
    'author' => 'SHOPWIND',
	'website' => 'https://www.shopwind.net',
    'version' => '1.0',
    'category' => 'store', // user/store
    'icon' => 'icon-dapeitaocan',
    'buttons' => array(
        array(
            'label' => Language::get('setting'),
            'url' => Url::toRoute(['seller_meal/index'], Basewind::homeUrl()),
            'dialog' => true
        )
    )
);