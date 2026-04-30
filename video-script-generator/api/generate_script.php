<?php
/**
 * Generate a structured video script using Claude or Gemini.
 * Returns and stores JSON sections in vsg_sections.
 */
require_once '../db.php';
requireMethod('POST');
set_time_limit(120);

$data       = getInput();
$project_id = intval($data['project_id'] ?? 0);
if (!$project_id) jsonResponse(['error' => 'project_id required'], 400);

$project = getProject($project_id);
if (!$project) jsonResponse(['error' => 'Project not found'], 404);

$theme        = $project['theme'];
$duration     = intval($project['duration_minutes']);
$total_sec    = $duration * 60;
$ai_provider  = $project['ai_provider'];
$claude_model = $project['claude_model'] ?: 'claude-opus-4-7';
$language     = $project['language_code'] ?: 'en-US';

updateProjectStatus($project_id, 'generating');

// -----------------------------------------------------------------------
// Build the prompt
// -----------------------------------------------------------------------
$lang_note = $language !== 'en-US' ? "Write ALL narration text in the language corresponding to locale: $language." : '';
$prompt = <<<PROMPT
You are an expert video script writer for YouTube and social media content.

Create a COMPLETE, DETAILED video script for a {$duration}-minute video (approximately {$total_sec} seconds) on the topic:
"{$theme}"

{$lang_note}

CRITICAL: Return ONLY a valid JSON object with NO markdown, NO code blocks, NO explanation — pure JSON only.

JSON schema:
{
  "title": "Compelling video title",
  "description": "Short YouTube description (2 sentences)",
  "total_duration_seconds": {$total_sec},
  "sections": [
    ... (array of section objects, see types below)
  ]
}

SECTION TYPES — choose the best type for each moment:

1. TYPE: "narration"  (pure narration, use with a b-roll section when possible)
   Required fields:
   - "sequence": integer
   - "type": "narration"
   - "narration_text": "The exact spoken text — conversational, engaging"
   - "duration_seconds": integer
   - "voice_notes": "tone, emotion, pace (e.g. 'calm and confident')"

2. TYPE: "broll_video"  (stock video footage plays while narration is heard)
   Required fields:
   - "sequence": integer
   - "type": "broll_video"
   - "narration_text": "Spoken while footage plays"
   - "search_terms": ["term1", "term2", "term3"]  (3-5 specific Pexels search terms)
   - "description": "Detailed description of ideal footage"
   - "duration_seconds": integer (10–60)

3. TYPE: "broll_image"  (stock photo shown while narration is heard)
   Required fields:
   - "sequence": integer
   - "type": "broll_image"
   - "narration_text": "Spoken while image shows"
   - "search_terms": ["term1", "term2"]  (2-4 specific Pexels search terms)
   - "description": "Detailed description of ideal image"
   - "duration_seconds": integer (5–15)

4. TYPE: "text_overlay"  (text card / title card / statistic / quote on screen)
   Required fields:
   - "sequence": integer
   - "type": "text_overlay"
   - "overlay_text": "TEXT ON SCREEN (use \\n for line breaks)"
   - "narration_text": "Optional: spoken while text shows (empty string if silent)"
   - "background_color": "#hexcode"
   - "text_color": "#ffffff"
   - "duration_seconds": integer (3–10)

STRUCTURE GUIDELINES:
- ALWAYS start with a "text_overlay" title card (3-5 sec)
- ALWAYS end with a "text_overlay" call-to-action
- Alternate between b-roll video and images for visual variety
- Use text_overlay for statistics, quotes, chapter titles
- Total section durations must sum to approximately {$total_sec} seconds
- Use SPECIFIC search terms that will find real Pexels stock content
- Write narration in a natural, conversational YouTube style
- For broll_video: prefer dynamic search terms like "timelapse", "aerial", "slow motion"
PROMPT;

// -----------------------------------------------------------------------
// Call the selected AI
// -----------------------------------------------------------------------
$script_json = null;
$used_provider = '';

if (in_array($ai_provider, ['claude', 'both'])) {
    $key = getConfig('claude_api_key');
    if (!$key) jsonResponse(['error' => 'Claude API key not configured'], 400);
    $result = callClaude($key, $claude_model, $prompt);
    if (!isset($result['error'])) {
        $script_json   = $result;
        $used_provider = 'claude';
    }
}

if ($script_json === null && in_array($ai_provider, ['gemini', 'both'])) {
    $key = getConfig('gemini_api_key');
    if (!$key) jsonResponse(['error' => 'Gemini API key not configured'], 400);
    $result = callGemini($key, $prompt);
    if (!isset($result['error'])) {
        $script_json   = $result;
        $used_provider = 'gemini';
    } else {
        updateProjectStatus($project_id, 'error', $result['error']);
        jsonResponse(['error' => $result['error']], 500);
    }
}

