<?php 
return array (
  'version' => '1.0',
  'subject' => '您获得了来自{$coupon.store_name}的优惠券',
  'content' => '<p>尊敬的{$user.username}，</p>
<p>&nbsp;&nbsp;&nbsp; 您好，恭喜您获得了一个来自【{$coupon.store_name}】的优惠券。</p>
<p>&nbsp;&nbsp;&nbsp; 优惠金额：{$coupon.coupon_value|price_format}</p>
<p>&nbsp;&nbsp;&nbsp; 有效期：{$coupon.start_time|date_format:"%Y.%m.%d %H:%M:%S"}至{$coupon.end_time|date_format:"%Y.%m.%d %H:%M:%S"}</p>
<p>&nbsp;&nbsp;&nbsp; 优惠券号码：{$user.coupon_sn}</p>
<p>&nbsp;&nbsp;&nbsp; 使用条件：购物满{$coupon.min_amount|price_format}即可使用</p>
<p>&nbsp;&nbsp;&nbsp; 店铺地址：<a href="{url route=\'store/index\' id=$coupon.store_id baseUrl=$site_url}">{url route=\'store/index\' id=$coupon.store_id}</a></p>
<p style="padding-left: 30px;">&nbsp;</p>
<p style="text-align: right;">网站名称：{$site_name}</p>
<p style="text-align: right;">日期：{$send_time}</p>',
);