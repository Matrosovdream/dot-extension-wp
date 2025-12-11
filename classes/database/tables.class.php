<?php
class Dotfiler_migration {



}



/*
DROP TABLE IF EXISTS `wp_frm_payments_authnet`;
CREATE TABLE `wp_frm_payments_authnet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `amount` float NOT NULL,
  `payment_id` int(11) NOT NULL,
  `invoice_id` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `form_id` int(11) NOT NULL,
  `authnet_login_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `authnet_transaction_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


DROP TABLE IF EXISTS `wp_frm_refunds_authnet`;
CREATE TABLE `wp_frm_refunds_authnet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sum` float NOT NULL,
  `payment_id` int(100) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



*/