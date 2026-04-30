const mysql = require('mysql2/promise');
const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '../../.env') });

const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'whatsapp_bot',
  charset: 'utf8mb4',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

async function query(sql, params = []) {
  const [rows] = await pool.execute(sql, params);
  return rows;
}

async function queryOne(sql, params = []) {
  const rows = await query(sql, params);
  return rows[0] || null;
}

async function insert(sql, params = []) {
  const [result] = await pool.execute(sql, params);
  return result.insertId;
}

async function update(sql, params = []) {
  const [result] = await pool.execute(sql, params);
  return result.affectedRows;
}

async function getConfig() {
  return await queryOne('SELECT * FROM bot_config LIMIT 1');
}

async function getAIKeys() {
  const rows = await query('SELECT * FROM ai_keys WHERE is_active = 1');
  const keys = {};
  rows.forEach(r => { keys[r.provider] = r; });
  return keys;
}

async function getKnowledgeBase() {
  const kb = await queryOne('SELECT * FROM knowledge_base LIMIT 1');
  if (!kb) return '';
  if (kb.source_type === 'url' && kb.url_content) return kb.url_content;
  return kb.content || '';
}

async function getOrCreateClient(phoneNumber) {
  let client = await queryOne('SELECT * FROM clients WHERE phone_number = ?', [phoneNumber]);
  if (!client) {
    const id = await insert(
      'INSERT INTO clients (phone_number) VALUES (?)',
      [phoneNumber]
    );
    client = await queryOne('SELECT * FROM clients WHERE id = ?', [id]);
  }
  return client;
}

async function updateClientName(clientId, name) {
  await update('UPDATE clients SET name = ?, nickname = ? WHERE id = ?', [name, name, clientId]);
}

async function getConversationHistory(clientId, limit = 10) {
  return await query(
    'SELECT role, message FROM conversations WHERE client_id = ? ORDER BY created_at DESC LIMIT ?',
    [clientId, limit]
  );
}

async function saveMessage(clientId, role, message, aiUsed = null) {
  await insert(
    'INSERT INTO conversations (client_id, role, message, ai_used) VALUES (?, ?, ?, ?)',
    [clientId, role, message, aiUsed]
  );
  await update('UPDATE clients SET total_messages = total_messages + 1, last_interaction = NOW() WHERE id = ?', [clientId]);
}

async function getConversationState(clientId) {
  return await queryOne('SELECT * FROM conversation_state WHERE client_id = ?', [clientId]);
}

async function setConversationState(clientId, state, context = {}) {
  const existing = await queryOne('SELECT id FROM conversation_state WHERE client_id = ?', [clientId]);
  if (existing) {
    await update(
      'UPDATE conversation_state SET state = ?, context = ? WHERE client_id = ?',
      [state, JSON.stringify(context), clientId]
    );
  } else {
    await insert(
      'INSERT INTO conversation_state (client_id, state, context) VALUES (?, ?, ?)',
      [clientId, state, JSON.stringify(context)]
    );
  }
}

async function resetConversationState(clientId) {
  await update('UPDATE conversation_state SET state = ?, context = ? WHERE client_id = ?', ['idle', '{}', clientId]);
}

async function updateBotStatus(connected, message = '', phoneNumber = '') {
  const existing = await queryOne('SELECT id FROM bot_status LIMIT 1');
  if (existing) {
    await update(
      'UPDATE bot_status SET is_connected = ?, status_message = ?, phone_number = ?, last_ping = NOW() WHERE id = ?',
      [connected ? 1 : 0, message, phoneNumber, existing.id]
    );
  } else {
    await insert(
      'INSERT INTO bot_status (is_connected, status_message, phone_number) VALUES (?, ?, ?)',
      [connected ? 1 : 0, message, phoneNumber]
    );
  }
}

async function updatePairingCode(code) {
  const existing = await queryOne('SELECT id FROM bot_status LIMIT 1');
  if (existing) {
    await update('UPDATE bot_status SET pairing_code = ? WHERE id = ?', [code, existing.id]);
  }
}

async function log(level, source, message, context = null) {
  try {
    await insert(
      'INSERT INTO system_logs (level, source, message, context) VALUES (?, ?, ?, ?)',
      [level, source, message, context ? JSON.stringify(context) : null]
    );
  } catch (e) {
    // Não quebrar o bot por erro de log
  }
}

async function getProducts(activeOnly = true) {
  const where = activeOnly ? 'WHERE is_active = 1' : '';
  return await query(`SELECT * FROM products ${where} ORDER BY sort_order, name`);
}

async function getFreight(neighborhood) {
  return await queryOne(
    'SELECT * FROM freight WHERE LOWER(neighborhood) = LOWER(?) AND is_active = 1',
    [neighborhood]
  );
}

