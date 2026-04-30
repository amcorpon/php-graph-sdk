<?php
/**
 * Instalador do WhatsApp AI Bot
 * Execute este arquivo uma vez para configurar o banco de dados e criar o primeiro admin.
 *
 * IMPORTANTE: Delete ou mova este arquivo após a instalação!
 */

$step    = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$message = '';
$error   = '';

// ── STEP 2: Processar instalação ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? 'whatsapp_bot');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = $_POST['db_pass'] ?? '';

    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = $_POST['admin_pass'] ?? '';
    $adminPassConf = $_POST['admin_pass_confirm'] ?? '';

    $clientUser = trim($_POST['client_user'] ?? 'cliente');
    $clientPass = $_POST['client_pass'] ?? '';
    $clientBusiness = trim($_POST['client_business'] ?? 'Meu Negócio');

    if (empty($adminUser) || empty($adminPass)) {
        $error = 'Usuário e senha do admin são obrigatórios.';
    } elseif ($adminPass !== $adminPassConf) {
        $error = 'As senhas do admin não coincidem.';
    } elseif (strlen($adminPass) < 6) {
        $error = 'A senha deve ter ao menos 6 caracteres.';
    } else {
        // Conectar ao banco
        $db = @mysqli_connect($dbHost, $dbUser, $dbPass);
        if (!$db) {
            $error = 'Erro de conexão: ' . mysqli_connect_error();
        } else {
            // Criar banco se não existir
            mysqli_query($db, "CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            mysqli_select_db($db, $dbName);
            mysqli_set_charset($db, 'utf8mb4');

            // Importar SQL
            $sqlFile = __DIR__ . '/database.sql';
            if (!file_exists($sqlFile)) {
                $error = 'Arquivo database.sql não encontrado!';
            } else {
                $sql = file_get_contents($sqlFile);
                // Remover a linha CREATE DATABASE e USE pois já selecionamos
                $sql = preg_replace('/CREATE DATABASE[^;]+;/i', '', $sql);
                $sql = preg_replace('/USE `[^`]+`;/i', '', $sql);

                // Executar statement por statement
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                $errorSql   = null;
                mysqli_begin_transaction($db);
                foreach ($statements as $stmt) {
                    if (empty($stmt) || strpos($stmt, '--') === 0) continue;
                    if (!mysqli_query($db, $stmt)) {
                        $errorSql = mysqli_error($db) . "\nSQL: " . substr($stmt, 0, 200);
                        break;
                    }
                }

                if ($errorSql) {
                    mysqli_rollback($db);
                    $error = 'Erro ao importar banco: ' . $errorSql;
                } else {
                    mysqli_commit($db);

                    // Criar admin
                    $adminHash  = password_hash($adminPass,  PASSWORD_BCRYPT);
                    $clientHash = password_hash($clientPass ?: $clientUser . '123', PASSWORD_BCRYPT);

                    mysqli_query($db, "DELETE FROM admin_users");
                    mysqli_query($db, "INSERT INTO admin_users (username, password, role) VALUES ('" . mysqli_real_escape_string($db, $adminUser) . "', '$adminHash', 'super_admin')");

                    mysqli_query($db, "DELETE FROM client_users");
                    mysqli_query($db, "INSERT INTO client_users (username, password, business_name) VALUES ('" . mysqli_real_escape_string($db, $clientUser) . "', '$clientHash', '" . mysqli_real_escape_string($db, $clientBusiness) . "')");

                    // Criar arquivo .env
                    $envContent = "DB_HOST=$dbHost\nDB_NAME=$dbName\nDB_USER=$dbUser\nDB_PASS=$dbPass\nNODE_ENV=production\n";
                    file_put_contents(__DIR__ . '/.env', $envContent);

                    // Atualizar includes/config.php com os dados reais
                    $configFile = __DIR__ . '/includes/config.php';
                    if (file_exists($configFile)) {
                        $cfg = file_get_contents($configFile);
                        $cfg = preg_replace("/define\('DB_HOST',\s*'[^']*'\)/",   "define('DB_HOST', '$dbHost')", $cfg);
                        $cfg = preg_replace("/define\('DB_NAME',\s*'[^']*'\)/",   "define('DB_NAME', '$dbName')", $cfg);
                        $cfg = preg_replace("/define\('DB_USER',\s*'[^']*'\)/",   "define('DB_USER', '$dbUser')", $cfg);
                        $cfg = preg_replace("/define\('DB_PASS',\s*'[^']*'\)/",   "define('DB_PASS', '" . addslashes($dbPass) . "')", $cfg);
                        file_put_contents($configFile, $cfg);
                    }

                    // Instalar dependências Node.js do bot automaticamente
                    $npmOutput  = '';
                    $npmSuccess = false;
                    $botDir     = realpath(__DIR__ . '/bot');

                    if (!function_exists('shell_exec')) {
                        $npmOutput = 'shell_exec desabilitado no PHP. Rode manualmente: <code>cd bot && npm install</code>';
                    } elseif (!$botDir || !is_dir($botDir)) {
                        $npmOutput = 'Pasta bot/ não encontrada. Rode manualmente: <code>cd bot && npm install</code>';
                    } else {
                        $escaped   = escapeshellarg($botDir);
                        $rawOutput = shell_exec("cd $escaped && npm install --production 2>&1");
                        if ($rawOutput === null) {
                            $npmOutput = 'npm não pôde ser executado. Rode manualmente: <code>cd bot && npm install</code>';
                        } else {
                            $npmOutput  = $rawOutput;
                            $npmSuccess = stripos($rawOutput, 'error') === false && trim($rawOutput) !== '';
                        }
                    }

                    $step = 3;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalação — WhatsApp AI Bot</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:Inter,sans-serif; background:linear-gradient(135deg,#0f1117 0%,#1a2235 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
  .card { background:#fff; border-radius:20px; padding:40px; width:100%; max-width:560px; box-shadow:0 20px 60px rgba(0,0,0,.3); }
  .header { text-align:center; margin-bottom:30px; }
  .icon { width:64px;height:64px;background:#25a244;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;font-size:30px;margin-bottom:12px; }
  h1 { font-size:22px; font-weight:800; color:#1a1d2e; }
  p.sub { font-size:13px; color:#6b7280; margin-top:4px; }
  .steps { display:flex; gap:0; margin-bottom:28px; }
  .step-item { flex:1; text-align:center; padding:8px; font-size:12px; font-weight:600; }
  .step-item.active { color:#25a244; border-bottom:2px solid #25a244; }
  .step-item.done { color:#6b7280; border-bottom:2px solid #d1fae5; }
  .step-item.pending { color:#d1d5db; border-bottom:2px solid #f3f4f6; }
  .form-group { margin-bottom:16px; }
  label { display:block; font-size:13px; font-weight:600; color:#1a1d2e; margin-bottom:6px; }
  input { width:100%; padding:10px 14px; border:1.5px solid #e5e7eb; border-radius:8px; font-size:13.5px; outline:none; }
  input:focus { border-color:#25a244; box-shadow:0 0 0 3px rgba(37,162,68,.12); }
  .btn { display:block; width:100%; padding:12px; background:#25a244; color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; margin-top:8px; }
  .btn:hover { background:#1d8035; }
  .alert-error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; border-radius:8px; padding:12px; margin-bottom:16px; font-size:13.5px; }
  .alert-success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; border-radius:8px; padding:12px; margin-bottom:16px; font-size:13.5px; }
  .section-title { font-size:14px; font-weight:700; color:#1a1d2e; border-bottom:1px solid #e5e7eb; padding-bottom:8px; margin-bottom:14px; margin-top:20px; }
  .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  small { font-size:11px; color:#9ca3af; font-weight:400; }
  .success-box { text-align:center; padding:10px 0; }
  .success-icon { font-size:48px; }
  .success-box h2 { font-size:20px; font-weight:800; color:#065f46; margin:12px 0 8px; }
  .links { display:flex; gap:12px; margin-top:24px; justify-content:center; flex-wrap:wrap; }
  .link-btn { padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:600; font-size:14px; }
  .link-btn.primary { background:#25a244; color:#fff; }
  .link-btn.secondary { background:#f3f4f6; color:#1a1d2e; }
  .warning-box { background:#fef3c7; border:1px solid #fde68a; border-radius:8px; padding:12px; margin-top:20px; font-size:13px; color:#92400e; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <div class="icon">🤖</div>
    <h1>WhatsApp AI Bot</h1>
    <p class="sub">Instalação e Configuração Inicial</p>
  </div>

  <div class="steps">
    <div class="step-item <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : 'pending' ?>">1. Boas-vindas</div>
    <div class="step-item <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : 'pending' ?>">2. Configuração</div>
    <div class="step-item <?= $step >= 3 ? 'active' : 'pending' ?>">3. Concluído</div>
  </div>

  <?php if ($step === 1): ?>
  <!-- STEP 1: Boas-vindas -->
  <div class="alert-success">✅ Servidor PHP detectado! Vamos configurar o banco de dados e criar as credenciais de acesso.</div>
  <p style="font-size:13.5px;line-height:1.7;color:#374151;margin-bottom:20px">
    Este instalador irá:
    <br>• Criar o banco de dados MySQL
    <br>• Importar todas as tabelas necessárias
    <br>• Criar o usuário admin e o painel do cliente
    <br>• Gerar o arquivo <code>.env</code> de configuração
  </p>
  <p style="font-size:13px;color:#6b7280;margin-bottom:20px">
    <strong>Requisitos:</strong> PHP 7.4+, MySQL 5.7+, Node.js 18+
  </p>
  <a href="?step=2"><button type="button" class="btn">Começar Instalação →</button></a>

  <?php elseif ($step === 2): ?>
  <!-- STEP 2: Formulário -->
  <?php if ($error): ?><div class="alert-error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST">
    <input type="hidden" name="step" value="2">

    <div class="section-title">🗄️ Banco de Dados MySQL</div>
    <div class="grid-2">
      <div class="form-group">
        <label>Servidor <small>(host)</small></label>
        <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>">
      </div>
      <div class="form-group">
        <label>Nome do Banco</label>
        <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'whatsapp_bot') ?>">
      </div>
    </div>
    <div class="grid-2">
      <div class="form-group">
        <label>Usuário MySQL</label>
        <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>">
      </div>
      <div class="form-group">
        <label>Senha MySQL</label>
        <input type="password" name="db_pass" value="">
      </div>
    </div>

    <div class="section-title">👤 Painel Administrador</div>
    <div class="grid-2">
      <div class="form-group">
        <label>Usuário Admin</label>
        <input type="text" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>">
      </div>
      <div class="form-group">
        <label>Senha Admin</label>
        <input type="password" name="admin_pass">
      </div>
    </div>
    <div class="form-group">
      <label>Confirmar Senha Admin</label>
      <input type="password" name="admin_pass_confirm">
    </div>

    <div class="section-title">🏪 Painel do Cliente</div>
    <div class="grid-2">
      <div class="form-group">
        <label>Usuário Cliente</label>
        <input type="text" name="client_user" value="<?= htmlspecialchars($_POST['client_user'] ?? 'cliente') ?>">
      </div>
      <div class="form-group">
        <label>Senha <small>(opcional)</small></label>
        <input type="password" name="client_pass" placeholder="Se vazio: usuario+123">
      </div>
    </div>
    <div class="form-group">
      <label>Nome do Negócio</label>
      <input type="text" name="client_business" value="<?= htmlspecialchars($_POST['client_business'] ?? 'Meu Negócio') ?>">
    </div>

    <button type="submit" class="btn">Instalar Sistema →</button>
  </form>

  <?php elseif ($step === 3): ?>
  <!-- STEP 3: Sucesso -->
  <div class="success-box">
    <div class="success-icon">🎉</div>
    <h2>Instalação Concluída!</h2>
    <p style="font-size:13.5px;color:#374151">Banco de dados criado, usuários configurados.</p>

    <div class="links">
      <a href="/admin/login.php" class="link-btn primary">🤖 Painel Admin</a>
      <a href="/client/login.php" class="link-btn secondary">🏪 Painel Cliente</a>
    </div>
  </div>

  <!-- Resultado do npm install -->
  <?php if (!empty($npmSuccess)): ?>
  <div class="alert-success" style="margin-top:20px">
    ✅ <strong>Dependências Node.js instaladas com sucesso!</strong> O bot está pronto para iniciar.
  </div>
  <?php elseif (!empty($npmOutput)): ?>
  <div class="alert-error" style="margin-top:20px">
    ⚠️ <strong>npm install — atenção:</strong><br>
    <?= nl2br(htmlspecialchars($npmOutput)) ?>
    <br><br>Se houver erros, rode manualmente no servidor:<br>
    <code style="background:#fee2e2;padding:4px 8px;border-radius:4px;font-size:12px">cd <?= htmlspecialchars(realpath(__DIR__ . '/bot') ?: __DIR__ . '/bot') ?> &amp;&amp; npm install</code>
  </div>
  <?php endif; ?>

  <div class="warning-box">
    ⚠️ <strong>Importante:</strong> Delete ou mova o arquivo <code>install.php</code> do servidor para evitar re-instalação acidental.
    <br><br>
    <strong>Próximos passos:</strong>
    <ol style="margin-top:8px;padding-left:18px;line-height:2">
      <li>Acesse o <strong>Painel Admin</strong> → Configurações de IA e adicione sua chave de API</li>
      <li>Configure o nome e número do bot em <strong>Configurações do Bot</strong></li>
      <li>Clique em <strong>Iniciar Bot</strong> no painel</li>
      <li>Digite o código de emparelhamento que aparecer no WhatsApp</li>
    </ol>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