if (!$script_json) {
    updateProjectStatus($project_id, 'error', 'Failed to generate script from AI');
    jsonResponse(['error' => 'Failed to generate script'], 500);
}

// -----------------------------------------------------------------------
// Persist script + sections
// -----------------------------------------------------------------------
$db       = getDB();
$raw_esc  = mysqli_real_escape_string($db, json_encode($script_json));
$prov_esc = mysqli_real_escape_string($db, $used_provider);

// Delete any previous script
mysqli_query($db, "DELETE FROM vsg_scripts  WHERE project_id=$project_id");
mysqli_query($db, "DELETE FROM vsg_sections WHERE project_id=$project_id");

mysqli_query($db, "INSERT INTO vsg_scripts (project_id,raw_json,ai_provider) VALUES ($project_id,'$raw_esc','$prov_esc')");

// Update project title
if (!empty($script_json['title'])) {
    $new_title = mysqli_real_escape_string($db, substr($script_json['title'], 0, 255));
    mysqli_query($db, "UPDATE vsg_projects SET title='$new_title' WHERE id=$project_id");
}

$sections = $script_json['sections'] ?? [];
foreach ($sections as $s) {
    $seq   = intval($s['sequence']);
    $type  = mysqli_real_escape_string($db, $s['type'] ?? 'narration');
    $nar   = mysqli_real_escape_string($db, $s['narration_text']   ?? '');
    $ovl   = mysqli_real_escape_string($db, $s['overlay_text']     ?? '');
    $bg    = mysqli_real_escape_string($db, $s['background_color'] ?? '#1a1a2e');
    $tc    = mysqli_real_escape_string($db, $s['text_color']       ?? '#ffffff');
    $desc  = mysqli_real_escape_string($db, $s['description']      ?? '');
    $terms = mysqli_real_escape_string($db, json_encode($s['search_terms'] ?? []));
    $dur   = max(3, intval($s['duration_seconds'] ?? 10));
    $vnot  = mysqli_real_escape_string($db, $s['voice_notes']      ?? '');

    mysqli_query($db, "INSERT INTO vsg_sections
        (project_id,sequence_number,section_type,narration_text,overlay_text,
         overlay_bg_color,overlay_text_color,description,search_terms,duration_seconds,voice_notes,status)
        VALUES ($project_id,$seq,'$type','$nar','$ovl','$bg','$tc','$desc','$terms',$dur,'$vnot','pending')");
}

updateProjectStatus($project_id, 'script_ready');

jsonResponse([
    'success'    => true,
    'provider'   => $used_provider,
    'title'      => $script_json['title']       ?? '',
    'description'=> $script_json['description'] ?? '',
    'sections'   => count($sections),
]);

// -----------------------------------------------------------------------
// Helper: call Claude API
// -----------------------------------------------------------------------
function callClaude(string $key, string $model, string $prompt): array {
    $body = json_encode([
        'model'      => $model,
        'max_tokens' => 8192,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: '.$key,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => $body,
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) return ['error' => "Claude HTTP $status: " . substr($response, 0, 200)];

    $data = json_decode($response, true);
    $text = $data['content'][0]['text'] ?? '';
    return parseJsonFromText($text);
}

// -----------------------------------------------------------------------
// Helper: call Gemini API
// -----------------------------------------------------------------------
function callGemini(string $key, string $prompt): array {
    $url  = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=$key";
    $body = json_encode([
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 8192],
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $body,
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) return ['error' => "Gemini HTTP $status: " . substr($response, 0, 200)];

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return parseJsonFromText($text);
}

// -----------------------------------------------------------------------
// Helper: extract JSON from AI text (may be wrapped in markdown)
// -----------------------------------------------------------------------
function parseJsonFromText(string $text): array {
    // Remove markdown code fences
    $text = preg_replace('/```(?:json)?\s*/i', '', $text);
    $text = str_replace('```', '', $text);
    $text = trim($text);

    $parsed = json_decode($text, true);
    if (json_last_error() === JSON_ERROR_NONE) return $parsed;

    // Try to extract JSON object
    if (preg_match('/\{.*\}/s', $text, $m)) {
        $parsed = json_decode($m[0], true);
        if (json_last_error() === JSON_ERROR_NONE) return $parsed;
    }

    return ['error' => 'Could not parse JSON from AI response: ' . substr($text, 0, 300)];
}
