const db = require('../utils/db');
const qa = require('./qa');
const orders = require('./orders');
const appointments = require('./appointments');
const { extractNameFromMessage, isNameChangeRequest, detectIntent, splitModes } = require('../utils/helpers');

async function handle(sock, jid, rawMessage) {
  try {
    const phoneNumber = jid.replace('@s.whatsapp.net', '').replace('@c.us', '');
    const message = rawMessage.trim();

    // Buscar/criar cliente
    let client = await db.getOrCreateClient(phoneNumber);

    // Verificar se está bloqueado
    if (client.blocked) return;

    // Verificar horário de funcionamento
    const isOpen = await db.isBusinessOpen();
    if (!isOpen) {
      const config = await db.getConfig();
      if (config.out_of_hours_message) {
        await sock.sendMessage(jid, { text: config.out_of_hours_message });
      }
      return;
    }

    const config = await db.getConfig();
    const modes = splitModes(config.bot_mode);
    const state = await db.getConversationState(client.id);
    const currentState = state ? state.state : 'idle';

    // ── Fluxo: identificar nome (primeiro contato) ──────────────────
    if (!client.name && config.ask_name_on_first_contact) {
      if (currentState === 'asking_name') {
        const name = extractNameFromMessage(message);
        if (name) {
          await db.updateClientName(client.id, name);
          client = await db.getOrCreateClient(phoneNumber); // reload
          await db.resetConversationState(client.id);

          let greeting = config.welcome_message
            ? config.welcome_message.replace('{nome}', name)
            : `Prazer, ${name}! Como posso te ajudar hoje? 😊`;

          if (modes.length > 1 || (modes.includes('orders') || modes.includes('appointments'))) {
            greeting += buildModeMenu(modes);
          }
          await sock.sendMessage(jid, { text: greeting });
          return;
        } else {
          await sock.sendMessage(jid, { text: `Não consegui capturar seu nome. Pode me dizer apenas o seu nome? 😊` });
          return;
        }
      }

      // Primeiro contato — pedir nome
      await db.setConversationState(client.id, 'asking_name', {});
      const welcomeMsg = config.welcome_message || `Olá! Sou ${config.bot_name}. Para te atender melhor, pode me dizer seu nome? 😊`;
      await sock.sendMessage(jid, { text: welcomeMsg });
      return;
    }

    // ── Pedido de troca de nome ──────────────────────────────────────
    if (isNameChangeRequest(message)) {
      await db.setConversationState(client.id, 'changing_name', {});
      await sock.sendMessage(jid, { text: `Claro! Qual nome você gostaria que eu usasse? 😊` });
      return;
    }

    if (currentState === 'changing_name') {
      const name = extractNameFromMessage(message);
      if (name) {
        await db.updateClientName(client.id, name);
        await db.resetConversationState(client.id);
        await sock.sendMessage(jid, { text: `Perfeito! Agora vou te chamar de *${name}*. 😄` });
        return;
      }
      await sock.sendMessage(jid, { text: `Pode me dizer o nome que você prefere? 😊` });
      return;
    }

    // ── Retorno do cliente (tem nome, idle) ──────────────────────────
    if (currentState === 'idle' && client.total_messages > 1) {
      const lower = message.toLowerCase();
      const isGreeting = ['oi', 'olá', 'ola', 'bom dia', 'boa tarde', 'boa noite', 'hello', 'hey'].some(g => lower.startsWith(g));

      if (isGreeting && config.return_message) {
        const returnMsg = config.return_message.replace('{nome}', client.name || '');
        await sock.sendMessage(jid, { text: returnMsg });
        if (modes.length > 1 || modes.includes('orders') || modes.includes('appointments')) {
          await sock.sendMessage(jid, { text: buildModeMenu(modes) });
        }
        return;
      }
    }

    // ── Fluxo ativo de pedidos ───────────────────────────────────────
    if (currentState !== 'idle' && currentState !== 'asking_name' && currentState !== 'changing_name') {
      if (currentState.startsWith('selecting_') || currentState.startsWith('getting_') || currentState.startsWith('confirming_')) {
        const ctxStr = state.context || '{}';
        const ctx = JSON.parse(ctxStr);

        if (ctx.flow === 'orders' || currentState.includes('order') || currentState.includes('item') || currentState.includes('cart') || currentState.includes('neighborhood') || currentState.includes('address') || currentState.includes('payment')) {
          await orders.handle(sock, jid, client, message, state);
          return;
        }
        if (ctx.flow === 'appointments' || currentState.includes('service') || currentState.includes('date') || currentState.includes('time') || currentState.includes('appointment')) {
          await appointments.handle(sock, jid, client, message, state);
          return;
        }

        // Fallback: determinar pelo contexto
        if (ctx.cart !== undefined) {
          await orders.handle(sock, jid, client, message, state);
          return;
        }
        if (ctx.service !== undefined || ctx.slots !== undefined) {
          await appointments.handle(sock, jid, client, message, state);
          return;
        }
      }
    }

    // ── Detecção de intenção ─────────────────────────────────────────
    const intent = detectIntent(message, modes);

    if (intent === 'orders' && modes.includes('orders')) {
      await db.setConversationState(client.id, 'showing_menu', { flow: 'orders' });
      await orders.handle(sock, jid, client, message, { state: 'idle', context: '{"flow":"orders"}' });
      return;
    }

    if (intent === 'appointments' && modes.includes('appointments')) {
      await db.setConversationState(client.id, 'selecting_service', { flow: 'appointments' });
      await appointments.handle(sock, jid, client, message, { state: 'idle', context: '{"flow":"appointments"}' });
      return;
    }

    // Menu de opções quando múltiplos modos e mensagem genérica
    if (modes.length > 1 && isGenericGreeting(message)) {
      const name = client.name ? `, ${client.name}` : '';
      await sock.sendMessage(jid, { text: `Olá${name}! 😊${buildModeMenu(modes)}` });
      return;
    }

    if (modes.length > 1) {
      const lower = message.trim();
      if (lower === '1' && modes.includes('qa')) {
        await qa.handle(sock, jid, client, 'Olá, quero tirar uma dúvida');
        return;
      }
      if ((lower === '2' || (lower === '1' && !modes.includes('qa'))) && modes.includes('orders')) {
        await orders.handle(sock, jid, client, 'cardápio', null);
        return;
      }
      if (modes.includes('appointments') && (lower === '3' || lower === '2')) {
        await appointments.handle(sock, jid, client, 'agendar', null);
        return;
      }
    }

    // ── Resposta via IA (Q&A padrão) ─────────────────────────────────
    await qa.handle(sock, jid, client, message);

  } catch (error) {
    await db.log('error', 'message_handler', `Erro ao processar mensagem: ${error.message}`, { jid });
    console.error('Erro no message handler:', error);
    try {
      await sock.sendMessage(jid, { text: 'Desculpe, ocorreu um erro. Tente novamente em instantes.' });
    } catch {}
  }
}

function buildModeMenu(modes) {
  const items = [];
  if (modes.includes('qa')) items.push('1️⃣ Tirar dúvidas');
  if (modes.includes('orders')) items.push(`${items.length + 1}️⃣ Fazer um pedido`);
  if (modes.includes('appointments')) items.push(`${items.length + 1}️⃣ Agendar horário`);
  if (items.length <= 1) return '';
  return '\n\nO que você precisa?\n\n' + items.join('\n') + '\n\nDigite o número da opção 👆';
}

function isGenericGreeting(message) {
  const lower = message.toLowerCase().trim();
  return ['oi', 'olá', 'ola', 'bom dia', 'boa tarde', 'boa noite', 'hello', 'opa', 'hey', 'e ai', 'e aí'].includes(lower);
}

module.exports = { handle };
