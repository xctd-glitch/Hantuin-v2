-- =============================================================
-- Hantuin-v2 Decision Logic — Database Schema
-- MySQL 5.7+ / MariaDB 10.3+
-- Shared hosting / cPanel-safe import
-- =============================================================

-- -------------------------------------------------------------
-- settings
-- Single-row configuration table (id is always 1).
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`                  TINYINT UNSIGNED  NOT NULL,
  `redirect_url`        VARCHAR(2048)     NOT NULL DEFAULT '',
  `system_on`           TINYINT(1)        NOT NULL DEFAULT 0,
  `country_filter_mode` ENUM('all','whitelist','blacklist')
                                          NOT NULL DEFAULT 'all',
  `country_filter_list` TEXT              NOT NULL,
  `postback_url`        VARCHAR(2048)     NOT NULL DEFAULT '',
  `postback_token`      VARCHAR(64)       NOT NULL DEFAULT '',
  `updated_at`          INT UNSIGNED      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @settings_has_postback_url := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'settings'
    AND COLUMN_NAME = 'postback_url'
);
SET @settings_add_postback_url_sql := IF(
  @settings_has_postback_url = 0,
  'ALTER TABLE `settings` ADD COLUMN `postback_url` VARCHAR(2048) NOT NULL DEFAULT '''' AFTER `country_filter_list`',
  'SELECT 1'
);
PREPARE `srp_settings_add_postback_url` FROM @settings_add_postback_url_sql;
EXECUTE `srp_settings_add_postback_url`;
DEALLOCATE PREPARE `srp_settings_add_postback_url`;

SET @settings_has_postback_token := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'settings'
    AND COLUMN_NAME = 'postback_token'
);
SET @settings_add_postback_token_sql := IF(
  @settings_has_postback_token = 0,
  'ALTER TABLE `settings` ADD COLUMN `postback_token` VARCHAR(64) NOT NULL DEFAULT '''' AFTER `postback_url`',
  'SELECT 1'
);
PREPARE `srp_settings_add_postback_token` FROM @settings_add_postback_token_sql;
EXECUTE `srp_settings_add_postback_token`;
DEALLOCATE PREPARE `srp_settings_add_postback_token`;

INSERT INTO `settings`
  (`id`, `redirect_url`, `system_on`, `country_filter_mode`, `country_filter_list`, `postback_url`, `postback_token`, `updated_at`)
VALUES
  (1, '', 0, 'all', '', '', '', UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- -------------------------------------------------------------
-- logs
-- Append-only traffic log.
--
-- Indexes:
--   idx_logs_ts_dec  (ts, decision) — covering index for analytics/weekly stats.
--                    Both getWeeklyStats() and getDailyStats() filter on ts and
--                    aggregate on decision; this index satisfies both without a
--                    table-row lookup.
--   idx_logs_cc_ts   (country_code, ts) — country + time range queries.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logs` (
  `id`           BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `ts`           INT UNSIGNED      NOT NULL,
  `ip`           VARCHAR(45)       NOT NULL,
  `ua`           VARCHAR(500)      NOT NULL,
  `click_id`     VARCHAR(100)      NULL DEFAULT NULL,
  `country_code` VARCHAR(10)       NULL DEFAULT NULL,
  `user_lp`      VARCHAR(100)      NULL DEFAULT NULL,
  `decision`     ENUM('A','B')     NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_logs_ts_dec` (`ts`, `decision`),
  INDEX `idx_logs_cc_ts`  (`country_code`, `ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @logs_has_idx_logs_ts_dec := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'logs'
    AND INDEX_NAME = 'idx_logs_ts_dec'
);
SET @logs_add_idx_logs_ts_dec_sql := IF(
  @logs_has_idx_logs_ts_dec = 0,
  'ALTER TABLE `logs` ADD INDEX `idx_logs_ts_dec` (`ts`, `decision`)',
  'SELECT 1'
);
PREPARE `srp_logs_add_idx_logs_ts_dec` FROM @logs_add_idx_logs_ts_dec_sql;
EXECUTE `srp_logs_add_idx_logs_ts_dec`;
DEALLOCATE PREPARE `srp_logs_add_idx_logs_ts_dec`;

SET @logs_has_idx_logs_ts := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'logs'
    AND INDEX_NAME = 'idx_logs_ts'
);
SET @logs_drop_idx_logs_ts_sql := IF(
  @logs_has_idx_logs_ts > 0,
  'ALTER TABLE `logs` DROP INDEX `idx_logs_ts`',
  'SELECT 1'
);
PREPARE `srp_logs_drop_idx_logs_ts` FROM @logs_drop_idx_logs_ts_sql;
EXECUTE `srp_logs_drop_idx_logs_ts`;
DEALLOCATE PREPARE `srp_logs_drop_idx_logs_ts`;

-- -------------------------------------------------------------
-- conversions
-- Postback receiver log — one row per lead/conversion event.
-- Linked to logs via click_id.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conversions` (
  `id`        BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `ts`        INT UNSIGNED     NOT NULL,
  `click_id`  VARCHAR(100)     NOT NULL DEFAULT '',
  `payout`    DECIMAL(10,4)    NOT NULL DEFAULT 0.0000,
  `currency`  VARCHAR(10)      NOT NULL DEFAULT 'USD',
  `status`    VARCHAR(50)      NOT NULL DEFAULT 'approved',
  `country`   VARCHAR(10)      NULL DEFAULT NULL,
  `ip`        VARCHAR(45)      NOT NULL DEFAULT '',
  `raw`       TEXT             NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_conv_ts`       (`ts`),
  INDEX `idx_conv_click_id` (`click_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @conv_has_country := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'conversions'
    AND COLUMN_NAME = 'country'
);
SET @conv_add_country_sql := IF(
  @conv_has_country = 0,
  'ALTER TABLE `conversions` ADD COLUMN `country` VARCHAR(10) NULL DEFAULT NULL AFTER `status`',
  'SELECT 1'
);
PREPARE `srp_conv_add_country` FROM @conv_add_country_sql;
EXECUTE `srp_conv_add_country`;
DEALLOCATE PREPARE `srp_conv_add_country`;
