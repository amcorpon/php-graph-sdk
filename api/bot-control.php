<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
requireAdmin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'status':
        $status = dbFetchOne($db, 'SELECT * FROM bot_status LIMIT 1');
        $pingAge = null;
        if ($status && $status['last_ping']) {
            $pingAge = (time() - strtotime($status['last_ping']));
            if ($pingAge > 60) {
                $status['is_connected'] = 0;
                $status['status_message'] = 'Sem resposta (reiniciando?)';
            }
        }
        jsonSuccess(['status' => $status]);
        break;

    case 'start':
        $botDir = realpath(__DIR__ . '/../bot');
        if (!$botDir) jsonError('Diretório do bot não encontrado');
        $logFile = realpath(__DIR__ . '/../logs') ?: '/tmp';
        exec("cd $botDir && pm2 start pm2.config.js 2>&1", $output, $code);
        if ($code !== 0) {
            // pm2 não encontrado, tenta node direto
            exec("cd $botDir && nohup node index.js > $logFile/bot-out.log 2>&1 & echo \$!", $output2);
            $pid = trim($output2[0] ?? '');
            jsonSuccess(['pid' => $pid, 'message' => 'Bot iniciado (modo direto)']);
        }
        jsonSuccess(['output' => implode("\n", $output), 'message' => 'Bot iniciado via PM2']);
        break;

    case 'stop':
        exec('pm2 stop whatsapp-bot 2>&1', $output, $code);
        dbExecute($db, "UPDATE bot_status SET is_connected = 0, status_message = 'Parado manualmente' WHERE id = 1");
        jsonSuccess(['output' => implode("\n", $output), 'message' => 'Bot parado']);
        break;

    case 'restart':
        exec('pm2 restart whatsapp-bot 2>&1', $output, $code);
        jsonSuccess(['output' => implode("\n", $output), 'message' => 'Bot reiniciado']);
        break;

    case 'logs':
        $logFile = __DIR__ . '/../logs/bot-out.log';
        if (!file_exists($logFile)) {
            exec('pm2 logs whatsapp-bot --lines 50 --nostream 2>&1', $output);
            jsonSuccess(['logs' => implode("\n", $output)]);
        } else {
            $lines = array_slice(file($logFile), -100);
            jsonSuccess(['logs' => implode('', $lines)]);
        }
        break;

    case 'reset_session':
        $authDir = __DIR__ . '/../bot_auth';
        if (is_dir($authDir)) {
            array_map('unlink', glob("$authDir/*"));
        }
        exec('pm2 restart whatsapp-bot 2>&1', $output);
        dbExecute($db, "UPDATE bot_status SET is_connected = 0, pairing_code = NULL, status_message = 'Sessão resetada' WHERE id = 1");
        logSystem($db, 'warning', 'bot_control', 'Sessão do bot resetada');
        jsonSuccess(['message' => 'Sessão resetada. O bot irá solicitar novo código de emparelhamento.']);
        break;

    case 'pairing_code':
        $status = dbFetchOne($db, 'SELECT pairing_code FROM bot_status LIMIT 1');
        jsonSuccess(['pairing_code' => $status['pairing_code'] ?? null]);
        break;

    default:
        jsonError('Ação inválida');
}
