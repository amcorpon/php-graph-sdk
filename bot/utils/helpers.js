function formatCurrency(value) {
  return parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr + 'T12:00:00');
  return d.toLocaleDateString('pt-BR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

function formatTime(timeStr) {
  if (!timeStr) return '';
  return timeStr.slice(0, 5);
}

function normalizePhone(phone) {
  return phone.replace(/\D/g, '').replace('@s.whatsapp.net', '').replace('@c.us', '');
}

function buildSystemPrompt(config, knowledgeBase, clientName) {
  const personaMap = {
    professional: 'Você é um assistente profissional, formal e eficiente.',
    friendly: 'Você é um assistente amigável, caloroso e prestativo.',
    fun: 'Você é um assistente divertido, animado e com bom humor.',
    formal: 'Você é um assistente extremamente formal e respeitoso.',
    empathetic: 'Você é um assistente empático, compreensivo e gentil.'
  };

  const lengthMap = {
    short: 'Responda de forma CURTA e direta, máximo 2-3 frases.',
    medium: 'Responda de forma moderada, nem muito curta nem muito longa.',
    long: 'Responda de forma detalhada e completa quando necessário.'
  };

  const emojiInstruction = config.use_emojis
    ? 'Use emojis de forma natural para deixar a conversa mais agradável.'
    : 'NÃO use emojis nas respostas.';

  const nameInstruction = clientName
    ? `O nome do cliente é "${clientName}". Trate-o pelo nome quando apropriado.`
    : 'Ainda não sabemos o nome do cliente.';

  let prompt = `Seu nome é ${config.bot_name}. NUNCA diga que é um bot, robô ou IA. Sempre responda como se fosse uma pessoa real chamada ${config.bot_name}.

${personaMap[config.persona] || personaMap.friendly}
${lengthMap[config.response_length] || lengthMap.medium}
${emojiInstruction}
${nameInstruction}

BASE DE CONHECIMENTO (use para responder perguntas):
---
${knowledgeBase || 'Não há base de conhecimento configurada ainda.'}
---

Regras importantes:
- Nunca revele que é uma IA, bot, ou sistema automatizado
- Se não souber a resposta, diga que vai verificar e peça para aguardar
- Seja sempre educado e prestativo
- Responda APENAS em português do Brasil
- Nunca invente informações que não estão na base de conhecimento`;

  return prompt;
}

function buildOrderSummary(items, subtotal, freight, total, neighborhood, address, paymentMethod) {
  let text = '📋 *Resumo do pedido:*\n\n';
  items.forEach((item, i) => {
    text += `${i + 1}. ${item.name} x${item.quantity} — ${formatCurrency(item.price * item.quantity)}\n`;
  });
  text += `\n💰 Subtotal: ${formatCurrency(subtotal)}`;
  if (freight > 0) text += `\n🚗 Frete (${neighborhood}): ${formatCurrency(freight)}`;
  text += `\n✅ *Total: ${formatCurrency(total)}*`;
  if (address) text += `\n📍 Endereço: ${address}`;
  if (paymentMethod) text += `\n💳 Pagamento: ${paymentMethod}`;
  return text;
}

function buildOwnerNotification(template, clientName, clientPhone, details) {
  return template
    .replace('{nome}', clientName || clientPhone)
    .replace('{telefone}', clientPhone)
    .replace('{detalhes}', details);
}

function extractNameFromMessage(message) {
  const lower = message.toLowerCase().trim();
  const patterns = [
    /(?:me chamo|meu nome é|sou o|sou a|pode me chamar de)\s+([a-záéíóúàèìòùâêîôûãõç]+(?:\s+[a-záéíóúàèìòùâêîôûãõç]+)?)/i,
    /^([a-záéíóúàèìòùâêîôûãõç]{2,}(?:\s+[a-záéíóúàèìòùâêîôûãõç]{2,})?)$/i
  ];
  for (const pattern of patterns) {
    const match = message.match(pattern);
    if (match) {
      const name = match[1].trim();
      if (name.length >= 2 && name.length <= 50) return capitalize(name);
    }
  }
  return null;
}

function capitalize(str) {
  return str.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
}

function isNameChangeRequest(message) {
  const lower = message.toLowerCase();
  return lower.includes('mudar meu nome') ||
    lower.includes('trocar meu nome') ||
    lower.includes('me chama de') ||
    lower.includes('pode me chamar de') ||
    lower.includes('meu nome agora é');
}

function detectIntent(message, modes) {
  const lower = message.toLowerCase();
  const orderKeywords = ['pedido', 'cardápio', 'cardapio', 'menu', 'produto', 'comprar', 'quero pedir', 'fazer pedido', 'delivery', 'entrega'];
  const appointmentKeywords = ['agendar', 'agendamento', 'marcar', 'horário', 'horario', 'consulta', 'disponível', 'disponivel', 'atendimento', 'reserva'];

  if (modes.includes('orders')) {
    for (const kw of orderKeywords) {
      if (lower.includes(kw)) return 'orders';
    }
  }
  if (modes.includes('appointments')) {
    for (const kw of appointmentKeywords) {
      if (lower.includes(kw)) return 'appointments';
    }
  }
  return 'qa';
}

function parseListNumber(message) {
  const match = message.trim().match(/^(\d+)$/);
  return match ? parseInt(match[1]) : null;
}

function splitModes(modeStr) {
  if (!modeStr) return ['qa'];
  return modeStr.split(',').map(m => m.trim());
}

module.exports = {
  formatCurrency, formatDate, formatTime, normalizePhone,
  buildSystemPrompt, buildOrderSummary, buildOwnerNotification,
  extractNameFromMessage, isNameChangeRequest, detectIntent,
  parseListNumber, splitModes, capitalize
};
