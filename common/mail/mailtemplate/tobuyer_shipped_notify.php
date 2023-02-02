<?php
return array (
  'version' => '1.0',
  'subject' => '{$site_name}提醒:您的订单{$order.order_sn}已发货',
  'content' => '<p>尊敬的{$order.buyer_name}:</p>
<p style="padding-left: 30px;">与您交易的店铺{$order.seller_name}已经给您的订单{$order.order_sn}发货了，请注意查收。</p>
<p style="padding-left: 30px;">{if $order.express_no}发货单号：{$order.express_no|escape}{/if}</p>
<p style="padding-left: 30px;">查看订单详细信息请点击以下链接</p>
<p style="padding-left: 30px;"><a href="{url route=\'buyer_order/view\' order_id=$order.order_id baseUrl=$site_url}">{url route=\'buyer_order/view\' order_id=$order.order_id}</a></p>
<p style="text-align: right;">{$site_name}</p>
<p style="text-align: right;">{$send_time}</p>',
);