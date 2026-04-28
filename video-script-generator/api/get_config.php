<?php
require_once '../db.php';

$cfg = getAllConfig();
// Mask secret keys — return only whether they're set
$secrets = ['claude_api_key','gemini_api_key','pexels_api_key','google_tts_api_key','elevenlabs_api_key'];
foreach ($secrets as $k) {
    if (!empty($cfg[$k])) $cfg[$k] = '••••••••••••••••';
}

jsonResponse($cfg);
