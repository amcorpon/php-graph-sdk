const db = require('../utils/db');
const { formatCurrency, formatDate, formatTime, buildOwnerNotification, parseListNumber } = require('../utils/helpers');

async function handle(sock, jid, client, message, state) {
  const config = await db.getConfig();
  const ctx = (state && state.context) ? JSON.parse(state.context) : {};
  const currentState = (state && state.state) || 'idle';
  const lower = message.toLowerCase().trim();

  if (lower === 'cancelar' || lower === 'sair' || lower === 'voltar') {
    await db.resetConversationState(client.id);
    await sock.sendMessage(jid, { text: `Tudo bem! Agendamento cancelado. Posso ajudar em algo mais? 😊` });
    return;
  }

  if (currentState === 'idle' || currentState === 'selecting_service') {
    const services = await db.getServices();
    if (!services.length) {
      await sock.sendMessage(jid, { text: 'Não há serviços cadastrados. Entre em contato para agendar manualmente.' });
      return;
    }

    let servText = `📅 *Nossos Serviços*\n\n`;
    services.forEach((s, i) => {
      servText += `*${i + 1}.* ${s.name}\n`;
      if (s.description) servText += `   _${s.description}_\n`;
      if (s.duration_minutes) servText += `   ⏱ ${s.duration_minutes} min`;
      if (s.price) servText += ` · 💰 ${formatCurrency(s.price)}`;
      servText += '\n\n';
    });
    servText += `\nDigite o *número* do serviço desejado.\nDigite *"cancelar"* para sair.`;

    await sock.sendMessage(jid, { text: servText });
    await db.setConversationState(client.id, 'selecting_service', {
      services: services.map(s => ({ id: s.id, name: s.name, duration: s.duration_minutes, price: s.price }))
    });
    return;
  }

  if (currentState === 'selecting_service') {
    const num = parseListNumber(message);
    if (!num || num < 1 || num > ctx.services.length) {
      await sock.sendMessage(jid, { text: 'Por favor, digite o *número* do serviço desejado.' });
      return;
    }
    const service = ctx.services[num - 1];
    ctx.service = service;

    await db.setConversationState(client.id, 'selecting_date', ctx);
    await sock.sendMessage(jid, {
      text: `✅ *${service.name}* selecionado!\n\nQual *data* você prefere?\nDigite no formato *DD/MM/AAAA* (ex: 25/12/2025):`
    });
    return;
  }

  if (currentState === 'selecting_date') {
    const dateMatch = message.trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!dateMatch) {
      await sock.sendMessage(jid, { text: 'Formato inválido. Por favor, use *DD/MM/AAAA* (ex: 25/12/2025):' });
      return;
    }

    const [, day, month, year] = dateMatch;
    const date = `${year}-${month}-${day}`;
    const dateObj = new Date(date + 'T12:00:00');

    if (isNaN(dateObj.getTime())) {
      await sock.sendMessage(jid, { text: 'Data inválida. Tente novamente:' });
      return;
    }
    if (dateObj < new Date()) {
      await sock.sendMessage(jid, { text: 'Essa data já passou! Por favor, escolha uma data futura:' });
      return;
    }

    const slots = await db.getAvailableSlots(date);
    if (!slots.length) {
      await sock.sendMessage(jid, {
        text: `😔 Não há horários disponíveis em *${formatDate(date)}*. Por favor, escolha outra data:`
      });
      return;
    }

    let slotText = `📅 *${formatDate(date)}*\n\nHorários disponíveis:\n\n`;
    slots.forEach((s, i) => { slotText += `*${i + 1}.* ${formatTime(s)}\n`; });
    slotText += `\nDigite o *número* do horário desejado:`;

    ctx.date = date;
    ctx.slots = slots;
    await db.setConversationState(client.id, 'selecting_time', ctx);
    await sock.sendMessage(jid, { text: slotText });
    return;
  }

  if (currentState === 'selecting_time') {
    const num = parseListNumber(message);
    if (!num || num < 1 || num > ctx.slots.length) {
      await sock.sendMessage(jid, { text: 'Por favor, digite o *número* do horário desejado.' });
      return;
    }
    const time = ctx.slots[num - 1];
    ctx.time = time;

    const summary = `📋 *Confirmação do Agendamento*\n\n` +
      `🔖 Serviço: *${ctx.service.name}*\n` +
      `📅 Data: *${formatDate(ctx.date)}*\n` +
      `⏰ Horário: *${formatTime(time)}*\n` +
      (ctx.service.price ? `💰 Valor: *${formatCurrency(ctx.service.price)}*\n` : '') +
      `\nConfirma o agendamento? Digite *"confirmar"* ou *"cancelar"*:`;

    await db.setConversationState(client.id, 'confirming_appointment', ctx);
    await sock.sendMessage(jid, { text: summary });
    return;
  }

  if (currentState === 'confirming_appointment') {
    if (lower === 'confirmar' || lower === 'sim' || lower === 's') {
      const available = await db.checkAppointmentAvailability(ctx.date, ctx.time, ctx.service.duration || 60);
      if (!available) {
        await sock.sendMessage(jid, {
          text: `😔 Infelizmente esse horário acabou de ser ocupado. Vamos tentar outro?\n\nDigite a data novamente:`
        });
        await db.setConversationState(client.id, 'selecting_date', ctx);
        return;
      }

      const apptId = await db.createAppointment(
        client.id, ctx.service.id, ctx.service.name,
        ctx.date, ctx.time, ctx.service.duration || 60, null
      );

      await db.resetConversationState(client.id);
      await sock.sendMessage(jid, {
        text: `🎉 Agendamento *#${apptId}* confirmado${client.name ? ', ' + client.name : ''}!\n\n` +
          `🔖 ${ctx.service.name}\n📅 ${formatDate(ctx.date)} às ${formatTime(ctx.time)}\n\n` +
          `Te esperamos! Qualquer dúvida, é só chamar. 😊`
      });

      // Notificar dono
      if (config.notify_owner && config.owner_number) {
        const template = config.owner_notification_template || '🔔 *Novo Agendamento #{id}*\n\nCliente: {nome}\nTelefone: {telefone}\n\n{detalhes}';
        const details = `Serviço: ${ctx.service.name}\nData: ${formatDate(ctx.date)}\nHorário: ${formatTime(ctx.time)}`;
        const ownerMsg = buildOwnerNotification(
          template.replace('{id}', apptId),
          client.name || 'Não informado',
          client.phone_number,
          details
        );
        try {
          await sock.sendMessage(`${config.owner_number}@s.whatsapp.net`, { text: ownerMsg });
          await db.update('UPDATE appointments SET owner_notified = 1 WHERE id = ?', [apptId]);
        } catch (e) {
          await db.log('error', 'appointments_handler', `Erro ao notificar dono: ${e.message}`);
        }
      }
      return;
    }

    if (lower === 'cancelar' || lower === 'não' || lower === 'nao') {
      await db.resetConversationState(client.id);
      await sock.sendMessage(jid, { text: `Agendamento cancelado. Pode remarcar quando quiser! 😊` });
      return;
    }

    await sock.sendMessage(jid, { text: 'Digite *"confirmar"* para confirmar ou *"cancelar"* para cancelar.' });
  }
}

module.exports = { handle };
