-- Partner users: people who log in (email or phone + password) to see the stats of their referral links.
-- Safe to run on a live database and more than once: only adds what is missing, never deletes data.

CREATE TABLE IF NOT EXISTS `partners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `failed_logins` INT NOT NULL DEFAULT 0,
  `locked_until` DATETIME DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `partners_email` (`email`),
  UNIQUE KEY `partners_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each referral link can belong to one partner
SET @db = DATABASE();
SET @s = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'referrers' AND column_name = 'partner_id') = 0,
  'ALTER TABLE `referrers` ADD COLUMN `partner_id` INT DEFAULT NULL AFTER `notes`, ADD INDEX `referrers_partner` (`partner_id`)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
