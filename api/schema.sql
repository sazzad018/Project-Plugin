
-- SMS Balance Table
CREATE TABLE IF NOT EXISTS `sms_balance_store` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `balance` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert initial row (ID 1 with 0 balance)
INSERT IGNORE INTO `sms_balance_store` (`id`, `balance`) VALUES (1, 0);

-- Feature Flags (Plugin Control)
CREATE TABLE IF NOT EXISTS `feature_flags` (
  `feature_key` varchar(50) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`feature_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Default Features
INSERT IGNORE INTO `feature_flags` (`feature_key`, `is_enabled`) VALUES 
('live_capture', 1),
('fraud_guard', 1),
('sms_automation', 1),
('pixel_capi', 0);

-- Incomplete Orders (Leads)
CREATE TABLE IF NOT EXISTS `incomplete_orders` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(100) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  `cart_items` text,
  `cart_total` decimal(10,2) DEFAULT 0.00,
  `status` enum('new','contacted','recovered','lost') DEFAULT 'new',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fraud Check Cache
CREATE TABLE IF NOT EXISTS `fraud_check_cache` (
  `phone` varchar(20) NOT NULL,
  `data` json DEFAULT NULL,
  `success_rate` decimal(5,2) DEFAULT 0.00,
  `total_orders` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
