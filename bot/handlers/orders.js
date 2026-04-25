const db = require('../utils/db');
const { formatCurrency, buildOrderSummary, buildOwnerNotification, parseListNumber } = require('../utils/helpers');

async function handle(sock, jid, client, message, state) {
  const config = await db.getConfig();
  const ctx = (state && state.context) ? JSON.parse(state.context) : {};
  const currentState = (state && state.state) || 'idle';
  const lower = message.toLowerCase().trim();

  // Cancelar pedido a qualquer momento
  if (lower === 'cancelar' || lower === 'sair' || lower === 'voltar') {
    await db.resetConversationState(client.id);
    await sock.sendMessage(jid, { text: `Tudo bem${client.name ? ', ' + client.name : ''}! Seu pedido foi cancelado. Posso ajudar em algo mais? 😊` });
    return;
  }

  if (currentState === 'idle' || currentState === 'showing_menu') {
    const products = await db.getProducts();
    if (!products.length) {
      await sock.sendMessage(jid, { text: 'Ainda não temos produtos cadastrados. Em breve teremos!' });
      return;
    }

    let menuText = `🛍️ *Nosso Cardápio*\n\n`;
    products.forEach((p, i) => {
      menuText += `*${i + 1}.* ${p.name}\n`;
      if (p.description) menuText += `   _${p.description}_\n`;
      menuText += `   💰 ${formatCurrency(p.price)}\n\n`;
    });
    menuText += `\nDigite o *número* do item que deseja adicionar ao pedido.\nDigite *"finalizar"* quando terminar.\nDigite *"cancelar"* para sair.`;

    await sock.sendMessage(jid, { text: menuText });
    await db.setConversationState(client.id, 'selecting_items', {
      cart: [],
      products: products.map(p => ({ id: p.id, name: p.name, price: parseFloat(p.price) }))
    });
    return;
  }

  if (currentState === 'selecting_items') {
    if (lower === 'finalizar' || lower === 'pronto' || lower === 'fechar pedido') {
      if (!ctx.cart || ctx.cart.length === 0) {
        await sock.sendMessage(jid, { text: 'Seu carrinho está vazio! Adicione itens antes de finalizar. 🛒' });
        return;
      }
      const subtotal = ctx.cart.reduce((s, i) => s + i.price * i.quantity, 0);
      let cartText = `🛒 *Seu carrinho:*\n\n`;
      ctx.cart.forEach((item, i) => {
        cartText += `${i + 1}. ${item.name} x${item.quantity} — ${formatCurrency(item.price * item.quantity)}\n`;
      });
      cartText += `\n💰 *Subtotal: ${formatCurrency(subtotal)}*\n\nQual é o seu *bairro* para calcular o frete?`;
      await sock.sendMessage(jid, { text: cartText });
      await db.setConversationState(client.id, 'getting_neighborhood', { ...ctx, subtotal });
      return;
    }

    // Verificar se digitou quantidade (ex: "2 unidades" ou "x2")
    const qtyMatch = message.match(/^(\d+)\s*[xX]?\s*(\d+)$|^[xX](\d+)$/);
    if (qtyMatch && ctx.lastAdded !== undefined) {
      const qty = parseInt(qtyMatch[1] || qtyMatch[3]);
      ctx.cart[ctx.lastAdded].quantity = qty;
      await db.setConversationState(client.id, 'selecting_items', ctx);
      await sock.sendMessage(jid, { text: `✅ Quantidade atualizada para ${qty}x ${ctx.cart[ctx.lastAdded].name}. Continue adicionando ou digite *"finalizar"*.` });
      return;
    }

    const num = parseListNumber(message);
    if (num && num >= 1 && num <= ctx.products.length) {
      const product = ctx.products[num - 1];
      const existing = ctx.cart.find(i => i.id === product.id);
      if (existing) {
        existing.quantity++;
        ctx.lastAdded = ctx.cart.indexOf(existing);
      } else {
        ctx.cart.push({ id: product.id, name: product.name, price: product.price, quantity: 1 });
        ctx.lastAdded = ctx.cart.length - 1;
      }

      const totalItems = ctx.cart.reduce((s, i) => s + i.quantity, 0);
      await db.setConversationState(client.id, 'selecting_items', ctx);
      await sock.sendMessage(jid, {
        text: `✅ *${product.name}* adicionado! (${totalItems} item${totalItems > 1 ? 's' : ''} no carrinho)\n\nContinue adicionando ou digite *"finalizar"* para fechar o pedido.`
      });
      return;
    }

    await sock.sendMessage(jid, { text: `Por favor, digite o *número* do item desejado ou *"finalizar"* para fechar o pedido.` });
    return;
  }

  if (currentState === 'getting_neighborhood') {
    const freights = await db.getAllFreight();
    const freight = await db.getFreight(message.trim());

    if (!freight && freights.length > 0) {
      let freightList = `🗺️ Não encontrei esse bairro. Confira os bairros disponíveis:\n\n`;
      freights.forEach((f, i) => {
        freightList += `*${i + 1}.* ${f.neighborhood} — ${formatCurrency(f.price)}${f.delivery_time ? ' (⏱ ' + f.delivery_time + ')' : ''}\n`;
      });
      freightList += `\nDigite o nome do seu bairro:`;
      await sock.sendMessage(jid, { text: freightList });
      return;
    }

    const freightPrice = freight ? parseFloat(freight.price) : 0;
    const total = ctx.subtotal + freightPrice;
    ctx.neighborhood = message.trim();
    ctx.freightPrice = freightPrice;
    ctx.total = total;

    let addressMsg = `📍 Seu bairro: *${message.trim()}*\n`;
    if (freight) addressMsg += `🚗 Frete: ${formatCurrency(freightPrice)}${freight.delivery_time ? ' (⏱ ' + freight.delivery_time + ')' : ''}\n`;
    addressMsg += `\n💰 *Total: ${formatCurrency(total)}*\n\nAgora me informe o *endereço completo* (rua, número, complemento):`;

    await sock.sendMessage(jid, { text: addressMsg });
    await db.setConversationState(client.id, 'getting_address', ctx);
    return;
  }

  if (currentState === 'getting_address') {
    ctx.address = message.trim();
    await db.setConversationState(client.id, 'getting_payment', ctx);
    await sock.sendMessage(jid, {
      text: `✅ Endereço anotado!\n\nQual a forma de *pagamento*?\n\n1. Dinheiro\n2. Cartão débito\n3. Cartão crédito\n4. PIX\n\nDigite o número ou o nome:`
    });
    return;
  }

  if (currentState === 'getting_payment') {
    const paymentMap = { '1': 'Dinheiro', '2': 'Débito', '3': 'Crédito', '4': 'PIX' };
    ctx.paymentMethod = paymentMap[message.trim()] || message.trim();

    const summary = buildOrderSummary(ctx.cart, ctx.subtotal, ctx.freightPrice, ctx.total, ctx.neighborhood, ctx.address, ctx.paymentMethod);
    await sock.sendMessage(jid, { text: `${summary}\n\nConfirma o pedido? Digite *"confirmar"* ou *"cancelar"*.` });
    await db.setConversationState(client.id, 'confirming_order', ctx);
    return;
  }

  if (currentState === 'confirming_order') {
    if (lower === 'confirmar' || lower === 'sim' || lower === 's') {
      const orderId = await db.createOrder(
        client.id, ctx.cart, ctx.subtotal, ctx.freightPrice, ctx.total,
        ctx.neighborhood, ctx.address, ctx.paymentMethod, null
      );

      await db.saveMessage(client.id, 'assistant', `[Pedido #${orderId} confirmado]`, 'system');
      await db.resetConversationState(client.id);

      const orderDetails = buildOrderSummary(ctx.cart, ctx.subtotal, ctx.freightPrice, ctx.total, ctx.neighborhood, ctx.address, ctx.paymentMethod);
      const confirmMsg = `🎉 Pedido *#${orderId}* confirmado${client.name ? ', ' + client.name : ''}!\n\n${orderDetails}\n\nEm breve entraremos em contato. Obrigado! 🙏`;
      await sock.sendMessage(jid, { text: confirmMsg });

      // Notificar dono
      if (config.notify_owner && config.owner_number) {
        const template = config.owner_notification_template || '🔔 *Novo Pedido #{id}*\n\nCliente: {nome}\nTelefone: {telefone}\n\n{detalhes}';
        const ownerMsg = buildOwnerNotification(
          template.replace('{id}', orderId),
          client.name || 'Não informado',
          client.phone_number,
          orderDetails
        );
        try {
          await sock.sendMessage(`${config.owner_number}@s.whatsapp.net`, { text: ownerMsg });
          await db.update('UPDATE orders SET owner_notified = 1 WHERE id = ?', [orderId]);
        } catch (e) {
          await db.log('error', 'orders_handler', `Erro ao notificar dono: ${e.message}`);
        }
      }
      return;
    }
    if (lower === 'cancelar' || lower === 'não' || lower === 'nao') {
      await db.resetConversationState(client.id);
      await sock.sendMessage(jid, { text: `Pedido cancelado. Pode fazer novo pedido quando quiser! 😊` });
      return;
    }
    await sock.sendMessage(jid, { text: 'Por favor, confirme digitando *"confirmar"* ou cancele digitando *"cancelar"*.' });
  }
}

module.exports = { handle };
