<?php
/**
 * Database connection and helpers for Video Script Generator
 */

define('VSG_ROOT', __DIR__);
define('VSG_PROJECTS_DIR', VSG_ROOT . '/projects');

// Load .env config if present, otherwise use defaults
function getDBCredentials(): array {
    $env_file = VSG_ROOT . '/.env';
    $cfg = [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'name' => 'video_script_gen',
    ];
    if (file_exists($env_file)) {
        foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            $map = ['DB_HOST'=>'host','DB_USER'=>'user','DB_PASS'=>'pass','DB_NAME'=>'name'];
            if (isset($map[trim($k)])) $cfg[$map[trim($k)]] = trim($v);
        }
    }
    return $cfg;
}

function getDB(): mysqli {
    static $conn = null;
    if ($conn !== null) return $conn;

    $c = getDBCredentials();
    $conn = mysqli_connect($c['host'], $c['user'], $c['pass'], $c['name']);
    if (!$conn) {
        http_response_code(500);
        die(json_encode(['error' => 'DB connection failed: ' . mysqli_connect_error()]));
    }
    mysqli_set_charset($conn, 'utf8mb4');
    return $conn;
}

function getConfig(string $key, string $default = ''): string {
    $db  = getDB();
    $key = mysqli_real_escape_string($db, $key);
    $res = mysqli_query($db, "SELECT config_value FROM vsg_config WHERE config_key='$key' LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) return (string)$row['config_value'];
    return $default;
}

function setConfig(string $key, string $value): void {
    $db  = getDB();
    $k   = mysqli_real_escape_string($db, $key);
    $v   = mysqli_real_escape_string($db, $value);
    mysqli_query($db, "INSERT INTO vsg_config (config_key,config_value) VALUES('$k','$v')
                        ON DUPLICATE KEY UPDATE config_value='$v'");
}

function getAllConfig(): array {
    $db  = getDB();
    $res = mysqli_query($db, "SELECT config_key, config_value FROM vsg_config");
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) $out[$row['config_key']] = $row['config_value'];
    return $out;
}

function updateProjectStatus(int $id, string $status, string $error = ''): void {
    $db  = getDB();
    $s   = mysqli_real_escape_string($db, $status);
    $e   = mysqli_real_escape_string($db, $error);
    mysqli_query($db, "UPDATE vsg_projects SET status='$s', error_message='$e', updated_at=NOW() WHERE id=$id");
}

function updateSectionStatus(int $id, string $status, array $extra = []): void {
    $db  = getDB();
    $s   = mysqli_real_escape_string($db, $status);
    $sets = ["status='$s'"];
    foreach ($extra as $col => $val) {
        $allowed = ['file_path','file_name','audio_file','media_url','media_thumb','media_pexels_id',
                    'cut_start','cut_end','error_message','media_options','selected_option','media_source'];
        if (!in_array($col, $allowed)) continue;
        $v = mysqli_real_escape_string($db, (string)$val);
        $sets[] = "$col='$v'";
    }
    $sql = "UPDATE vsg_sections SET " . implode(', ', $sets) . " WHERE id=$id";
    mysqli_query($db, $sql);
}

function getProject(int $id): ?array {
    $db  = getDB();
    $res = mysqli_query($db, "SELECT * FROM vsg_projects WHERE id=$id LIMIT 1");
    return $res ? mysqli_fetch_assoc($res) : null;
}

function getProjectSections(int $project_id): array {
    $db  = getDB();
    $res = mysqli_query($db, "SELECT * FROM vsg_sections WHERE project_id=$project_id ORDER BY sequence_number ASC");
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) $out[] = $row;
    return $out;
}

function projectDir(int $project_id, string $title = ''): string {
    $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($title));
    $dir  = VSG_PROJECTS_DIR . "/{$project_id}" . ($slug ? "_$slug" : '');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    foreach (['raw','processed'] as $sub) {
        if (!is_dir("$dir/$sub")) mkdir("$dir/$sub", 0755, true);
    }
    return $dir;
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireMethod(string ...$methods): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods)) {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
}

function getInput(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}
