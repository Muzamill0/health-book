-- Webhook log: every request Paddle sends to webhook.php is recorded here (shown in Admin → Webhook Log).
-- Safe to run on a live database and more than once: it only creates the table if it is missing.
CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` VARCHAR(100) DEFAULT NULL,
  `event_type` VARCHAR(100) NOT NULL DEFAULT '',
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `order_no` VARCHAR(32) DEFAULT NULL,
  `signature_ok` TINYINT(1) NOT NULL DEFAULT 0,
  `result` VARCHAR(255) NOT NULL DEFAULT '',
  `payload` MEDIUMTEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`created_at`),
  INDEX (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
