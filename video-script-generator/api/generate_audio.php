<?php
/**
 * Generate TTS audio for all narration sections of a project.
 * Uses Google Cloud TTS or ElevenLabs depending on project setting.
 */
require_once '../db.php';
requireMethod('POST');
set_time_limit(300);

$data       = getInput();
$project_id = intval($data['project_id'] ?? 0);
if (!$project_id) jsonResponse(['error' => 'project_id required'], 400);

$project = getProject($project_id);
if (!$project) jsonResponse(['error' => 'Project not found'], 404);

$voice_provider = $project['voice_provider'];
$voice_id       = $project['voice_id'];
$language_code  = $project['language_code'] ?: 'en-US';

$dir            = projectDir($project_id, $project['title']);
$proc_dir       = $dir . '/processed';

$sections = getProjectSections($project_id);
$generated = 0;

foreach ($sections as $sec) {
    $text = trim($sec['narration_text'] ?? '');
    if (!$text) continue;

    $seq      = str_pad($sec['sequence_number'], 3, '0', STR_PAD_LEFT);
    $type_tag = $sec['section_type'];
    $audio_f  = "$proc_dir/{$seq}_{$type_tag}_narration.mp3";

    if (file_exists($audio_f)) {
        // Update DB path
        updateSectionStatus(intval($sec['id']), $sec['status'], ['audio_file' => $audio_f]);
        continue;
    }

    $ok = false;
    if ($voice_provider === 'elevenlabs') {
        $key = getConfig('elevenlabs_api_key');
        $ok  = generateElevenLabs($key, $voice_id, $text, $audio_f);
    } else {
        $key = getConfig('google_tts_api_key');
        $ok  = generateGoogleTTS($key, $voice_id, $language_code, $text, $audio_f);
    }

    if ($ok) {
        updateSectionStatus(intval($sec['id']), $sec['status'], ['audio_file' => $audio_f]);
        $generated++;
    }
}

jsonResponse(['success' => true, 'generated' => $generated]);

// -----------------------------------------------------------------------
// Google Cloud Text-to-Speech
// -----------------------------------------------------------------------
function generateGoogleTTS(string $key, string $voice_name, string $lang, string $text, string $output): bool {
    if (!$key) return false;

    $body = json_encode([
        'input'       => ['text' => $text],
        'voice'       => ['languageCode' => $lang, 'name' => $voice_name],
        'audioConfig' => ['audioEncoding' => 'MP3', 'speakingRate' => 1.0, 'pitch' => 0.0],
    ]);

    $ch = curl_init("https://texttospeech.googleapis.com/v1/text:synthesize?key=$key");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $body,
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) return false;

    $data  = json_decode($response, true);
    $audio = $data['audioContent'] ?? '';
    if (!$audio) return false;

    file_put_contents($output, base64_decode($audio));
    return true;
}

// -----------------------------------------------------------------------
// ElevenLabs TTS
// -----------------------------------------------------------------------
function generateElevenLabs(string $key, string $voice_id, string $text, string $output): bool {
    if (!$key || !$voice_id) return false;

    $body = json_encode([
        'text'          => $text,
        'model_id'      => 'eleven_multilingual_v2',
        'voice_settings' => ['stability' => 0.5, 'similarity_boost' => 0.8],
    ]);

    $ch = curl_init("https://api.elevenlabs.io/v1/text-to-speech/$voice_id");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            "xi-api-key: $key",
            'Content-Type: application/json',
            'Accept: audio/mpeg',
        ],
        CURLOPT_POSTFIELDS => $body,
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) return false;

    file_put_contents($output, $response);
    return filesize($output) > 1000;
}
