<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (isAdminLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (adminLogin($db, $username, $password)) {
        header('Location: /admin/dashboard.php');
        exit;
    }
    $error = 'Usuário ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — WhatsApp Bot Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<style>
  body { display:flex; align-items:center; justify-content:center; min-height:100vh; background: linear-gradient(135deg,#0f1117 0%,#1a2235 100%); }
  .login-card { background:#fff; border-radius:20px; padding:44px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,.3); }
  .login-logo { text-align:center; margin-bottom:28px; }
  .login-logo .icon { width:64px;height:64px;background:var(--accent);border-radius:16px;display:inline-flex;align-items:center;justify-content:center;font-size:30px;margin-bottom:12px; }
  .login-logo h1 { font-size:22px;font-weight:800;color:var(--text-main); }
  .login-logo p { font-size:13px;color:var(--text-muted); }
</style>
</head>
<body>
<div class="login-card">
  <div class="login-logo">
    <div class="icon">🤖</div>
    <h1>WhatsApp Bot</h1>
    <p>Painel de Administração</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="on">
    <div class="form-group">
      <label class="form-label">Usuário</label>
      <input type="text" name="username" class="form-control" placeholder="admin" required autofocus>
    </div>
    <div class="form-group">
      <label class="form-label">Senha</label>
      <input type="password" name="password" class="form-control" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn btn-primary w-full btn-lg" style="margin-top:8px;">
      Entrar no Painel
    </button>
  </form>
  <p style="text-align:center;margin-top:20px;font-size:12px;color:var(--text-muted);">
    <a href="/client/login.php" style="color:var(--accent);">Ir para o painel do cliente →</a>
  </p>
</div>
</body>
</html>
