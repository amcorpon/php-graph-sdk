const db = require('../utils/db');
const claude = require('./claude');
const gemini = require('./gemini');
const gpt = require('./gpt');

const providers = { claude, gemini, gpt };

const CREDIT_ERRORS = [
  'insufficient_quota', 'rate_limit', 'quota', 'billing',
  'exceeded', 'limit', '429', '402', 'credits'
];

function isCreditsError(error) {
  const msg = (error.message || error.toString()).toLowerCase();
  return CREDIT_ERRORS.some(keyword => msg.includes(keyword));
}

async function ask(messages, systemPrompt) {
  const config = await db.getConfig();
  const aiKeys = await db.getAIKeys();

  let sequence;
  if (config.active_ai === 'sequential') {
    try {
      sequence = JSON.parse(config.ai_sequence) || ['claude', 'gemini', 'gpt'];
    } catch {
      sequence = ['claude', 'gemini', 'gpt'];
    }
  } else {
    sequence = [config.active_ai];
  }

  for (const providerName of sequence) {
    const keyData = aiKeys[providerName];
    if (!keyData || !keyData.api_key) continue;
    if (keyData.credits_exhausted) continue;

    const provider = providers[providerName];
    if (!provider) continue;

    try {
      const result = await provider.ask(keyData.api_key, keyData.model, messages, systemPrompt);
      await db.update('UPDATE ai_keys SET last_used = NOW(), credits_exhausted = 0 WHERE provider = ?', [providerName]);
      await db.log('info', 'ai_manager', `Resposta gerada com ${providerName}`, { tokens: result.tokens });
      return { ...result, provider: providerName };
    } catch (error) {
      await db.log('error', 'ai_manager', `Erro no ${providerName}: ${error.message}`);

      if (isCreditsError(error)) {
        await db.update(
          'UPDATE ai_keys SET credits_exhausted = 1, last_error = ? WHERE provider = ?',
          [error.message, providerName]
        );
        continue; // tenta próxima IA
      }
      throw error; // outro tipo de erro
    }
  }

  throw new Error('Nenhuma IA disponível com créditos. Verifique as configurações.');
}

module.exports = { ask };