async function getAllFreight() {
  return await query('SELECT * FROM freight WHERE is_active = 1 ORDER BY neighborhood');
}

async function createOrder(clientId, items, subtotal, freightPrice, total, neighborhood, address, paymentMethod, notes) {
  const orderId = await insert(
    `INSERT INTO orders (client_id, subtotal, freight_price, total, neighborhood, address, payment_method, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
    [clientId, subtotal, freightPrice, total, neighborhood, address, paymentMethod, notes]
  );
  for (const item of items) {
    await insert(
      `INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [orderId, item.productId || null, item.name, item.quantity, item.price, item.quantity * item.price]
    );
  }
  return orderId;
}

async function getServices(activeOnly = true) {
  const where = activeOnly ? 'WHERE is_active = 1' : '';
  return await query(`SELECT * FROM services ${where} ORDER BY sort_order, name`);
}

async function checkAppointmentAvailability(date, time, durationMinutes) {
  const endTime = addMinutesToTime(time, durationMinutes);
  const conflicts = await query(
    `SELECT id FROM appointments
     WHERE appointment_date = ?
     AND status != 'cancelled'
     AND (
       (appointment_time <= ? AND ADDTIME(appointment_time, SEC_TO_TIME(duration_minutes * 60)) > ?)
       OR (appointment_time < ? AND ADDTIME(appointment_time, SEC_TO_TIME(duration_minutes * 60)) >= ?)
       OR (appointment_time >= ? AND appointment_time < ?)
     )`,
    [date, time, time, endTime, endTime, time, endTime]
  );
  return conflicts.length === 0;
}

async function getAvailableSlots(date) {
  const dayOfWeek = new Date(date + 'T12:00:00').getDay();
  const hours = await queryOne('SELECT * FROM business_hours WHERE day_of_week = ?', [dayOfWeek]);
  if (!hours || !hours.is_open) return [];

  const booked = await query(
    "SELECT appointment_time, duration_minutes FROM appointments WHERE appointment_date = ? AND status != 'cancelled'",
    [date]
  );

  const slots = generateTimeSlots(hours.open_time, hours.close_time, 60);
  const available = [];
  for (const slot of slots) {
    let conflict = false;
    for (const appt of booked) {
      const apptEnd = addMinutesToTime(appt.appointment_time, appt.duration_minutes);
      const slotEnd = addMinutesToTime(slot, 60);
      if (slot < apptEnd && slotEnd > appt.appointment_time) {
        conflict = true;
        break;
      }
    }
    if (!conflict) available.push(slot);
  }
  return available;
}

async function createAppointment(clientId, serviceId, serviceName, date, time, durationMinutes, notes) {
  return await insert(
    `INSERT INTO appointments (client_id, service_id, service_name, appointment_date, appointment_time, duration_minutes, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?)`,
    [clientId, serviceId || null, serviceName, date, time, durationMinutes, notes || null]
  );
}

function generateTimeSlots(openTime, closeTime, intervalMinutes) {
  const slots = [];
  let current = timeToMinutes(openTime);
  const end = timeToMinutes(closeTime);
  while (current + intervalMinutes <= end) {
    slots.push(minutesToTime(current));
    current += intervalMinutes;
  }
  return slots;
}

function timeToMinutes(time) {
  const [h, m] = time.split(':').map(Number);
  return h * 60 + m;
}

function minutesToTime(minutes) {
  const h = Math.floor(minutes / 60).toString().padStart(2, '0');
  const m = (minutes % 60).toString().padStart(2, '0');
  return `${h}:${m}`;
}

function addMinutesToTime(time, minutes) {
  return minutesToTime(timeToMinutes(time) + minutes);
}

async function isBusinessOpen() {
  const config = await getConfig();
  if (!config.business_hours_enabled) return true;

  const now = new Date();
  const dayOfWeek = now.getDay();
  const currentTime = now.toTimeString().slice(0, 8);

  const hours = await queryOne('SELECT * FROM business_hours WHERE day_of_week = ?', [dayOfWeek]);
  if (!hours || !hours.is_open) return false;
  return currentTime >= hours.open_time && currentTime <= hours.close_time;
}

module.exports = {
  query, queryOne, insert, update,
  getConfig, getAIKeys, getKnowledgeBase,
  getOrCreateClient, updateClientName,
  getConversationHistory, saveMessage,
  getConversationState, setConversationState, resetConversationState,
  updateBotStatus, updatePairingCode, log,
  getProducts, getFreight, getAllFreight, createOrder,
  getServices, checkAppointmentAvailability, getAvailableSlots, createAppointment,
  isBusinessOpen, pool
};
