<?php
require_once '../db.php';
requireMethod('POST');

$data    = getInput();
$allowed = ['claude_api_key','claude_model','gemini_api_key','pexels_api_key',
            'google_tts_api_key','elevenlabs_api_key','python_bin','ffmpeg_bin'];
$saved   = 0;

foreach ($allowed as $key) {
    if (isset($data[$key])) {
        setConfig($key, $data[$key]);
        $saved++;
    }
}

jsonResponse(['success' => true, 'message' => "Saved $saved settings."]);
