<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$activePage = 'bot-config';
$pageTitle  = 'Configurações do Bot';
$config     = getConfig($db);
$hours      = dbFetchAll($db, 'SELECT * FROM business_hours ORDER BY day_of_week');
$botStatus  = getBotStatus($db);

include __DIR__ . '/../includes/layout-admin.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">⚙️ Configurações do Bot</div>
    <div class="page-subtitle">Personalize o comportamento do seu assistente</div>
  </div>
</div>

<!-- BOT STATUS CARD -->
<div class="card mb-3">
  <div class="card-header">
    <div class="card-title">📱 Conexão WhatsApp</div>
    <div class="flex gap-2">
      <button onclick="botAction('start')" class="btn btn-primary btn-sm"><i class="fa-solid fa-play"></i> Iniciar</button>
      <button onclick="botAction('restart')" class="btn btn-warning btn-sm"><i class="fa-solid fa-rotate"></i> Reiniciar</button>
      <button onclick="botAction('stop')" class="btn btn-danger btn-sm"><i class="fa-solid fa-stop"></i> Parar</button>
      <button onclick="botAction('reset_session')" class="btn btn-outline btn-sm"><i class="fa-solid fa-key"></i> Reset Sessão</button>
    </div>
  </div>
  <div class="card-body">
    <div class="grid-2">
      <div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:8px;">Status atual:</p>
        <div id="bot-status-indicator" class="bot-status-pill disconnected">
          <span class="status-dot red"></span> Verificando...
        </div>
        <p id="bot-status-label" style="font-size:12px;color:var(--text-muted);margin-top:6px;"></p>
      </div>
      <div id="pairing-code-box" style="display:none">
        <p style="font-size:13px;font-weight:600;margin-bottom:6px;">📲 Código de Emparelhamento:</p>
        <div class="pairing-code-display" id="pairing-code-value">——</div>
        <p style="font-size:12px;color:var(--text-muted);">
          WhatsApp → Dispositivos Conectados → Conectar com número de telefone
        </p>
        <button onclick="copyToClipboard(document.getElementById('pairing-code-value').textContent)" class="btn btn-outline btn-sm mt-1">
          <i class="fa-solid fa-copy"></i> Copiar código
        </button>
      </div>
    </div>
    <div id="bot-logs-box" style="display:none;margin-top:16px;">
      <p style="font-size:13px;font-weight:600;margin-bottom:6px;">Logs do Bot:</p>
      <pre id="bot-logs-content" style="background:#0f1117;color:#a3e635;padding:14px;border-radius:8px;font-size:12px;overflow-x:auto;max-height:200px;overflow-y:auto;"></pre>
    </div>
    <button onclick="toggleLogs()" class="btn btn-outline btn-sm mt-2">
      <i class="fa-solid fa-terminal"></i> Ver Logs
    </button>
  </div>
</div>

