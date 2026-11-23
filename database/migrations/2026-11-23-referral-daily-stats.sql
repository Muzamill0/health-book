-- Daily referral clicks: one row per referral link per day, so partners and admins can see click history.
-- Orders per day come from orders.paid_at, so no extra table is needed for them.
-- Safe to run on a live database and more than once: it only creates the table if it is missing.
-- Clicks made before this migration only exist in referrers.clicks (the all-time total) and have no date.
CREATE TABLE IF NOT EXISTS `referral_clicks_daily` (
  `referrer_id` INT NOT NULL,
  `day` DATE NOT NULL,
  `clicks` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`referrer_id`, `day`),
  INDEX `referral_clicks_daily_day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lets the daily orders query filter paid orders by date quickly
SET @db = DATABASE();
SET @s = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @db AND table_name = 'orders' AND index_name = 'orders_referrer_paid') = 0,
  'ALTER TABLE `orders` ADD INDEX `orders_referrer_paid` (`referrer_id`, `paid_at`)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
