<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\plugins\connect\weixinapp;

/**
 * @Id plugin.info.php 2018.6.3 $
 * @author mosir
 */

return array(
    'code' => 'weixinapp',
    'name' => '微信APP登录',
    'desc' => '适用于APP客户端(Android && iOS)/PC端微信扫码登录，请填写微信开放平台秘钥',
    'author' => 'SHOPWIND',
    'website' => 'https://www.shopwind.net',
    'version' => '1.0',
    'config' => array(
        'appId' => array(
            'type' => 'text',
            'text' => 'AppId'
        ),
        'appKey' => array(
            'type' => 'text',
            'text' => 'AppSecret'
        )
    )
);
