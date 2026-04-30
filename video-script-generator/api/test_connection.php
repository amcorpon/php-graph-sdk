<?php
require_once '../db.php';

$provider = $_GET['provider'] ?? '';

switch ($provider) {
    case 'claude':
        $key = getConfig('claude_api_key');
        if (!$key) { jsonResponse(['success'=>false,'error'=>'API key not configured']); }
        $ch  = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: '.$key,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 10,
                'messages'   => [['role'=>'user','content'=>'Hi']],
            ]),
        ]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ok = $status === 200;
        jsonResponse(['success'=>$ok, 'error'=>$ok ? '' : "HTTP $status: " . substr($body,0,120)]);
        break;

    case 'gemini':
        $key = getConfig('gemini_api_key');
        if (!$key) { jsonResponse(['success'=>false,'error'=>'API key not configured']); }
        $url = "https://generativelanguage.googleapis.com/v1beta/models?key=$key";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ok = $status === 200;
        jsonResponse(['success'=>$ok, 'error'=>$ok ? '' : "HTTP $status"]);
        break;

    case 'pexels':
        $key = getConfig('pexels_api_key');
        if (!$key) { jsonResponse(['success'=>false,'error'=>'API key not configured']); }
        $ch  = curl_init('https://api.pexels.com/v1/search?query=nature&per_page=1');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: $key"],
        ]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ok = $status === 200;
        jsonResponse(['success'=>$ok, 'error'=>$ok ? '' : "HTTP $status"]);
        break;

    case 'youtube':
        $key = getConfig('youtube_api_key');
        if (!$key) { jsonResponse(['success'=>false,'error'=>'API key not configured']); }
        $ch  = curl_init("https://www.googleapis.com/youtube/v3/search?part=snippet&q=test&maxResults=1&type=video&key=$key");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data   = json_decode($body, true);
        $ok     = $status === 200 && isset($data['items']);
        $err    = '';
        if (!$ok) {
            $err = $data['error']['message'] ?? "HTTP $status";
        }
        jsonResponse(['success'=>$ok, 'error'=>$err]);
        break;

    default:
        jsonResponse(['success'=>false,'error'=>'Unknown provider']);
}
