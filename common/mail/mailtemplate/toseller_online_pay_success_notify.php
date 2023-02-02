<?php
return array (
  'version' => '1.0',
  'subject' => '{$site_name}提醒:买家{$order.buyer_name}已付款',
  'content' => '<p>尊敬的{$order.seller_name}:</p>
<p style="padding-left: 30px;">买家{$order.buyer_name}已通过线上支付完成了订单{$order.order_sn}的付款，请核实并尽快安排发货。</p>
<p style="padding-left: 30px;">查看订单详细信息请点击以下链接</p>
<p style="padding-left: 30px;"><a href="{url route=\'seller_order/view\' order_id=$order.order_id baseUrl=$site_url}">{url route=\'seller_order/view\' order_id=$order.order_id}</a></p>
<p style="padding-left: 30px;">查看您的订单列表管理页请点击以下链接</p>
<p style="padding-left: 30px;"><a href="{url route=\'seller_order/index\' baseUrl=$site_url}">{url route=\'seller_order/index\'}</a></p>
<p style="text-align: right;">{$site_name}</p>
<p style="text-align: right;">{$send_time}</p>',
);