<?php
/**
 * One-click installer — creates .env and runs schema.sql
 */
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host   = trim($_POST['db_host']   ?? 'localhost');
    $user   = trim($_POST['db_user']   ?? 'root');
    $pass   = trim($_POST['db_pass']   ?? '');
    $name   = trim($_POST['db_name']   ?? 'video_script_gen');
    $python = trim($_POST['python_bin'] ?? 'python3');
    $ffmpeg = trim($_POST['ffmpeg_bin'] ?? 'ffmpeg');

    // Test connection
    $conn = @mysqli_connect($host, $user, $pass);
    if (!$conn) {
        $error = 'Cannot connect to MySQL: ' . mysqli_connect_error();
    } else {
        // Create DB if not exists
        mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        mysqli_select_db($conn, $name);

        // Run schema
        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        // Split statements
        foreach (explode(';', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt) mysqli_query($conn, $stmt);
        }
        mysqli_close($conn);

        // Write .env
        $env = "DB_HOST=$host\nDB_USER=$user\nDB_PASS=$pass\nDB_NAME=$name\nPYTHON_BIN=$python\nFFMPEG_BIN=$ffmpeg\n";
        file_put_contents(__DIR__ . '/.env', $env);

        // Create projects dir
        $dir = __DIR__ . '/projects';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $success = 'Installation complete! <a href="config.php">Configure API keys →</a>';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install — Video Script Generator</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="center-page">
<div class="install-card">
  <div class="logo-area">
    <span class="logo-icon">🎬</span>
    <h1>Video Script Generator</h1>
    <p class="subtitle">Installation Setup</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
  <?php else: ?>
  <form method="POST">
    <fieldset>
      <legend>MySQL Database</legend>
      <div class="form-row">
        <label>Host</label>
        <input type="text" name="db_host" value="localhost" required>
      </div>
      <div class="form-row">
        <label>User</label>
        <input type="text" name="db_user" value="root" required>
      </div>
      <div class="form-row">
        <label>Password</label>
        <input type="password" name="db_pass" placeholder="(leave blank if none)">
      </div>
      <div class="form-row">
        <label>Database Name</label>
        <input type="text" name="db_name" value="video_script_gen" required>
      </div>
    </fieldset>
    <fieldset>
      <legend>System Binaries</legend>
      <div class="form-row">
        <label>Python binary</label>
        <input type="text" name="python_bin" value="python3">
      </div>
      <div class="form-row">
        <label>FFmpeg binary</label>
        <input type="text" name="ffmpeg_bin" value="ffmpeg">
      </div>
    </fieldset>
    <button type="submit" class="btn btn-primary btn-full">Install Now</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
