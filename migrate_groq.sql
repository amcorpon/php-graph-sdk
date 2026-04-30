-- ============================================================
-- Migração: adiciona suporte a Groq (transcrição de áudio)
-- Execute uma vez em bancos já instalados
-- ============================================================
USE `whatsapp_bot`;

ALTER TABLE `ai_keys`
  MODIFY COLUMN `provider` enum('claude','gemini','gpt','groq') NOT NULL;
