<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace common\plugins\connect\qq;

/**
 * @Id plugin.info.php 2018.6.3 $
 * @author mosir
 */

return array(
    'code' => 'qq',
    'name' => '腾讯QQ登录',
    'desc' => '适用于PC端或H5端登录，只需要填写QQ互联开放平台的网站应用秘钥。APP端登录也需要开启该插件，在移动端源码打包时填写QQ互联移动应用秘钥',
    'author' => 'SHOPWIND',
	'website' => 'https://www.shopwind.net',
    'version' => '1.0',
    'config' => array(
        'appId' => array(
            'type' => 'text',
            'text' => 'appId'
        ),
        'appKey' => array(
            'type' => 'text',
            'text' => 'appKey'
        ),
    )
);