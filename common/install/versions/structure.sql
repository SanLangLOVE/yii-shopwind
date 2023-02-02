-- phpMyAdmin SQL Dump
-- version phpStudy 2014
-- http://www.phpmyadmin.net
--
-- 主机: localhost
-- 生成日期: 2018 年 11 月 21 日 10:36
-- 服务器版本: 5.5.36
-- PHP 版本: 5.4.26

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- 数据库: `shopwind`
--

-- --------------------------------------------------------

--
-- 表的结构 `swd_acategory`
--
DROP TABLE IF EXISTS `swd_acategory`;
CREATE TABLE IF NOT EXISTS `swd_acategory` (
  `cate_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cate_name` varchar(100) NOT NULL DEFAULT '',
  `parent_id` int(10) unsigned DEFAULT '0',
  `store_id` int(10) DEFAULT '0',
  `sort_order` tinyint(3) unsigned DEFAULT '255',
  `if_show` int(1) DEFAULT '1',
  PRIMARY KEY (`cate_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_address`
--
DROP TABLE IF EXISTS `swd_address`;
CREATE TABLE IF NOT EXISTS `swd_address` (
  `addr_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `consignee` varchar(60) NOT NULL DEFAULT '',
  `region_id` int(10) unsigned NOT NULL DEFAULT '0',
  `region_name` varchar(255) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `zipcode` varchar(20) DEFAULT '',
  `phone_tel` varchar(60) DEFAULT '',
  `phone_mob` varchar(20) DEFAULT '',
  `defaddr` tinyint(3) DEFAULT '0',
  PRIMARY KEY (`addr_id`),
  KEY `userid` (`userid`),
  KEY `region_id` (`region_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_appbuylog`
--
DROP TABLE IF EXISTS `swd_appbuylog`;
CREATE TABLE IF NOT EXISTS `swd_appbuylog` (
  `bid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `orderId` varchar(20) NOT NULL,
  `appid` varchar(20) NOT NULL,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `period` int(11) DEFAULT '0',
  `amount` decimal(10,2) DEFAULT '0',
  `status` tinyint(3) DEFAULT '0',
  `add_time` int(11) DEFAULT NULL,
  `pay_time` int(11) DEFAULT NULL,
  `end_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`bid`),
  KEY `bid` (`bid`),
  KEY `orderId` (`orderId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_appmarket`
--
DROP TABLE IF EXISTS `swd_appmarket`;
CREATE TABLE IF NOT EXISTS `swd_appmarket` (
  `aid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `appid` varchar(20) NOT NULL,
  `title` varchar(100) DEFAULT '',
  `summary` varchar(255) DEFAULT NULL,
  `category` int(11) DEFAULT '0',
  `description` text DEFAULT NULL,
  `logo` varchar(200) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT '0.00',
  `sales` int(11) DEFAULT '0',
  `views` int(11) DEFAULT '0',
  `status` tinyint(1) DEFAULT '0',
  `add_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`aid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_apprenewal`
--
DROP TABLE IF EXISTS `swd_apprenewal`;
CREATE TABLE IF NOT EXISTS `swd_apprenewal` (
  `rid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `appid` varchar(20) NOT NULL,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `add_time` int(11) unsigned DEFAULT NULL,
  `expired` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`rid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_article`
--
DROP TABLE IF EXISTS `swd_article`;
CREATE TABLE IF NOT EXISTS `swd_article` (
  `article_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) DEFAULT '',
  `cate_id` int(10) DEFAULT '0',
  `store_id` int(10) unsigned DEFAULT '0',
  `link` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` tinyint(3) unsigned DEFAULT '255',
  `if_show` tinyint(3) unsigned DEFAULT '1',
  `add_time` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`article_id`),
  KEY `cate_id` (`cate_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_bank`
--
DROP TABLE IF EXISTS `swd_bank`;
CREATE TABLE IF NOT EXISTS `swd_bank` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `bank` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `account` varchar(50) NOT NULL,
  `area` varchar(100) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_bind`
--
DROP TABLE IF EXISTS `swd_bind`;
CREATE TABLE IF NOT EXISTS `swd_bind` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `unionid` varchar(255) NOT NULL,
  `openid` varchar(255) DEFAULT '',
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `code` varchar(50) DEFAULT '',
  `token` varchar(255) DEFAULT '',
  `nickname` varchar(60) DEFAULT '',
  `enabled` int(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_brand`
--
DROP TABLE IF EXISTS `swd_brand`;
CREATE TABLE IF NOT EXISTS `swd_brand` (
  `brand_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `brand_name` varchar(100) DEFAULT '',
  `brand_logo` varchar(255) DEFAULT NULL,
  `brand_image` varchar(255) DEFAULT NULL,
  `cate_id` int(11) DEFAULT '0',
  `sort_order` tinyint(3) unsigned DEFAULT '255',
  `recommended` tinyint(3) unsigned DEFAULT '0',
  `store_id` int(10) unsigned DEFAULT '0',
  `if_show` tinyint(2) unsigned DEFAULT '1',
  `tag` varchar(100) DEFAULT '',
  `letter` varchar(10) DEFAULT '',
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`brand_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_cart`
--
DROP TABLE IF EXISTS `swd_cart`;
CREATE TABLE IF NOT EXISTS `swd_cart` (
  `rec_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `store_id` int(10) unsigned NOT NULL DEFAULT '0',
  `goods_id` int(10) unsigned NOT NULL DEFAULT '0',
  `goods_name` varchar(255) DEFAULT '',
  `spec_id` int(10) unsigned DEFAULT '0',
  `specification` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) unsigned DEFAULT '0.00',
  `quantity` int(10) unsigned DEFAULT '1',
  `goods_image` varchar(255) DEFAULT NULL,
  `selected` tinyint(1) unsigned DEFAULT '0',
  `product_id` varchar(32) DEFAULT '',
  `invalid` int(11) DEFAULT '0',
  PRIMARY KEY (`rec_id`),
  KEY `userid` (`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_cashcard`
--
DROP TABLE IF EXISTS `swd_cashcard`;
CREATE TABLE IF NOT EXISTS `swd_cashcard` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) DEFAULT NULL,
  `cardNo` varchar(30) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `money` decimal(10,2) DEFAULT NULL,
  `useId` int(11) unsigned DEFAULT '0',
  `printed` int(1) unsigned DEFAULT '0',
  `add_time` int(11) DEFAULT NULL,
  `active_time` int(11) DEFAULT NULL,
  `expire_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_category_goods`
--
DROP TABLE IF EXISTS `swd_category_goods`;
CREATE TABLE IF NOT EXISTS `swd_category_goods` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cate_id` int(10) unsigned DEFAULT '0',
  `goods_id` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `cate_id` (`cate_id`),
  KEY `goods_id` (`goods_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_category_store`
--
DROP TABLE IF EXISTS `swd_category_store`;
CREATE TABLE IF NOT EXISTS `swd_category_store` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cate_id` int(10) unsigned DEFAULT '0',
  `store_id` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `cate_id` (`cate_id`),
  KEY `store_id` (`store_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_cate_pvs`
--
DROP TABLE IF EXISTS `swd_cate_pvs`;
CREATE TABLE IF NOT EXISTS `swd_cate_pvs` (
  `cate_id` int(11) NOT NULL,
  `pvs` text DEFAULT '',
  PRIMARY KEY (`cate_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `swd_channel`
--
DROP TABLE IF EXISTS `swd_channel`;
CREATE TABLE IF NOT EXISTS `swd_channel` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cid` varchar(20) DEFAULT '',
  `title` varchar(50) DEFAULT '',
  `style` int(11) DEFAULT '1',
  `cate_id` int(11) DEFAULT '0',
  `status` int(11) DEFAULT '1',
  `add_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_cod`
--
DROP TABLE IF EXISTS `swd_cod`;
CREATE TABLE IF NOT EXISTS `swd_cod` (
  `store_id` int(10) NOT NULL,
  `regions` text,
  `status` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`store_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `swd_collect`
--
DROP TABLE IF EXISTS `swd_collect`;
CREATE TABLE IF NOT EXISTS `swd_collect` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `type` varchar(10) DEFAULT 'goods',
  `item_id` int(10) unsigned DEFAULT '0',
  `keyword` varchar(60) DEFAULT NULL,
  `add_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`),
  KEY `item_id` (`item_id`),
  KEY `type` (`type`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_coupon`
--
DROP TABLE IF EXISTS `swd_coupon`;
CREATE TABLE IF NOT EXISTS `swd_coupon` (
  `coupon_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned DEFAULT '0',
  `coupon_name` varchar(100) DEFAULT '',
  `coupon_value` decimal(10,2) unsigned DEFAULT '0.00',
  `use_times` int(10) unsigned DEFAULT '0',
  `start_time` int(10) unsigned DEFAULT NULL,
  `end_time` int(10) unsigned DEFAULT NULL,
  `min_amount` decimal(10,2) unsigned DEFAULT '0.00',
  `available` int(11) DEFAULT '1',
  `image` varchar(255) DEFAULT NULL,
  `total` int(11) DEFAULT '0',
  `surplus` int(11) DEFAULT '0',
  `clickreceive` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`coupon_id`),
  KEY `store_id` (`store_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_coupon_sn`
--
DROP TABLE IF EXISTS `swd_coupon_sn`;
CREATE TABLE IF NOT EXISTS `swd_coupon_sn` (
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `coupon_sn` varchar(20) NOT NULL DEFAULT '',
  `coupon_id` int(10) unsigned NOT NULL DEFAULT '0',
  `remain_times` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`coupon_sn`),
  KEY `coupon_id` (`coupon_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `swd_delivery_template`
--
DROP TABLE IF EXISTS `swd_delivery_template`;
CREATE TABLE IF NOT EXISTS `swd_delivery_template` (
  `template_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT '',
  `store_id` int(10) DEFAULT '0',
  `types` text DEFAULT '',
  `dests` text DEFAULT '',
  `start_standards` text DEFAULT '',
  `start_fees` text DEFAULT '',
  `add_standards` text DEFAULT '',
  `add_fees` text DEFAULT '',
  `created` int(11) DEFAULT NULL,
  PRIMARY KEY (`template_id`),
  KEY `store_id` (`store_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_deposit_account`
--
DROP TABLE IF EXISTS `swd_deposit_account`;
CREATE TABLE IF NOT EXISTS `swd_deposit_account` (
  `account_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `account` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT '',
  `money` decimal(10,2) DEFAULT '0',
  `frozen` decimal(10,2) DEFAULT '0',
  `nodrawal` decimal(10,2) DEFAULT '0',
  `real_name` varchar(30) DEFAULT NULL,
  `pay_status` varchar(3) DEFAULT 'off',
  `add_time` int(11) DEFAULT NULL,
  `last_update` int(11) DEFAULT NULL,
  PRIMARY KEY (`account_id`),
  KEY `userid` (`userid`),
  KEY `account` (`account`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_deposit_recharge`
--
DROP TABLE IF EXISTS `swd_deposit_recharge`;
CREATE TABLE IF NOT EXISTS `swd_deposit_recharge` (
  `recharge_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `orderId` varchar(30) NOT NULL,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `examine` varchar(100) DEFAULT '',
  `is_online` int(1) DEFAULT '1',
  PRIMARY KEY (`recharge_id`),
  KEY `orderId` (`orderId`),
  KEY `userid` (`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_deposit_record`
--
DROP TABLE IF EXISTS `swd_deposit_record`;
CREATE TABLE IF NOT EXISTS `swd_deposit_record` (
  `record_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tradeNo` varchar(30) NOT NULL,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `amount` decimal(10,2) DEFAULT '0' COMMENT '收支金额',
  `balance` decimal(10,2) DEFAULT '0' COMMENT '账户余额',
  `flow` varchar(10) DEFAULT 'outlay' COMMENT '资金方向',
  `tradeType` varchar(20) DEFAULT 'PAY' COMMENT '收支类型',
  `name` varchar(100) DEFAULT '' COMMENT '名称',
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  PRIMARY KEY (`record_id`),
  KEY `tradeNo` (`tradeNo`),
  KEY `userid` (`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_deposit_setting`
--
DROP TABLE IF EXISTS `swd_deposit_setting`;
CREATE TABLE IF NOT EXISTS `swd_deposit_setting` (
  `setting_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `trade_rate` decimal(10,3) DEFAULT '0' COMMENT '交易手续费',
  `transfer_rate` decimal(10,3) DEFAULT '0' COMMENT '转账手续费',
  `regive_rate` decimal(10,3) DEFAULT '0' COMMENT '充值赠送金额比率',
  `guider_rate` decimal(10,3) DEFAULT '0' COMMENT '团长返佣比率',
  PRIMARY KEY (`setting_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_deposit_trade`
--
DROP TABLE IF EXISTS `swd_deposit_trade`;
CREATE TABLE IF NOT EXISTS `swd_deposit_trade` (
  `trade_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tradeNo` varchar(32) NOT NULL COMMENT '交易号',
  `outTradeNo` varchar(255) DEFAULT '' COMMENT '第三方支付接口的交易号',
  `payTradeNo` varchar(32) DEFAULT '' COMMENT '支付订单号',
  `bizOrderId` varchar(32) DEFAULT '' COMMENT '商户订单号',
  `bizIdentity` varchar(20) DEFAULT '' COMMENT '商户交易类型识别号',
  `buyer_id` int(11) NOT NULL COMMENT '交易买家',
  `seller_id` int(11) NOT NULL COMMENT '交易卖家',
  `amount` decimal(10,2) DEFAULT '0' COMMENT '交易金额',
  `status` varchar(30) DEFAULT '',
  `payment_code` varchar(20) COMMENT '支付方式代号',
  `pay_alter` int(11) DEFAULT '0' COMMENT '支付方式变更标记',
  `tradeCat` varchar(20) DEFAULT NULL COMMENT '交易分类',
  `payType` varchar(20) DEFAULT NULL COMMENT '支付类型(担保即时)',
  `flow` varchar(10) DEFAULT 'outlay' COMMENT '资金流向',
  `payTerminal` varchar(10) DEFAULT '' COMMENT '支付终端',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '交易标题',
  `buyer_remark` varchar(255) DEFAULT '' COMMENT '买家备注',
  `seller_remark` varchar(255) DEFAULT '' COMMENT '卖家备注',
  `add_time` int(11) DEFAULT NULL,
  `pay_time` int(11) DEFAULT NULL,
  `end_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`trade_id`),
  KEY `tradeNo` (`tradeNo`),
  KEY `bizOrderId` (`bizOrderId`),
  KEY `buyer_id` (`buyer_id`),
  KEY `seller_id` (`seller_id`),
  KEY `payTradeNo` (`payTradeNo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_deposit_withdraw`
--
DROP TABLE IF EXISTS `swd_deposit_withdraw`;
CREATE TABLE IF NOT EXISTS `swd_deposit_withdraw` (
  `draw_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `orderId` varchar(30) NOT NULL,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `drawtype` varchar(20) NOT NULL DEFAULT 'bank',
  `terminal` varchar(20) NOT NULL DEFAULT '',
  `account` varchar(255) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `bank` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`draw_id`),
  KEY `orderId` (`orderId`),
  KEY `userid` (`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_distribute`
--
DROP TABLE IF EXISTS `swd_distribute`;
CREATE TABLE IF NOT EXISTS `swd_distribute` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `amount` decimal(10,2) DEFAULT '0',
  `layer1` decimal(10,2) DEFAULT '0',
  `layer2` decimal(10,2) DEFAULT '0',
  `layer3` decimal(10,2) DEFAULT '0',
  `goods` decimal(10,2) DEFAULT '0',
  `store` decimal(10,2) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_distribute_items`
--
DROP TABLE IF EXISTS `swd_distribute_items`;
CREATE TABLE IF NOT EXISTS `swd_distribute_items` (
  `diid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `item_id` int(11) NOT NULL,
  `type` varchar(20) DEFAULT '',
  `created` int(11) DEFAULT NULL ,
  PRIMARY KEY (`diid`),
  KEY `userid` (`userid`),
  KEY `item_id` (`item_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_distribute_merchant`
--
DROP TABLE IF EXISTS `swd_distribute_merchant`;
CREATE TABLE IF NOT EXISTS `swd_distribute_merchant` (
  `dmid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `username` varchar(60) DEFAULT '',
  `parent_id` int(11) DEFAULT '0',
  `phone_mob` varchar(20) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL DEFAULT '',
  `logo` varchar(255) DEFAULT NULL,
  `status` int(11) DEFAULT '0',
  `created` int(11) DEFAULT NULL,
  PRIMARY KEY (`dmid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_distribute_order`
--
DROP TABLE IF EXISTS `swd_distribute_order`;
CREATE TABLE IF NOT EXISTS `swd_distribute_order` (
  `doid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `rec_id` int(11) NOT NULL,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `tradeNo` varchar(32) NOT NULL,
  `order_sn` varchar(20) DEFAULT '',
  `money` decimal(10,2) DEFAULT '0',
  `layer` int(11) DEFAULT '1',
  `ratio` decimal(10,2) DEFAULT '0',
  `type` varchar(20) DEFAULT '',
  `created` int(11) DEFAULT NULL,
  PRIMARY KEY (`doid`),
  KEY `rec_id` (`rec_id`),
  KEY `userid` (`userid`),
  KEY `order_sn` (`order_sn`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_distribute_setting`
--
DROP TABLE IF EXISTS `swd_distribute_setting`;
CREATE TABLE IF NOT EXISTS `swd_distribute_setting` (
  `dsid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) DEFAULT '',
  `item_id` int(11) DEFAULT '0',
  `ratio1` decimal(10,2) DEFAULT '0',
  `ratio2` decimal(10,2) DEFAULT '0',
  `ratio3` decimal(10,2) DEFAULT '0',
  `enabled` int(1) DEFAULT '1',
  PRIMARY KEY (`dsid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_flagstore`
--
DROP TABLE IF EXISTS `swd_flagstore`;
CREATE TABLE IF NOT EXISTS `swd_flagstore` (
  `fid` int(255) unsigned NOT NULL AUTO_INCREMENT,
  `brand_id` int(10) DEFAULT '0',
  `keyword` varchar(20) DEFAULT '',
  `cate_id` int(11) DEFAULT '0',
  `store_id` int(10) DEFAULT '0',
  `status` tinyint(1) DEFAULT '0',
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT '255',
  PRIMARY KEY (`fid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_friend`
--
DROP TABLE IF EXISTS `swd_friend`;
CREATE TABLE IF NOT EXISTS `swd_friend` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `friend_id` int(10) unsigned NOT NULL DEFAULT '0',
  `add_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`),
  KEY `friend_id` (`friend_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_gcategory`
--
DROP TABLE IF EXISTS `swd_gcategory`;
CREATE TABLE IF NOT EXISTS `swd_gcategory` (
  `cate_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned DEFAULT '0',
  `cate_name` varchar(100) NOT NULL DEFAULT '',
  `parent_id` int(10) unsigned DEFAULT '0',
  `groupid` int(11) DEFAULT '0',
  `sort_order` tinyint(3) unsigned DEFAULT '255',
  `if_show` tinyint(3) unsigned DEFAULT '1',
  `image` varchar(255) DEFAULT NULL,
  `ad` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`cate_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

-- --------------------------------------------------------

--
-- 表的结构 `swd_goods`
--
DROP TABLE IF EXISTS `swd_goods`;
CREATE TABLE IF NOT EXISTS `swd_goods` (
  `goods_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL DEFAULT '0',
  `type` varchar(10) DEFAULT 'material',
  `goods_name` varchar(255) NOT NULL DEFAULT '',
  `description` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `cate_id` int(10) unsigned DEFAULT '0',
  `cate_name` varchar(255) DEFAULT '',
  `brand` varchar(100)  DEFAULT '',
  `spec_qty` tinyint(4) unsigned DEFAULT '0',
  `spec_name_1` varchar(60) DEFAULT '',
  `spec_name_2` varchar(60) DEFAULT '',
  `if_show` tinyint(3) unsigned DEFAULT '1',
  `closed` tinyint(3) unsigned DEFAULT '0',
  `close_reason` varchar(255) DEFAULT NULL,
  `add_time` int(10) unsigned DEFAULT NULL,
  `last_update` int(10) unsigned DEFAULT NULL,
  `default_spec` int(11) unsigned DEFAULT '0',
  `default_image` varchar(255) DEFAULT NULL,
  `long_image` varchar(255) DEFAULT NULL,
  `video` varchar(255) DEFAULT NULL,
  `isnew` tinyint(4) unsigned DEFAULT '0',
  `recommended` tinyint(4) unsigned DEFAULT '0',
  `price` decimal(10,2) DEFAULT '0.00',
  `mkprice` decimal(10,2) DEFAULT '0.00',
  `tags` varchar(102) DEFAULT '',
  `dt_id` int(11) DEFAULT '0',
  PRIMARY KEY (`goods_id`),
  KEY `store_id` (`store_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_goods_image`
--
DROP TABLE IF EXISTS `swd_goods_image`;
CREATE TABLE IF NOT EXISTS `swd_goods_image` (
  `image_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(10) unsigned NOT NULL DEFAULT '0',
  `image_url` varchar(255) NOT NULL DEFAULT '',
  `thumbnail` varchar(255) DEFAULT '',
  `sort_order` tinyint(4) unsigned DEFAULT '0',
  `file_id` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`image_id`),
  KEY `goods_id` (`goods_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_goods_integral`
--
DROP TABLE IF EXISTS `swd_goods_integral`;
CREATE TABLE IF NOT EXISTS `swd_goods_integral` (
  `goods_id` int(10) NOT NULL,
  `max_exchange` int(11) DEFAULT '0',
  PRIMARY KEY (`goods_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `swd_goods_prop`
--
DROP TABLE IF EXISTS `swd_goods_prop`;
CREATE TABLE IF NOT EXISTS `swd_goods_prop` (
  `pid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `ptype` varchar(20) DEFAULT 'select',
  `is_color` int(1) DEFAULT '0',
  `status` int(1) DEFAULT '1',
  `sort_order` int(11) DEFAULT '255',
  PRIMARY KEY (`pid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_goods_prop_value`
--
DROP TABLE IF EXISTS `swd_goods_prop_value`;
CREATE TABLE IF NOT EXISTS `swd_goods_prop_value` (
  `vid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(11) DEFAULT '0',
  `pvalue` varchar(255) DEFAULT '',
  `color` varchar(255) DEFAULT '',
  `status` int(1) DEFAULT '1',
  `sort_order` int(11) DEFAULT '255',
  PRIMARY KEY (`vid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_goods_pvs`
--
DROP TABLE IF EXISTS `swd_goods_pvs`;
CREATE TABLE IF NOT EXISTS `swd_goods_pvs` (
  `goods_id` int(10) NOT NULL,
  `pvs` text DEFAULT '',
  PRIMARY KEY (`goods_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `swd_goods_qa`
--
DROP TABLE IF EXISTS `swd_goods_qa`;
CREATE TABLE IF NOT EXISTS `swd_goods_qa` (
  `ques_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `question_content` varchar(255) DEFAULT '',
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `store_id` int(10) unsigned DEFAULT '0',
  `email` varchar(60) DEFAULT '',
  `item_id` int(10) unsigned NOT NULL DEFAULT '0',
  `item_name` varchar(255) DEFAULT '',
  `reply_content` varchar(255) DEFAULT '',
  `time_post` int(10) unsigned DEFAULT NULL,
  `time_reply` int(10) unsigned DEFAULT NULL,
  `if_new` tinyint(3) unsigned DEFAULT '1',
  `type` varchar(10) DEFAULT 'goods',
  PRIMARY KEY (`ques_id`),
  KEY `userid` (`userid`),
  KEY `goods_id` (`item_id`),
  KEY `store_id` (`store_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_goods_spec`
--
DROP TABLE IF EXISTS `swd_goods_spec`;
CREATE TABLE IF NOT EXISTS `swd_goods_spec` (
  `spec_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(10) unsigned NOT NULL DEFAULT '0',
  `spec_1` varchar(60) DEFAULT '',
  `spec_2` varchar(60) DEFAULT '',
  `price` decimal(10,2) DEFAULT '0.00',
  `mkprice` decimal(10,2) DEFAULT '0.00',
  `stock` int(11) DEFAULT '0',
  `sku` varchar(60) DEFAULT '',
  `image` varchar(255) DEFAULT NULL,
  `sort_order` tinyint(3) unsigned DEFAULT '255',
  PRIMARY KEY (`spec_id`),
  KEY `goods_id` (`goods_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_goods_statistics`
--
DROP TABLE IF EXISTS `swd_goods_statistics`;
CREATE TABLE IF NOT EXISTS `swd_goods_statistics` (
  `goods_id` int(10) unsigned NOT NULL DEFAULT '0',
  `views` int(10) unsigned DEFAULT '0',
  `collects` int(10) unsigned DEFAULT '0',
  `orders` int(10) unsigned DEFAULT '0',
  `sales` int(10) unsigned DEFAULT '0',
  `comments` int(11) unsigned DEFAULT '0',
  PRIMARY KEY (`goods_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `swd_guideshop`
--
DROP TABLE IF EXISTS `swd_guideshop`;
CREATE TABLE IF NOT EXISTS `swd_guideshop` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `owner` varchar(50) NOT NULL DEFAULT '',
  `phone_mob` varchar(20) NOT NULL DEFAULT '',
  `region_id` int(11) unsigned DEFAULT 0,
  `region_name` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `banner` varchar(255) DEFAULT NULL,
  `longitude` varchar(20) DEFAULT '',
  `latitude` varchar(20) DEFAULT '',
  `created` int(11) DEFAULT NULL,
  `status` tinyint(3) unsigned DEFAULT '0',
  `remark` varchar(255) DEFAULT NULL,
  `inviter` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_integral`
--
DROP TABLE IF EXISTS `swd_integral`;
CREATE TABLE IF NOT EXISTS `swd_integral` (
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `amount` decimal(10,2) DEFAULT '0',
  PRIMARY KEY (`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `swd_integral_log`
--
DROP TABLE IF EXISTS `swd_integral_log`;
CREATE TABLE IF NOT EXISTS `swd_integral_log` (
  `log_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `order_id` int(10) NOT NULL DEFAULT '0',
  `order_sn` varchar(20) DEFAULT '',
  `changes` decimal(25,2) DEFAULT '0',
  `balance` decimal(25,2) DEFAULT '0',
  `type` varchar(50) DEFAULT '',
  `state` varchar(50) DEFAULT '',
  `flag` varchar(255) DEFAULT '',
  `add_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_integral_setting`
--
DROP TABLE IF EXISTS `swd_integral_setting`;
CREATE TABLE IF NOT EXISTS `swd_integral_setting` (
  `setting_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `rate` decimal(10,2) DEFAULT '0',
  `register` decimal(10,0) DEFAULT '0',
  `signin` decimal(10,0) DEFAULT '0',
  `openshop` decimal(10,0) DEFAULT '0',
  `buygoods` text DEFAULT NULL,
  `enabled` int(11) DEFAULT '0',
  PRIMARY KEY (`setting_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_limitbuy`
--
DROP TABLE IF EXISTS `swd_limitbuy`;
CREATE TABLE IF NOT EXISTS `swd_limitbuy` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(10) NOT NULL,
  `title` varchar(50) NOT NULL DEFAULT '',
  `summary` varchar(255) DEFAULT '',
  `start_time` int(11) DEFAULT NULL,
  `end_time` int(11) DEFAULT NULL,
  `store_id` int(10) DEFAULT '0',
  `rules` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_meal`
--
DROP TABLE IF EXISTS `swd_meal`;
CREATE TABLE IF NOT EXISTS `swd_meal` (
  `meal_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) NOT NULL,
  `title` varchar(255) DEFAULT '',
  `price` decimal(10,2) DEFAULT '0',
  `keyword` varchar(255) DEFAULT '',
  `description` text DEFAULT '',
  `status` int(1) DEFAULT '1',
  `created` int(11) DEFAULT NULL,
  PRIMARY KEY (`meal_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_meal_goods`
--
DROP TABLE IF EXISTS `swd_meal_goods`;
CREATE TABLE IF NOT EXISTS `swd_meal_goods` (
  `mg_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `meal_id` int(11) NOT NULL DEFAULT '0',
  `goods_id` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`mg_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_message`
--
DROP TABLE IF EXISTS `swd_message`;
CREATE TABLE IF NOT EXISTS `swd_message` (
  `msg_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `from_id` int(10) unsigned NOT NULL DEFAULT '0',
  `to_id` int(10) unsigned NOT NULL DEFAULT '0',
  `title` varchar(100) DEFAULT '',
  `content` text DEFAULT NULL,
  `add_time` int(10) unsigned DEFAULT NULL,
  `last_update` int(10) unsigned DEFAULT NULL,
  `new` tinyint(3) unsigned DEFAULT '0',
  `parent_id` int(10) unsigned DEFAULT '0',
  `status` tinyint(3) unsigned DEFAULT '0',
  PRIMARY KEY (`msg_id`),
  KEY `from_id` (`from_id`),
  KEY `to_id` (`to_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_msg`
--
DROP TABLE IF EXISTS `swd_msg`;
CREATE TABLE IF NOT EXISTS `swd_msg` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `num` int(10) unsigned DEFAULT '0',
  `functions` varchar(255) DEFAULT NULL,
  `state` tinyint(3) unsigned DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_msg_log`
--
DROP TABLE IF EXISTS `swd_msg_log`;
CREATE TABLE IF NOT EXISTS `swd_msg_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `receiver` varchar(20) NOT NULL DEFAULT '',
  `verifycode` int(10) unsigned DEFAULT NULL,
  `codekey` varchar(32) NOT NULL DEFAULT '',
  `content` text,
  `quantity` int(10) DEFAULT '0',
  `type` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `status` tinyint(3) DEFAULT '0',
  `message` varchar(100) DEFAULT NULL,
  `add_time` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_msg_template`
--
DROP TABLE IF EXISTS `swd_msg_template`;
CREATE TABLE IF NOT EXISTS `swd_msg_template` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `scene` varchar(50) NOT NULL,
  `signName` varchar(50) NOT NULL,
  `templateId` varchar(40) NOT NULL,
  `content` varchar(255) NOT NULL DEFAULT '',
  `add_time` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_navigation`
--
DROP TABLE IF EXISTS `swd_navigation`;
CREATE TABLE IF NOT EXISTS `swd_navigation` (
  `nav_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(10) DEFAULT 'middle',
  `title` varchar(60) NOT NULL DEFAULT '',
  `link` varchar(255) DEFAULT '',
  `sort_order` tinyint(3) unsigned DEFAULT '255',
  `if_show` int(1) DEFAULT '1',
  `open_new` tinyint(3) unsigned DEFAULT '0',
  `hot` tinyint(3) DEFAULT '0',
  PRIMARY KEY (`nav_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_order`
--
DROP TABLE IF EXISTS `swd_order`;
CREATE TABLE IF NOT EXISTS `swd_order` (
  `order_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_sn` varchar(20) NOT NULL DEFAULT '',
  `gtype` varchar(10) DEFAULT 'material',
  `otype` varchar(10) DEFAULT 'normal',
  `seller_id` int(10) unsigned NOT NULL DEFAULT '0',
  `seller_name` varchar(100) DEFAULT NULL,
  `buyer_id` int(10) unsigned NOT NULL DEFAULT '0',
  `buyer_name` varchar(100) DEFAULT NULL,
  `buyer_email` varchar(60) DEFAULT '',
  `status` tinyint(3) unsigned DEFAULT '0',
  `add_time` int(10) unsigned DEFAULT NULL,
  `payment_name` varchar(100) DEFAULT NULL,
  `payment_code` varchar(20) DEFAULT '',
  `out_trade_sn` varchar(20) DEFAULT '',
  `pay_time` int(10) unsigned DEFAULT NULL,
  `pay_message` varchar(255) DEFAULT '',
  `ship_time` int(10) unsigned DEFAULT NULL,
  `express_code` varchar(20) DEFAULT NULL,
  `express_no` varchar(50) DEFAULT NULL,
  `express_comkey` varchar(30) DEFAULT NULL,
  `express_company` varchar(50) DEFAULT NULL,
  `finished_time` int(10) unsigned DEFAULT NULL,
  `goods_amount` decimal(10,2) unsigned DEFAULT '0.00',
  `discount` decimal(10,2) unsigned DEFAULT '0.00',
  `order_amount` decimal(10,2) unsigned DEFAULT '0.00',
  `evaluation_status` tinyint(1) unsigned DEFAULT '0',
  `evaluation_time` int(10) unsigned DEFAULT NULL,
  `service_evaluation` decimal(10,2) DEFAULT '0.00',
  `shipped_evaluation` decimal(10,2) DEFAULT '0.00',
  `anonymous` tinyint(3) unsigned DEFAULT '0',
  `postscript` varchar(255) DEFAULT '',
  `pay_alter` tinyint(1) unsigned DEFAULT '0',
  `flag` int(1) DEFAULT '0',
  `memo` varchar(255) DEFAULT '',
  `adjust_amount` decimal(10,2) DEFAULT '0.00',
  `guider_id` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`order_id`),
  KEY `order_sn` (`order_sn`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_order_extm`
--
DROP TABLE IF EXISTS `swd_order_extm`;
CREATE TABLE IF NOT EXISTS `swd_order_extm` (
  `order_id` int(10) unsigned NOT NULL DEFAULT '0',
  `consignee` varchar(60) NOT NULL DEFAULT '',
  `region_id` int(10) unsigned NOT NULL DEFAULT '0',
  `region_name` varchar(255) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `zipcode` varchar(20) DEFAULT '',
  `phone_tel` varchar(60) DEFAULT '',
  `phone_mob` varchar(20) DEFAULT '',
  `shipping_name` varchar(100) DEFAULT NULL,
  `shipping_fee` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `swd_order_goods`
--
DROP TABLE IF EXISTS `swd_order_goods`;
CREATE TABLE IF NOT EXISTS `swd_order_goods` (
  `rec_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL DEFAULT '0',
  `goods_id` int(10) unsigned NOT NULL DEFAULT '0',
  `goods_name` varchar(255) DEFAULT '',
  `spec_id` int(10) unsigned DEFAULT '0',
  `specification` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) unsigned DEFAULT '0.00',
  `quantity` int(10) unsigned DEFAULT '1',
  `goods_image` varchar(255) DEFAULT NULL,
  `evaluation` tinyint(1) unsigned DEFAULT '0',
  `comment` varchar(255) DEFAULT '',
  `images` text DEFAULT '',
  `is_valid` tinyint(1) unsigned DEFAULT '1',
  `reply_comment` varchar(255) DEFAULT NULL,
  `reply_time` int(11) DEFAULT NULL,
  `inviteType` varchar(20) DEFAULT '',
  `inviteRatio` varchar(255) DEFAULT '',
  `inviteUid` int(11) DEFAULT '0',
  `status` varchar(50) DEFAULT '',
  PRIMARY KEY (`rec_id`),
  KEY `order_id` (`order_id`,`goods_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_order_integral`
--
DROP TABLE IF EXISTS `swd_order_integral`;
CREATE TABLE IF NOT EXISTS `swd_order_integral` (
  `order_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `frozen_integral` decimal(10,2) DEFAULT '0',
  PRIMARY KEY (`order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `swd_order_log`
--
DROP TABLE IF EXISTS `swd_order_log`;
CREATE TABLE IF NOT EXISTS `swd_order_log` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL DEFAULT '0',
  `operator` varchar(60) DEFAULT '',
  `order_status` varchar(60) DEFAULT '',
  `changed_status` varchar(60) DEFAULT '',
  `remark` varchar(255) DEFAULT NULL,
  `log_time` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `order_id` (`order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_partner`
--
DROP TABLE IF EXISTS `swd_partner`;
CREATE TABLE IF NOT EXISTS `swd_partner` (
  `partner_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned DEFAULT '0',
  `title` varchar(100) DEFAULT '',
  `link` varchar(255) DEFAULT '',
  `logo` varchar(255) DEFAULT NULL,
  `sort_order` tinyint(3) unsigned DEFAULT '255',
  `if_show` tinyint(1) unsigned DEFAULT NULL,
  PRIMARY KEY (`partner_id`),
  KEY `store_id` (`store_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_plugin`
--
DROP TABLE IF EXISTS `swd_plugin`;
CREATE TABLE IF NOT EXISTS `swd_plugin` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `instance` varchar(20) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `subname` varchar(50) NOT NULL,
  `desc` varchar(255) NOT NULL,
  `config` text NOT NULL,
  `enabled` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_promotool_item`
--
DROP TABLE IF EXISTS `swd_promotool_item`;
CREATE TABLE IF NOT EXISTS `swd_promotool_item` (
  `piid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(10) NOT NULL,
  `appid` varchar(20) NOT NULL,
  `store_id` int(10) DEFAULT '0',
  `config` text DEFAULT '',
  `status` int(1) DEFAULT '1',
  `add_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`piid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_promotool_setting`
--
DROP TABLE IF EXISTS `swd_promotool_setting`;
CREATE TABLE IF NOT EXISTS `swd_promotool_setting` (
  `psid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `appid` varchar(20) NOT NULL,
  `store_id` int(10) DEFAULT '0',
  `rules` text DEFAULT '',
  `status` tinyint(1) DEFAULT '0',
  `add_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`psid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_recommend`
--
DROP TABLE IF EXISTS `swd_recommend`;
CREATE TABLE IF NOT EXISTS `swd_recommend` (
  `recom_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `recom_name` varchar(100) NOT NULL DEFAULT '',
  `store_id` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`recom_id`),
  KEY `store_id` (`store_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_recommend_goods`
--
DROP TABLE IF EXISTS `swd_recommend_goods`;
CREATE TABLE IF NOT EXISTS `swd_recommend_goods` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(10) unsigned NOT NULL DEFAULT '0',
  `recom_id` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `goods_id` (`goods_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_refund`
--
DROP TABLE IF EXISTS `swd_refund`;
CREATE TABLE IF NOT EXISTS `swd_refund` (
  `refund_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tradeNo` varchar(30) NOT NULL,
  `refund_sn` varchar(30) NOT NULL,
  `title` varchar(255) DEFAULT '',
  `refund_reason` varchar(50) DEFAULT '',
  `refund_desc` varchar(255) DEFAULT '',
  `total_fee` decimal(10,2) DEFAULT '0',
  `goods_fee` decimal(10,2) DEFAULT '0',
  `shipping_fee` decimal(10,2) DEFAULT '0',
  `refund_total_fee` decimal(10,2) DEFAULT '0',
  `refund_goods_fee` decimal(10,2) DEFAULT '0',
  `refund_shipping_fee` decimal(10,2) DEFAULT '0',
  `buyer_id` int(10) NOT NULL,
  `seller_id` int(10) NOT NULL,
  `status` varchar(100) DEFAULT '',
  `shipped` int(11) DEFAULT '0',
  `intervene` int(1) DEFAULT '0',
  `created` int(11) DEFAULT NULL,
  `finished` int(11) DEFAULT NULL,
  PRIMARY KEY (`refund_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_refund_message`
--
DROP TABLE IF EXISTS `swd_refund_message`;
CREATE TABLE IF NOT EXISTS `swd_refund_message` (
  `rm_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) NOT NULL,
  `owner_role` varchar(10) DEFAULT '',
  `refund_id` int(11) NOT NULL,
  `content` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created` int(11) DEFAULT NULL,
  PRIMARY KEY (`rm_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_region`
--
DROP TABLE IF EXISTS `swd_region`;
CREATE TABLE IF NOT EXISTS `swd_region` (
  `region_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `region_name` varchar(100) NOT NULL DEFAULT '',
  `parent_id` int(10) unsigned DEFAULT '0',
  `sort_order` tinyint(3) unsigned DEFAULT '255',
  `if_show` int(1) DEFAULT '1',
  PRIMARY KEY (`region_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_report`
--
DROP TABLE IF EXISTS `swd_report`;
CREATE TABLE IF NOT EXISTS `swd_report` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '举报人ID',
  `store_id` int(10) DEFAULT NULL COMMENT '被举报店铺ID',
  `goods_id` int(10) DEFAULT NULL COMMENT '被举报商品ID',
  `content` varchar(255) DEFAULT NULL COMMENT '举报内容',
  `images` text DEFAULT NULL,
  `add_time` int(10) DEFAULT NULL COMMENT '添加时间',
  `status` int(3) DEFAULT NULL COMMENT '状态',
  `examine` varchar(20) DEFAULT NULL COMMENT '审核员',
  `verify` varchar(255) DEFAULT NULL COMMENT '审核说明',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_scategory`
--
DROP TABLE IF EXISTS `swd_scategory`;
CREATE TABLE IF NOT EXISTS `swd_scategory` (
  `cate_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cate_name` varchar(100) NOT NULL DEFAULT '',
  `parent_id` int(10) unsigned DEFAULT '0',
  `sort_order` tinyint(3) unsigned DEFAULT '255',
  `if_show` int(1) DEFAULT '1',
  PRIMARY KEY (`cate_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_sgrade`
--
DROP TABLE IF EXISTS `swd_sgrade`;
CREATE TABLE IF NOT EXISTS `swd_sgrade` (
  `grade_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `grade_name` varchar(60) NOT NULL DEFAULT '',
  `goods_limit` int(10) unsigned DEFAULT '0',
  `space_limit` int(10) unsigned DEFAULT '0',
  `charge` varchar(100) DEFAULT '',
  `need_confirm` tinyint(3) unsigned DEFAULT '0',
  `description` varchar(255) DEFAULT '',
  `skins` varchar(255) DEFAULT '',
  `wap_skins` varchar(255) DEFAULT '',
  `sort_order` tinyint(4) unsigned DEFAULT '255',
  PRIMARY KEY (`grade_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_sgrade_integral`
--
DROP TABLE IF EXISTS `swd_sgrade_integral`;
CREATE TABLE IF NOT EXISTS `swd_sgrade_integral` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sgrade_id` int(10) NOT NULL DEFAULT '0',
  `buy_integral` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `sgrade_id` (`sgrade_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_store`
--
DROP TABLE IF EXISTS `swd_store`;
CREATE TABLE IF NOT EXISTS `swd_store` (
  `store_id` int(10) unsigned NOT NULL DEFAULT '0',
  `store_name` varchar(100) NOT NULL DEFAULT '',
  `owner_name` varchar(60) DEFAULT '',
  `identity_card` varchar(60) DEFAULT '',
  `region_id` int(10) unsigned DEFAULT NULL,
  `region_name` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT '',
  `zipcode` varchar(20) DEFAULT '',
  `tel` varchar(60) DEFAULT '',
  `sgrade` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `stype` VARCHAR(20) NOT NULL DEFAULT 'personal',
  `joinway` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `apply_remark` varchar(255) DEFAULT '',
  `credit_value` decimal(10,2) unsigned DEFAULT '0.00',
  `praise_rate` decimal(10,2) unsigned DEFAULT '0.00',
  `domain` varchar(255) DEFAULT NULL,
  `state` tinyint(3) unsigned DEFAULT '0',
  `close_reason` varchar(255) DEFAULT '',
  `add_time` int(10) unsigned DEFAULT NULL,
  `end_time` int(10) unsigned DEFAULT '0',
  `certification` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned DEFAULT '255',
  `recommended` tinyint(4) DEFAULT '0',
  `theme` varchar(60) DEFAULT '',
  `store_banner` varchar(255) DEFAULT NULL,
  `store_logo` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `identity_front` varchar(255) DEFAULT '',
  `identity_back` varchar(255) DEFAULT '',
  `business_license` varchar(255) DEFAULT '',
  `im_qq` varchar(60) DEFAULT '',
  `swiper` text DEFAULT '',
  `longitude` varchar(20) DEFAULT '',
  `latitude` varchar(20) DEFAULT '',
  `zoom` int(10) DEFAULT '15',
  PRIMARY KEY (`store_id`),
  KEY `store_name` (`store_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `swd_teambuy`
--
DROP TABLE IF EXISTS `swd_teambuy`;
CREATE TABLE IF NOT EXISTS `swd_teambuy` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(10) NOT NULL,
  `title` varchar(50) NOT NULL DEFAULT '',
  `status` tinyint(3) NOT NULL DEFAULT '1',
  `store_id` int(10) DEFAULT '0',
  `people` int(10) unsigned NOT NULL DEFAULT '2',
  `specs` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_teambuy_log`
--
DROP TABLE IF EXISTS `swd_teambuy_log`;
CREATE TABLE IF NOT EXISTS `swd_teambuy_log` (
  `logid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tbid` int(10) unsigned DEFAULT '0',
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `order_id` int(10) unsigned NOT NULL DEFAULT '0',
  `goods_id` int(10) unsigned NOT NULL DEFAULT '0',
  `teamid` varchar(32) NOT NULL DEFAULT '',
  `leader` tinyint(3) unsigned DEFAULT '0',
  `people` int(10) unsigned NOT NULL DEFAULT '2',
  `status` tinyint(3) unsigned DEFAULT '0',
  `created` int(11) unsigned NOT NULL DEFAULT '0',
  `expired` int(11) unsigned NOT NULL DEFAULT '0',
  `pay_time` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`logid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_uploaded_file`
--
DROP TABLE IF EXISTS `swd_uploaded_file`;
CREATE TABLE IF NOT EXISTS `swd_uploaded_file` (
  `file_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned DEFAULT '0',
  `file_type` varchar(60) DEFAULT '',
  `file_size` int(10) unsigned DEFAULT '0',
  `file_name` varchar(255) DEFAULT '',
  `file_path` varchar(255) NOT NULL DEFAULT '',
  `add_time` int(10) unsigned DEFAULT NULL,
  `belong` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `item_id` int(10) unsigned DEFAULT '0',
  `link_url` varchar(50) DEFAULT '',
  PRIMARY KEY (`file_id`),
  KEY `store_id` (`store_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_user`
--
DROP TABLE IF EXISTS `swd_user`;
CREATE TABLE IF NOT EXISTS `swd_user` (
  `userid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(60) NOT NULL DEFAULT '',
  `nickname` varchar(60) NOT NULL DEFAULT '',
  `email` varchar(60) DEFAULT '',
  `password` varchar(255) DEFAULT '',
  `password_reset_token` varchar(255) DEFAULT '',
  `real_name` varchar(60) DEFAULT NULL,
  `gender` tinyint(3) unsigned DEFAULT '0',
  `birthday` varchar(50) NOT NULL DEFAULT '',
  `phone_tel` varchar(60) NOT NULL DEFAULT '',
  `phone_mob` varchar(20) NOT NULL DEFAULT '',
  `im_qq` varchar(60) NOT NULL DEFAULT '',
  `create_time` int(10) unsigned DEFAULT NULL,
  `update_time` int(10) unsigned DEFAULT NULL,
  `last_login` int(10) unsigned DEFAULT NULL,
  `last_ip` varchar(15) DEFAULT NULL,
  `logins` int(10) unsigned DEFAULT '0',
  `ugrade` tinyint(3) unsigned DEFAULT '1',
  `portrait` varchar(255) DEFAULT NULL,
  `activation` varchar(60) DEFAULT NULL,
  `locked` int(1) DEFAULT '0',
  `imforbid` int(1) DEFAULT '0',
  `auth_key` varchar(255) DEFAULT '',
  PRIMARY KEY (`userid`),
  KEY `username` (`username`),
  KEY `phone_mob` (`phone_mob`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_user_enter`
--
DROP TABLE IF EXISTS `swd_user_enter`;
CREATE TABLE IF NOT EXISTS `swd_user_enter` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `username` varchar(50) DEFAULT NULL,
  `scene` varchar(20) DEFAULT '',
  `ip` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `add_time` int(10) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_user_priv`
--
DROP TABLE IF EXISTS `swd_user_priv`;
CREATE TABLE IF NOT EXISTS `swd_user_priv` (
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `store_id` int(10) unsigned DEFAULT '0',
  `privs` text DEFAULT '',
  PRIMARY KEY (`userid`,`store_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `swd_user_token`
--
DROP TABLE IF EXISTS `swd_user_token`;
CREATE TABLE IF NOT EXISTS `swd_user_token` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `token` varchar(100) NOT NULL DEFAULT '',
  `expire_time` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `swd_webim`
--
DROP TABLE IF EXISTS `swd_webim`;
CREATE TABLE IF NOT EXISTS `swd_webim` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `toid` int(11) NOT NULL,
  `fromid` int(11) NOT NULL,
  `groupid` varchar(255) NOT NULL,
  `store_id` int(10) DEFAULT NULL,
  `store_name` varchar(100) DEFAULT NULL,
  `content` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `unread` int(11) NOT NULL DEFAULT '0',
  `created` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_weixin_menu`
--
DROP TABLE IF EXISTS `swd_weixin_menu`;
CREATE TABLE IF NOT EXISTS `swd_weixin_menu` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `parent_id` int(10) DEFAULT '0',
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `add_time` int(10) DEFAULT NULL,
  `sort_order` tinyint(3) unsigned DEFAULT '255',
  `link` varchar(255) DEFAULT NULL,
  `reply_id` int(10) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_weixin_reply`
--
DROP TABLE IF EXISTS `swd_weixin_reply`;
CREATE TABLE IF NOT EXISTS `swd_weixin_reply` (
  `reply_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `type` tinyint(1) unsigned DEFAULT '0' COMMENT '回复类型0文字1图文',
  `action` varchar(20) DEFAULT NULL COMMENT '回复命令 关注、消息、关键字',
  `title` varchar(255) DEFAULT NULL,
  `link` varchar(50) DEFAULT NULL,
  `image` varchar(100) DEFAULT NULL,
  `rule_name` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `add_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`reply_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `swd_weixin_setting`
--
DROP TABLE IF EXISTS `swd_weixin_setting`;
CREATE TABLE IF NOT EXISTS `swd_weixin_setting` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL DEFAULT '0',
  `code` varchar(30) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `token` varchar(255) DEFAULT '',
  `appid` varchar(255) DEFAULT NULL,
  `appsecret` varchar(255) DEFAULT NULL,
  `if_valid` tinyint(1) unsigned DEFAULT '0',
  `autologin` int(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- 表的结构 `swd_wholesale`
--
DROP TABLE IF EXISTS `swd_wholesale`;
CREATE TABLE IF NOT EXISTS `swd_wholesale` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(10) unsigned NOT NULL DEFAULT '0',
  `store_id` int(10) unsigned DEFAULT '0',
  `price` decimal(10,2) unsigned DEFAULT '0.00',
  `quantity` int(10) unsigned DEFAULT '1',
  `status` int(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
