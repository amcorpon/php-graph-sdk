<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (isClientLoggedIn()) {
    header('Location: /client/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (clientLogin($db, trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: /client/dashboard.php');
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
<title>Login — Painel do Cliente</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<style>
  body { display:flex; align-items:center; justify-content:center; min-height:100vh; background: linear-gradient(135deg,#064e3b 0%,#065f46 100%); }
  .login-card { background:#fff; border-radius:20px; padding:44px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,.3); }
  .login-logo { text-align:center; margin-bottom:28px; }
  .login-logo .icon { width:64px;height:64px;background:#059669;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;font-size:30px;margin-bottom:12px; }
</style>
</head>
<body>
<div class="login-card">
  <div class="login-logo">
    <div class="icon">🏪</div>
    <h1 style="font-size:22px;font-weight:800">Meu Bot WhatsApp</h1>
    <p style="font-size:13px;color:var(--text-muted)">Painel do Cliente</p>
  </div>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label class="form-label">Usuário</label>
      <input type="text" name="username" class="form-control" placeholder="cliente" required autofocus>
    </div>
    <div class="form-group">
      <label class="form-label">Senha</label>
      <input type="password" name="password" class="form-control" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn btn-primary w-full btn-lg" style="background:#059669;margin-top:8px">
      Entrar no Painel
    </button>
  </form>
  <p style="text-align:center;margin-top:20px;font-size:12px;color:var(--text-muted)">
    <a href="/admin/login.php" style="color:#059669">Ir para o painel admin →</a>
  </p>
</div>
</body>
</html>