<!-- CONFIG FORM -->
<form id="config-form">
<div class="grid-2" style="gap:22px;align-items:start">

  <!-- Coluna 1: Identidade -->
  <div>
    <div class="card mb-3">
      <div class="card-header"><div class="card-title">🤖 Identidade do Bot</div></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Nome do Bot <small>(nunca dirá que é um bot)</small></label>
          <input type="text" name="bot_name" class="form-control" value="<?= htmlspecialchars($config['bot_name'] ?? 'Bia') ?>" placeholder="Ex: Bia, João, Maria..." required>
        </div>
        <div class="form-group">
          <label class="form-label">Número do Bot <small>(com DDI, ex: 5511999999999)</small></label>
          <input type="text" name="bot_number" class="form-control" value="<?= htmlspecialchars($config['bot_number'] ?? '') ?>" placeholder="5511999999999">
          <div class="form-hint">Número onde o bot está conectado</div>
        </div>
        <div class="form-group">
          <label class="form-label">Número do Dono <small>(para notificações)</small></label>
          <input type="text" name="owner_number" class="form-control" value="<?= htmlspecialchars($config['owner_number'] ?? '') ?>" placeholder="5511999999999">
        </div>

        <div class="form-group">
          <label class="form-label">Persona / Personalidade</label>
          <select name="persona" class="form-select">
            <?php
            $personas = ['professional'=>'👔 Profissional','friendly'=>'😊 Amigável','fun'=>'😄 Divertida','formal'=>'🎩 Formal','empathetic'=>'💙 Empática'];
            foreach ($personas as $k => $v):
            ?>
            <option value="<?= $k ?>" <?= ($config['persona'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Tamanho das Respostas</label>
          <select name="response_length" class="form-select">
            <option value="short" <?= ($config['response_length'] ?? '') === 'short' ? 'selected' : '' ?>>📝 Curtas (2-3 frases)</option>
            <option value="medium" <?= ($config['response_length'] ?? '') === 'medium' ? 'selected' : '' ?>>📄 Médias (recomendado)</option>
            <option value="long" <?= ($config['response_length'] ?? '') === 'long' ? 'selected' : '' ?>>📋 Longas (detalhadas)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label flex-center gap-2">
            <label class="toggle-switch">
              <input type="checkbox" name="use_emojis" value="1" <?= ($config['use_emojis'] ?? 1) ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
            Usar Emojis nas Respostas
          </label>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><div class="card-title">🔧 Funcionalidades Ativas</div></div>
      <div class="card-body">
        <?php
        $activeModes = explode(',', $config['bot_mode'] ?? 'qa');
        $modes = ['qa' => '❓ Tirar Dúvidas', 'orders' => '🛍️ Receber Pedidos', 'appointments' => '📅 Agendamentos'];
        foreach ($modes as $k => $v):
        ?>
        <div class="form-check" style="margin-bottom:12px">
          <input type="checkbox" name="bot_mode[]" value="<?= $k ?>" id="mode_<?= $k ?>"
                 <?= in_array($k, $activeModes) ? 'checked' : '' ?>>
          <label for="mode_<?= $k ?>" style="font-size:14px;cursor:pointer"><?= $v ?></label>
        </div>
        <?php endforeach; ?>
        <div class="form-hint">Selecione os modos que o bot deve operar</div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">🔔 Notificações</div></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label flex-center gap-2">
            <label class="toggle-switch">
              <input type="checkbox" name="notify_owner" value="1" <?= ($config['notify_owner'] ?? 1) ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
            Notificar dono em pedidos/agendamentos
          </label>
        </div>
        <div class="form-group">
          <label class="form-label">Mensagem de Notificação ao Dono</label>
          <textarea name="owner_notification_template" class="form-control" rows="5"><?= htmlspecialchars($config['owner_notification_template'] ?? '') ?></textarea>
          <div class="form-hint">Variáveis: <code>{nome}</code>, <code>{telefone}</code>, <code>{detalhes}</code></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Coluna 2: Mensagens e Limites -->
  <div>
    <div class="card mb-3">
      <div class="card-header"><div class="card-title">💬 Mensagens Personalizadas</div></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Mensagem de Boas-vindas <small>(1º contato)</small></label>
          <textarea name="welcome_message" class="form-control" rows="3"><?= htmlspecialchars($config['welcome_message'] ?? '') ?></textarea>
          <div class="form-hint">Variável: <code>{nome}</code></div>
        </div>
        <div class="form-group">
          <label class="form-label">Mensagem de Retorno <small>(cliente voltando)</small></label>
          <textarea name="return_message" class="form-control" rows="3"><?= htmlspecialchars($config['return_message'] ?? '') ?></textarea>
          <div class="form-hint">Variável: <code>{nome}</code></div>
        </div>
        <div class="form-group">
          <label class="form-label flex-center gap-2">
            <label class="toggle-switch">
              <input type="checkbox" name="ask_name_on_first_contact" value="1" <?= ($config['ask_name_on_first_contact'] ?? 1) ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
            Perguntar nome no primeiro contato
          </label>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><div class="card-title">⏱️ Limites e Sessão</div></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Limite de Respostas por Conversa <small>(0 = ilimitado)</small></label>
          <input type="number" name="response_limit" class="form-control" value="<?= (int)($config['response_limit'] ?? 0) ?>" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Tempo de Inatividade para Resetar Sessão <small>(minutos)</small></label>
          <input type="number" name="max_session_idle" class="form-control" value="<?= (int)($config['max_session_idle'] ?? 60) ?>" min="5">
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="card-title">🕐 Horário de Funcionamento</div>
        <label class="toggle-switch">
          <input type="checkbox" name="business_hours_enabled" value="1" id="bh-enabled"
                 <?= ($config['business_hours_enabled'] ?? 0) ? 'checked' : '' ?>>
          <span class="toggle-slider"></span>
        </label>
      </div>
      <div class="card-body" id="business-hours-box" style="display:<?= ($config['business_hours_enabled'] ?? 0) ? 'block' : 'none' ?>">
        <div style="overflow-x:auto">
          <table>
            <thead><tr><th>Dia</th><th>Aberto</th><th>Abertura</th><th>Fechamento</th></tr></thead>
            <tbody>
            <?php
            $dayNames = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
            foreach ($hours as $h):
            ?>
            <tr>
              <td><?= $dayNames[$h['day_of_week']] ?></td>
              <td>
                <label class="toggle-switch" style="transform:scale(.85)">
                  <input type="checkbox" name="hours[<?= $h['day_of_week'] ?>][is_open]" value="1"
                         class="bh-toggle" data-day="<?= $h['day_of_week'] ?>"
                         <?= $h['is_open'] ? 'checked' : '' ?>>
                  <span class="toggle-slider"></span>
                </label>
              </td>
              <td>
                <input type="time" name="hours[<?= $h['day_of_week'] ?>][open_time]" class="form-control"
                       style="width:110px" value="<?= substr($h['open_time'],0,5) ?>"
                       <?= !$h['is_open'] ? 'disabled' : '' ?>>
              </td>
              <td>
                <input type="time" name="hours[<?= $h['day_of_week'] ?>][close_time]" class="form-control"
                       style="width:110px" value="<?= substr($h['close_time'],0,5) ?>"
                       <?= !$h['is_open'] ? 'disabled' : '' ?>>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="form-group mt-2">
          <label class="form-label">Mensagem fora do horário</label>
          <textarea name="out_of_hours_message" class="form-control" rows="2"><?= htmlspecialchars($config['out_of_hours_message'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  </div>
</div>

<div style="margin-top:24px;text-align:right">
  <button type="submit" class="btn btn-primary btn-lg" id="save-btn">
    <i class="fa-solid fa-floppy-disk"></i> Salvar Configurações
  </button>
</div>
</form>

<script>
document.getElementById('bh-enabled').addEventListener('change', function() {
  document.getElementById('business-hours-box').style.display = this.checked ? 'block' : 'none';
});

document.querySelectorAll('.bh-toggle').forEach(toggle => {
  toggle.addEventListener('change', function() {
    const day = this.dataset.day;
    const row = this.closest('tr');
    row.querySelectorAll('input[type=time]').forEach(i => i.disabled = !this.checked);
  });
});

document.getElementById('config-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('save-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Salvando...';

  const form = e.target;
  const data = {};

  // Campos simples
  ['bot_name','bot_number','owner_number','response_length','persona','response_limit','max_session_idle',
   'welcome_message','return_message','out_of_hours_message','owner_notification_template'].forEach(f => {
    const el = form.querySelector(`[name="${f}"]`);
    if (el) data[f] = el.value;
  });

  // Checkboxes
  ['use_emojis','notify_owner','business_hours_enabled','ask_name_on_first_contact'].forEach(f => {
    data[f] = form.querySelector(`[name="${f}"]`)?.checked ? 1 : 0;
  });

  // Modo (array de checkboxes)
  const modes = [...form.querySelectorAll('[name="bot_mode[]"]:checked')].map(el => el.value);
  data.bot_mode = modes.join(',');

  // Horários
  const hours = [];
  for (let d = 0; d <= 6; d++) {
    const isOpen = form.querySelector(`[name="hours[${d}][is_open]"]`)?.checked ? 1 : 0;
    const open   = form.querySelector(`[name="hours[${d}][open_time]"]`)?.value || '';
    const close  = form.querySelector(`[name="hours[${d}][close_time]"]`)?.value || '';
    hours.push({ day_of_week: d, is_open: isOpen, open_time: open + ':00', close_time: close + ':00' });
  }
  data.business_hours = hours;

  const res = await api('/api/config.php', 'POST', data);
  btn.disabled = false;
  btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar Configurações';
  if (res.success) { toast('Configurações salvas!', 'success'); }
  else { toast(res.error || 'Erro ao salvar', 'error'); }
});

async function botAction(action) {
  if (action === 'reset_session' && !confirm('Resetar a sessão irá desconectar o bot. Um novo código será gerado. Confirmar?')) return;
  const res = await api(`/api/bot-control.php?action=${action}`, 'POST', {action});
  toast(res.message || (res.success ? 'OK!' : res.error), res.success ? 'success' : 'error');
  if (action === 'reset_session') setTimeout(() => pollBotStatus(), 3000);
}

let logsVisible = false;
async function toggleLogs() {
  const box = document.getElementById('bot-logs-box');
  logsVisible = !logsVisible;
  box.style.display = logsVisible ? 'block' : 'none';
  if (logsVisible) {
    const res = await api('/api/bot-control.php?action=logs');
    document.getElementById('bot-logs-content').textContent = res.logs || 'Sem logs disponíveis';
  }
}
</script>

<?php include __DIR__ . '/../includes/layout-admin-end.php'; ?>
