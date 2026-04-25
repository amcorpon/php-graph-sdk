<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn() && !isClientLoggedIn()) jsonError('Não autorizado', 401);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $kb = dbFetchOne($db, 'SELECT * FROM knowledge_base LIMIT 1');
    jsonSuccess(['knowledge' => $kb]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $sourceType = $data['source_type'] ?? 'text';

    $existing = dbFetchOne($db, 'SELECT id FROM knowledge_base LIMIT 1');

    if ($sourceType === 'text') {
        $content = trim($data['content'] ?? '');
        if ($existing) {
            dbExecute($db,
                'UPDATE knowledge_base SET source_type = ?, content = ?, url = NULL, url_content = NULL WHERE id = ?',
                [$sourceType, $content, $existing['id']]
            );
        } else {
            dbInsert($db,
                'INSERT INTO knowledge_base (source_type, content) VALUES (?, ?)',
                [$sourceType, $content]
            );
        }
        jsonSuccess([], 'Base de conhecimento atualizada!');
    }

    if ($sourceType === 'url') {
        $url = trim($data['url'] ?? '');
        if (empty($url)) jsonError('URL não informada');
        if (!filter_var($url, FILTER_VALIDATE_URL)) jsonError('URL inválida');

        // Buscar conteúdo da URL
        $context = stream_context_create(['http' => [
            'timeout' => 15,
            'user_agent' => 'Mozilla/5.0 WhatsApp Bot Crawler'
        ]]);
        $html = @file_get_contents($url, false, $context);
        if ($html === false) jsonError('Não foi possível acessar a URL informada');

        // Extrair texto limpo
        $text = extractTextFromHTML($html);

        if ($existing) {
            dbExecute($db,
                'UPDATE knowledge_base SET source_type = ?, url = ?, url_content = ?, content = NULL, last_url_fetch = NOW() WHERE id = ?',
                [$sourceType, $url, $text, $existing['id']]
            );
        } else {
            dbInsert($db,
                'INSERT INTO knowledge_base (source_type, url, url_content, last_url_fetch) VALUES (?, ?, ?, NOW())',
                [$sourceType, $url, $text]
            );
        }
        jsonSuccess(['preview' => substr($text, 0, 300)], 'Conteúdo da URL importado com sucesso!');
    }
}

function extractTextFromHTML($html) {
    // Remover scripts, estilos, nav, footer
    $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
    $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
    $html = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $html);
    $html = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $html);
    $html = preg_replace('/<header[^>]*>.*?<\/header>/si', '', $html);

    // Converter para texto
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    // Limitar tamanho
    return mb_substr($text, 0, 50000);
}
