-- ChatDesk — โครงสร้างฐานข้อมูล (MySQL/MariaDB, utf8mb4)
-- import ผ่าน phpMyAdmin / CLI:  mysql -u USER -p chatdesk < schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- ตารางห้องแชท (1 รายการ = 1 ผู้ใช้ต่อช่องทาง เช่น LINE / Facebook Messenger)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cd_conversations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `channel` varchar(20) NOT NULL DEFAULT 'line' COMMENT 'line = LINE, fb = Facebook Messenger',
  `external_user_id` varchar(64) NOT NULL COMMENT 'LINE userId (Uxxxx...) หรือ Messenger PSID',
  `display_name` varchar(150) DEFAULT NULL,
  `picture_url` varchar(255) DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `bot_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = คนดูแลเอง บอทหยุดตอบ',
  `unread_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_message_at` datetime DEFAULT NULL,
  `last_message_text` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_channel_user` (`channel`,`external_user_id`),
  KEY `idx_last_message` (`last_message_at`),
  KEY `idx_status` (`status`,`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ตารางข้อความ
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cd_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int(10) unsigned NOT NULL,
  `sender` enum('customer','bot','agent','system') NOT NULL DEFAULT 'customer',
  `content` text NOT NULL,
  `message_type` varchar(20) NOT NULL DEFAULT 'text' COMMENT 'text / image / sticker / ...',
  `media_url` varchar(500) DEFAULT NULL,
  `media_preview_url` varchar(500) DEFAULT NULL,
  `sticker_package` varchar(50) DEFAULT NULL,
  `sticker_id` varchar(50) DEFAULT NULL,
  `external_message_id` varchar(64) DEFAULT NULL COMMENT 'id ของ LINE — กันข้อความซ้ำตอน LINE ส่งซ้ำ',
  `status` enum('ok','error') NOT NULL DEFAULT 'ok',
  `delivered_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `raw` mediumtext DEFAULT NULL COMMENT 'ข้อมูลดิบที่ n8n ส่งมา (ไว้ debug)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_external_msg` (`external_message_id`),
  KEY `idx_conversation` (`conversation_id`,`id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_cd_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `cd_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;