-- ============================================================
-- WhatsApp AI Bot - Schema completo
-- Versão 1.0
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS `whatsapp_bot` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `whatsapp_bot`;

-- ============================================================
-- CONFIGURAÇÃO DO BOT
-- ============================================================
CREATE TABLE IF NOT EXISTS `bot_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bot_name` varchar(100) NOT NULL DEFAULT 'Assistente',
  `owner_number` varchar(25) DEFAULT NULL COMMENT 'Ex: 5511999999999',
  `bot_number` varchar(25) DEFAULT NULL COMMENT 'Número onde o bot está conectado',
  `bot_mode` set('qa','orders','appointments') NOT NULL DEFAULT 'qa' COMMENT 'Modos ativos',
  `response_length` enum('short','medium','long') NOT NULL DEFAULT 'medium',
  `use_emojis` tinyint(1) NOT NULL DEFAULT 1,
  `persona` enum('professional','friendly','fun','formal','empathetic') NOT NULL DEFAULT 'friendly',
  `response_limit` int(11) NOT NULL DEFAULT 0 COMMENT '0 = sem limite por conversa',
  `notify_owner` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Notificar dono em pedidos/agendamentos',
  `owner_notification_template` text DEFAULT NULL COMMENT 'Template da msg enviada ao dono',
  `active_ai` enum('claude','gemini','gpt','sequential') NOT NULL DEFAULT 'claude',
  `ai_sequence` json DEFAULT NULL COMMENT 'Ex: ["claude","gemini","gpt"]',
  `business_hours_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `out_of_hours_message` text DEFAULT NULL,
  `welcome_message` text DEFAULT NULL COMMENT 'Mensagem de boas vindas (primeira vez)',
  `return_message` text DEFAULT NULL COMMENT 'Mensagem quando cliente retorna',
  `max_session_idle` int(11) NOT NULL DEFAULT 60 COMMENT 'Minutos sem interação para resetar sessão',
  `ask_name_on_first_contact` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `bot_config` (`bot_name`, `bot_mode`, `response_length`, `use_emojis`, `persona`, `response_limit`,
  `notify_owner`, `active_ai`, `ai_sequence`, `welcome_message`, `return_message`, `owner_notification_template`)
VALUES (
  'Bia',
  'qa',
  'medium',
  1,
  'friendly',
  0,
  1,
  'claude',
  '["claude","gemini","gpt"]',
  'Olá! Seja bem-vindo(a)! Sou a Bia e estou aqui para te ajudar. Pode me contar seu nome? 😊',
  'Olá {nome}! Que bom te ver de volta! Como posso te ajudar hoje?',
  '🔔 *Novo pedido/agendamento!*\n\nCliente: {nome}\nTelefone: {telefone}\n\nDetalhes:\n{detalhes}'
);

-- ============================================================
-- CHAVES DE API DAS IAs
-- ============================================================
CREATE TABLE IF NOT EXISTS `ai_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider` enum('claude','gemini','gpt') NOT NULL,
  `api_key` varchar(500) NOT NULL,
  `model` varchar(100) DEFAULT NULL COMMENT 'Modelo específico a usar',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `credits_exhausted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Créditos esgotados - usar próxima',
  `last_error` text DEFAULT NULL,
  `last_used` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- BASE DE CONHECIMENTO
-- ============================================================
CREATE TABLE IF NOT EXISTS `knowledge_base` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_type` enum('text','url') NOT NULL DEFAULT 'text',
  `content` longtext DEFAULT NULL COMMENT 'Conteúdo manual (quando source_type=text)',
  `url` varchar(1000) DEFAULT NULL COMMENT 'URL para extrair conteúdo',
  `url_content` longtext DEFAULT NULL COMMENT 'Conteúdo extraído da URL',
  `last_url_fetch` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `knowledge_base` (`source_type`, `content`) VALUES
('text', 'Este é o assistente virtual do seu negócio. Responda dúvidas dos clientes de forma educada e prestativa.');

-- ============================================================
-- CLIENTES (quem envia mensagens no WhatsApp)
-- ============================================================
CREATE TABLE IF NOT EXISTS `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(25) NOT NULL,
  `name` varchar(100) DEFAULT NULL COMMENT 'Nome salvo pelo bot',
  `nickname` varchar(100) DEFAULT NULL COMMENT 'Apelido preferido',
  `tags` varchar(255) DEFAULT NULL COMMENT 'Tags separadas por vírgula',
  `blocked` tinyint(1) NOT NULL DEFAULT 0,
  `total_messages` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL COMMENT 'Notas internas do admin',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_interaction` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone_number` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- CONVERSAS (histórico)
