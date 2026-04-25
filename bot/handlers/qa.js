const db = require('../utils/db');
const ai = require('../ai/manager');
const { buildSystemPrompt } = require('../utils/helpers');

async function handle(sock, jid, client, message) {
  const config = await db.getConfig();
  const knowledgeBase = await db.getKnowledgeBase();

  // Verificar limite de respostas
  if (config.response_limit > 0) {
    const count = await db.queryOne(
      "SELECT COUNT(*) as total FROM conversations WHERE client_id = ? AND role = 'assistant'",
      [client.id]
    );
    if (count && count.total >= config.response_limit) {
      await sock.sendMessage(jid, {
        text: `Desculpe ${client.name || ''}, atingimos o limite de atendimentos. Por favor, entre em contato pelo telefone.`.trim()
      });
      return;
    }
  }

  const history = await db.getConversationHistory(client.id, 8);
  history.reverse();
  history.push({ role: 'user', message });

  const systemPrompt = buildSystemPrompt(config, knowledgeBase, client.name);

  try {
    const result = await ai.ask(history, systemPrompt);
    await sock.sendMessage(jid, { text: result.text });
    await db.saveMessage(client.id, 'user', message);
    await db.saveMessage(client.id, 'assistant', result.text, result.provider);
  } catch (error) {
    await db.log('error', 'qa_handler', error.message);
    await sock.sendMessage(jid, {
      text: `Desculpe, estou com dificuldades técnicas no momento. Tente novamente em instantes.`
    });
  }
}

module.exports = { handle };
