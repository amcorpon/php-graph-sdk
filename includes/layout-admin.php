<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'Painel Admin' ?> — WhatsApp Bot</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div id="toast-container"></div>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">🤖</div>
    <div class="logo-text">WhatsApp Bot<small>Painel Admin</small></div>
  </div>

  <div class="sidebar-section">Principal</div>
  <nav class="sidebar-nav">
    <a href="/admin/dashboard.php" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-gauge-high"></i></span> Dashboard
    </a>
    <a href="/admin/bot-config.php" class="<?= ($activePage ?? '') === 'bot-config' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-robot"></i></span> Configurações do Bot
    </a>
    <a href="/admin/ai-settings.php" class="<?= ($activePage ?? '') === 'ai-settings' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-brain"></i></span> Configurações de IA
    </a>
  </nav>

  <div class="sidebar-section">Conteúdo</div>
  <nav class="sidebar-nav">
    <a href="/admin/knowledge.php" class="<?= ($activePage ?? '') === 'knowledge' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-book"></i></span> Base de Conhecimento
    </a>
    <a href="/admin/products.php" class="<?= ($activePage ?? '') === 'products' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-box"></i></span> Produtos
    </a>
    <a href="/admin/freight.php" class="<?= ($activePage ?? '') === 'freight' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-truck"></i></span> Fretes
    </a>
    <a href="/admin/appointments.php?tab=services" class="<?= ($activePage ?? '') === 'services' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-scissors"></i></span> Serviços
    </a>
  </nav>

  <div class="sidebar-section">Atendimentos</div>
  <nav class="sidebar-nav">
    <a href="/admin/orders.php" class="<?= ($activePage ?? '') === 'orders' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-bag-shopping"></i></span> Pedidos
    </a>
    <a href="/admin/appointments.php" class="<?= ($activePage ?? '') === 'appointments' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-calendar-days"></i></span> Agendamentos
    </a>
    <a href="/admin/conversations.php" class="<?= ($activePage ?? '') === 'conversations' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-comments"></i></span> Conversas
    </a>
    <a href="/admin/clients.php" class="<?= ($activePage ?? '') === 'clients' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-users"></i></span> Clientes
    </a>
  </nav>

  <div style="margin-top: auto; padding: 16px 8px;">
    <a href="/admin/logout.php" class="sidebar-nav" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;color:rgba(255,255,255,.5);text-decoration:none;font-size:13px;">
      <i class="fa-solid fa-right-from-bracket"></i> Sair
    </a>
  </div>
</aside>

<!-- Topbar -->
<header class="topbar">
  <button class="menu-toggle" id="sidebar-toggle">☰</button>
  <div class="topbar-title"><?= $pageTitle ?? '' ?></div>
  <div class="topbar-actions">
    <div id="bot-status-indicator" class="bot-status-pill disconnected">
      <span class="status-dot red"></span> Verificando...
    </div>
    <div style="font-size:13px;color:var(--text-muted);">
      <?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>
    </div>
  </div>
</header>

<main class="main-content">
