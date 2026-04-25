const {
  default: makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
  isJidBroadcast,
  isJidGroup,
  makeCacheableSignalKeyStore
} = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const path = require('path');
const pino = require('pino');
const db = require('./utils/db');
const messageHandler = require('./handlers/message');

const AUTH_DIR = path.join(__dirname, '../bot_auth');
let sock = null;
let reconnectAttempts = 0;
const MAX_RECONNECTS = 100;

async function connectToWhatsApp(phoneNumber) {
  const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
  const { version } = await fetchLatestBaileysVersion();
  const logger = pino({ level: 'silent' });

  sock = makeWASocket({
    version,
    auth: {
      creds: state.creds,
      keys: makeCacheableSignalKeyStore(state.keys, logger)
    },
    logger,
    printQRInTerminal: false,
    browser: ['WhatsApp Bot', 'Chrome', '120.0.0']
  });

  // Solicitar código de emparelhamento se não estiver registrado
  if (!sock.authState.creds.registered && phoneNumber) {
    await new Promise(resolve => setTimeout(resolve, 2000));
    try {
      const cleanPhone = phoneNumber.replace(/\D/g, '');
      const code = await sock.requestPairingCode(cleanPhone);
      console.log(`\n✅ CÓDIGO DE EMPARELHAMENTO: ${code}\n`);
      console.log('Abra o WhatsApp → Dispositivos Conectados → Conectar com número de telefone\n');
      await db.updatePairingCode(code);
      await db.updateBotStatus(false, `Aguardando emparelhamento. Código: ${code}`, cleanPhone);
    } catch (err) {
      console.error('Erro ao gerar código:', err.message);
      await db.log('error', 'whatsapp', `Erro ao gerar código: ${err.message}`);
    }
  }

  // Salvar credenciais
  sock.ev.on('creds.update', saveCreds);

  // Gerenciar conexão
  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (connection === 'open') {
      reconnectAttempts = 0;
      const botPhone = sock.user?.id?.split(':')[0] || '';
      console.log('✅ WhatsApp conectado! Número:', botPhone);
      await db.updateBotStatus(true, 'Conectado', botPhone);
      await db.log('info', 'whatsapp', 'Bot conectado ao WhatsApp', { phone: botPhone });
    }

    if (connection === 'close') {
      const reason = new Boom(lastDisconnect?.error)?.output?.statusCode;
      const shouldReconnect = reason !== DisconnectReason.loggedOut;

      console.log('⚠️ Conexão encerrada. Motivo:', reason, '| Reconectar:', shouldReconnect);
      await db.updateBotStatus(false, `Desconectado (${reason})`);

      if (shouldReconnect && reconnectAttempts < MAX_RECONNECTS) {
        reconnectAttempts++;
        const delay = Math.min(1000 * Math.pow(1.5, reconnectAttempts), 30000);
        console.log(`🔄 Reconectando em ${Math.round(delay / 1000)}s... (tentativa ${reconnectAttempts})`);
        setTimeout(() => connectToWhatsApp(phoneNumber), delay);
      } else if (reason === DisconnectReason.loggedOut) {
        console.log('🚪 Sessão encerrada. Limpe bot_auth/ e reinicie.');
        await db.log('warning', 'whatsapp', 'Sessão encerrada. É necessário reconfigurar.');
        await db.updateBotStatus(false, 'Sessão encerrada - reconfigurar');
      }
    }
  });

  // Processar mensagens recebidas
  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    if (type !== 'notify') return;

    for (const msg of messages) {
      if (msg.key.fromMe) continue;
      if (!msg.message) continue;
      if (isJidBroadcast(msg.key.remoteJid)) continue;
      if (isJidGroup(msg.key.remoteJid)) continue;

      const jid = msg.key.remoteJid;
      const text = extractMessageText(msg);
      if (!text || text.trim().length === 0) continue;

      console.log(`📩 [${jid}] ${text.substring(0, 80)}`);
      await messageHandler.handle(sock, jid, text);
    }
  });

  return sock;
}

function extractMessageText(msg) {
  const m = msg.message;
  return (
    m?.conversation ||
    m?.extendedTextMessage?.text ||
    m?.imageMessage?.caption ||
    m?.videoMessage?.caption ||
    m?.buttonsResponseMessage?.selectedDisplayText ||
    m?.listResponseMessage?.singleSelectReply?.selectedRowId ||
    m?.templateButtonReplyMessage?.selectedDisplayText ||
    ''
  );
}

function getSock() {
  return sock;
}

module.exports = { connectToWhatsApp, getSock };
