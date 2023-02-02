<?php
return array (
  'version' => '1.0',
  'subject' => '{$site_name}提醒:您有一个新订单需要处理',
  'content' => '<p>尊敬的{$order.seller_name}:</p>
<p style="padding-left: 30px;">您有一个新的订单需要处理，订单号{$order.order_sn}，请尽快处理。</p>
<p style="padding-left: 30px;">查看订单详细信息请点击以下链接</p>
<p style="padding-left: 30px;"><a href="{url route=\'seller_order/view\' order_id=$order.order_id baseUrl=$site_url}">{url route=\'seller_order/view\' order_id=$order.order_id}</a></p>
<p style="padding-left: 30px;">查看您的订单列表管理页请点击以下链接</p>
<p style="padding-left: 30px;"><a href="{url route=\'seller_order/index\' baseUrl=$site_url}">{url route=\'seller_order/index\'}</a></p>
<p style="text-align: right;">{$site_name}</p>
<p style="text-align: right;">{$send_time}</p>',
);