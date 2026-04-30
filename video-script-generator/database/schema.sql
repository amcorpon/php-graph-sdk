-- Video Script Generator - Database Schema
-- Run: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS `video_script_gen` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `video_script_gen`;

CREATE TABLE IF NOT EXISTS `vsg_config` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `config_key`   VARCHAR(100) NOT NULL UNIQUE,
  `config_value` TEXT,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `vsg_projects` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `title`            VARCHAR(255) NOT NULL,
  `theme`            TEXT NOT NULL,
  `duration_minutes` INT NOT NULL DEFAULT 5,
  `ai_provider`      ENUM('claude','gemini','both') DEFAULT 'claude',
  `claude_model`     VARCHAR(100) DEFAULT 'claude-opus-4-7',
  `voice_provider`   ENUM('google','elevenlabs') DEFAULT 'google',
  `voice_id`         VARCHAR(200) DEFAULT 'en-US-Neural2-F',
  `language_code`    VARCHAR(20) DEFAULT 'en-US',
  `status`           ENUM('draft','generating','script_ready','searching_media','media_ready','queued','processing','completed','error') DEFAULT 'draft',
  `output_path`      VARCHAR(500),
  `error_message`    TEXT,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `vsg_scripts` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `project_id`  INT NOT NULL,
  `raw_json`    LONGTEXT,
  `ai_provider` VARCHAR(50),
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`) REFERENCES `vsg_projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `vsg_sections` (
  `id`                   INT AUTO_INCREMENT PRIMARY KEY,
  `project_id`           INT NOT NULL,
  `sequence_number`      INT NOT NULL,
  `section_type`         ENUM('narration','broll_image','broll_video','text_overlay') NOT NULL,
  `narration_text`       TEXT,
  `overlay_text`         TEXT,
  `overlay_bg_color`     VARCHAR(20) DEFAULT '#1a1a2e',
  `overlay_text_color`   VARCHAR(20) DEFAULT '#ffffff',
  `search_terms`         TEXT,
  `description`          TEXT,
  `duration_seconds`     INT DEFAULT 10,
  `voice_notes`          VARCHAR(255),
  `media_options`        LONGTEXT COMMENT 'JSON array of media candidates from Pexels',
  `selected_option`      INT DEFAULT 0,
  `media_url`            TEXT COMMENT 'Final selected media URL',
  `media_thumb`          TEXT,
  `media_source`         VARCHAR(100),
  `media_pexels_id`      VARCHAR(50),
  `cut_start`            VARCHAR(20),
  `cut_end`              VARCHAR(20),
  `file_name`            VARCHAR(255),
  `file_path`            VARCHAR(500),
  `audio_file`           VARCHAR(500),
  `status`               ENUM('pending','searching','found','confirmed','downloading','downloaded','processing','done','error') DEFAULT 'pending',
  `error_message`        TEXT,
  `created_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`) REFERENCES `vsg_projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Default config values
INSERT IGNORE INTO `vsg_config` (`config_key`, `config_value`) VALUES
  ('claude_api_key',       ''),
  ('gemini_api_key',       ''),
  ('pexels_api_key',       ''),
  ('youtube_api_key',      ''),
  ('elevenlabs_api_key',   ''),
  ('google_tts_api_key',   ''),
  ('db_host',              'localhost'),
  ('db_user',              'root'),
  ('db_pass',              ''),
  ('db_name',              'video_script_gen'),
  ('ffmpeg_bin',           'ffmpeg'),
  ('worker_api_token',     ''),
  ('server_public_url',    '');

-- Migration: add 'queued' to projects status ENUM if upgrading from older version
-- ALTER TABLE vsg_projects MODIFY status ENUM('draft','generating','script_ready','searching_media','media_ready','queued','processing','completed','error') DEFAULT 'draft';
