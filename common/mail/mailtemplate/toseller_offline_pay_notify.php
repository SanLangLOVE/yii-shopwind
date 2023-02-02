<?php
return array (
  'version' => '1.0',
  'subject' => '{$site_name}提醒:买家{$order.buyer_name}已通过线下支付支付了订单的费用',
  'content' => '<p>尊敬的{$order.seller_name}:</p>
<p style="padding-left: 30px;">买家{$order.buyer_name}已通过线下支付支付了与您交易的订单{$order.order_sn}。请注意查收。</p>
<p style="padding-left: 30px;">{if $pay_message}支付信息：{$pay_message|escape}{/if}</p>
<p style="padding-left: 30px;">查看订单详细信息请点击以下链接</p>
<p style="padding-left: 30px;"><a href="{url route=\'seller_order/view\' order_id=$order.order_id baseUrl=$site_url}">{url route=\'seller_order/view\' order_id=$order.order_id}</a></p>
<p style="padding-left: 30px;">查看您的订单列表管理页请点击以下链接</p>
<p style="padding-left: 30px;"><a href="{url route=\'seller_order/index\' baseUrl=$site_url}">{url route=\'seller_order/index\'}</a></p>
<p style="text-align: right;">{$site_name}</p>
<p style="text-align: right;">{$send_time}</p>',
);