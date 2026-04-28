<?php
$current = basename($_SERVER['PHP_SELF'], '.php');
?>
<header class="site-header">
  <div class="container header-inner">
    <a href="index.php" class="site-logo">
      <span class="logo-icon">🎬</span>
      <span>VideoScript<strong>AI</strong></span>
    </a>
    <nav class="site-nav">
      <a href="index.php"  class="<?= $current==='index'  ? 'active' : '' ?>">Dashboard</a>
      <a href="config.php" class="<?= $current==='config' ? 'active' : '' ?>">⚙ Config</a>
    </nav>
  </div>
</header>
