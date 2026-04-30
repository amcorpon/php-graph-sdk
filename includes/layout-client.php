<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'Meu Bot' ?> — WhatsApp Bot Cliente</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  :root { --sidebar-bg: #064e3b; }
  .sidebar-nav a.active { background: #059669; }
</style>
</head>
<body>
<div id="toast-container"></div>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon" style="background:#059669">🤖</div>
    <div class="logo-text">
      <?= htmlspecialchars($_SESSION['client_business'] ?? 'Meu Bot') ?>
      <small>Painel do Cliente</small>
    </div>
  </div>

  <div class="sidebar-section">Configurações</div>
  <nav class="sidebar-nav">
    <a href="/client/dashboard.php" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-gauge-high"></i></span> Visão Geral
    </a>
    <a href="/client/knowledge.php" class="<?= ($activePage ?? '') === 'knowledge' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-book"></i></span> Base de Conhecimento
    </a>
    <a href="/client/products.php" class="<?= ($activePage ?? '') === 'products' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-box"></i></span> Produtos e Fretes
    </a>
    <a href="/client/schedule.php" class="<?= ($activePage ?? '') === 'schedule' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-calendar-days"></i></span> Agenda e Serviços
    </a>
  </nav>

  <div style="margin-top:auto;padding:16px 8px">
    <a href="/client/logout.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;color:rgba(255,255,255,.5);text-decoration:none;font-size:13px">
      <i class="fa-solid fa-right-from-bracket"></i> Sair
    </a>
  </div>
</aside>

<header class="topbar">
  <button class="menu-toggle" id="sidebar-toggle">☰</button>
  <div class="topbar-title"><?= $pageTitle ?? '' ?></div>
  <div class="topbar-actions">
    <span style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($_SESSION['client_username'] ?? '') ?></span>
  </div>
</header>

<main class="main-content">