-- ============================================================
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `role` enum('user','assistant') NOT NULL,
  `message` text NOT NULL,
  `ai_used` varchar(20) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_created` (`client_id`, `created_at`),
  CONSTRAINT `fk_conv_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ESTADO DA CONVERSA (fluxos multi-etapa)
-- ============================================================
CREATE TABLE IF NOT EXISTS `conversation_state` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `state` varchar(50) NOT NULL DEFAULT 'idle',
  `context` json DEFAULT NULL COMMENT 'Dados do fluxo em andamento',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`),
  CONSTRAINT `fk_state_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PRODUTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `stock` int(11) DEFAULT NULL COMMENT 'NULL = sem controle de estoque',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- FRETES (por bairro)
-- ============================================================
CREATE TABLE IF NOT EXISTS `freight` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `neighborhood` varchar(200) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_time` varchar(50) DEFAULT NULL COMMENT 'Ex: 30-45 min',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PEDIDOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `status` enum('pending','confirmed','preparing','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'confirmed',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `freight_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `neighborhood` varchar(200) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `owner_notified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_order_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SERVIÇOS (para agendamentos)
-- ============================================================
CREATE TABLE IF NOT EXISTS `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 60,
  `price` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- HORÁRIOS DE FUNCIONAMENTO
-- ============================================================
CREATE TABLE IF NOT EXISTS `business_hours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Dom, 1=Seg...6=Sab',
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `lunch_start` time DEFAULT NULL,
  `lunch_end` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `day_of_week` (`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `business_hours` (`day_of_week`, `open_time`, `close_time`, `is_open`) VALUES
(0, '09:00:00', '17:00:00', 0),
(1, '08:00:00', '18:00:00', 1),
(2, '08:00:00', '18:00:00', 1),
(3, '08:00:00', '18:00:00', 1),
(4, '08:00:00', '18:00:00', 1),
(5, '08:00:00', '18:00:00', 1),
(6, '09:00:00', '13:00:00', 1);

-- ============================================================
-- AGENDAMENTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `service_name` varchar(200) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 60,
  `status` enum('confirmed','cancelled','completed','no_show') NOT NULL DEFAULT 'confirmed',
  `notes` text DEFAULT NULL,
  `owner_notified` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_sent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`appointment_date`),
  KEY `idx_client` (`client_id`),
  CONSTRAINT `fk_appt_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- STATUS DO BOT (conexão WhatsApp)
-- ============================================================
CREATE TABLE IF NOT EXISTS `bot_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_connected` tinyint(1) NOT NULL DEFAULT 0,
  `pairing_code` varchar(20) DEFAULT NULL,
  `phone_number` varchar(25) DEFAULT NULL,
  `status_message` varchar(255) DEFAULT NULL,
  `last_ping` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `bot_status` (`is_connected`, `status_message`) VALUES (0, 'Desconectado');

-- ============================================================
-- SESSÃO DO BOT (dados de autenticação Baileys)
-- ============================================================
CREATE TABLE IF NOT EXISTS `bot_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_key` varchar(100) NOT NULL,
  `session_value` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_key` (`session_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- USUÁRIOS ADMIN
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('super_admin','admin') NOT NULL DEFAULT 'admin',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- USUÁRIOS DO PAINEL CLIENTE
-- ============================================================
CREATE TABLE IF NOT EXISTS `client_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `business_name` varchar(200) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- RESPOSTAS RÁPIDAS (atalhos configuráveis)
-- ============================================================
CREATE TABLE IF NOT EXISTS `quick_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trigger_phrase` varchar(200) NOT NULL COMMENT 'Palavra/frase que aciona',
  `response` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `priority` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- LISTA DE BLOQUEADOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `blocked_numbers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(25) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone_number` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- LOGS DO SISTEMA
-- ============================================================
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` enum('info','warning','error','debug') NOT NULL DEFAULT 'info',
  `source` varchar(50) DEFAULT NULL,
  `message` text NOT NULL,
  `context` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_level_created` (`level`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ANALYTICS DIÁRIO
-- ============================================================
CREATE TABLE IF NOT EXISTS `analytics_daily` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `total_messages` int(11) NOT NULL DEFAULT 0,
  `unique_users` int(11) NOT NULL DEFAULT 0,
  `total_orders` int(11) NOT NULL DEFAULT 0,
  `orders_revenue` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_appointments` int(11) NOT NULL DEFAULT 0,
  `ai_tokens_used` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;
