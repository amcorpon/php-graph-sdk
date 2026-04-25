require('dotenv').config({ path: require('path').join(__dirname, '../.env') });
const cron = require('node-cron');
const db = require('./utils/db');
const { connectToWhatsApp } = require('./whatsapp');

async function start() {
  console.log('🤖 WhatsApp AI Bot iniciando...');

  // Testar conexão DB
  try {
    await db.query('SELECT 1');
    console.log('✅ Banco de dados conectado');
  } catch (err) {
    console.error('❌ Erro ao conectar no banco:', err.message);
    process.exit(1);
  }

  const config = await db.getConfig();
  const phoneNumber = config.bot_number || process.env.BOT_PHONE || '';

  if (!phoneNumber) {
    console.log('⚠️  Número do bot não configurado. Acesse o painel admin → Configurações para definir o número.');
  }

  await connectToWhatsApp(phoneNumber);

  // ── Ping de status a cada 30 segundos ────────────────────────────
  cron.schedule('*/30 * * * * *', async () => {
    try {
      await db.update('UPDATE bot_status SET last_ping = NOW() WHERE id = 1');
    } catch {}
  });

  // ── Limpeza de logs antigos (todo dia à meia-noite) ───────────────
  cron.schedule('0 0 * * *', async () => {
    try {
      await db.update("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
      await db.update("DELETE FROM conversations WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
      console.log('🧹 Limpeza de logs realizada');
    } catch (err) {
      console.error('Erro na limpeza:', err.message);
    }
  });

  // ── Atualizar analytics diário ────────────────────────────────────
  cron.schedule('*/5 * * * *', async () => {
    try {
      const today = new Date().toISOString().split('T')[0];
      await db.query(`
        INSERT INTO analytics_daily (date, total_messages, unique_users, total_orders, orders_revenue, total_appointments)
        SELECT
          DATE(c.created_at) as date,
          COUNT(c.id) as total_messages,
          COUNT(DISTINCT c.client_id) as unique_users,
          (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?) as total_orders,
          (SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at) = ?) as orders_revenue,
          (SELECT COUNT(*) FROM appointments WHERE DATE(created_at) = ?) as total_appointments
        FROM conversations c
        WHERE DATE(c.created_at) = ?
        ON DUPLICATE KEY UPDATE
          total_messages = VALUES(total_messages),
          unique_users = VALUES(unique_users),
          total_orders = VALUES(total_orders),
          orders_revenue = VALUES(orders_revenue),
          total_appointments = VALUES(total_appointments)
      `, [today, today, today, today]);
    } catch {}
  });

  // ── Reset de créditos esgotados (a cada 6 horas) ─────────────────
  cron.schedule('0 */6 * * *', async () => {
    try {
      await db.update('UPDATE ai_keys SET credits_exhausted = 0, last_error = NULL');
      console.log('🔄 Status de créditos das IAs resetado');
    } catch {}
  });

  console.log('✅ Bot iniciado com sucesso!');
}

start().catch(err => {
  console.error('❌ Erro fatal ao iniciar bot:', err);
  process.exit(1);
});

process.on('uncaughtException', (err) => {
  console.error('Uncaught Exception:', err);
  db.log('error', 'process', `Uncaught Exception: ${err.message}`).catch(() => {});
});

process.on('unhandledRejection', (reason) => {
  console.error('Unhandled Rejection:', reason);
  db.log('error', 'process', `Unhandled Rejection: ${reason}`).catch(() => {});
});
