<?php
/**
 * Search Pexels for images/videos for all pending B-ROLL sections.
 * Stores candidate results in vsg_sections.media_options (JSON).
 */
require_once '../db.php';
requireMethod('POST');
set_time_limit(120);

$data       = getInput();
$project_id = intval($data['project_id'] ?? 0);
if (!$project_id) jsonResponse(['error' => 'project_id required'], 400);

$pexels_key = getConfig('pexels_api_key');
if (!$pexels_key) jsonResponse(['error' => 'Pexels API key not configured'], 400);

$db       = getDB();
$sections = getProjectSections($project_id);
$results  = [];

foreach ($sections as $sec) {
    if (!in_array($sec['section_type'], ['broll_image', 'broll_video'])) continue;
    if ($sec['status'] === 'found' || $sec['status'] === 'confirmed') continue;

    $terms_raw = json_decode($sec['search_terms'] ?? '[]', true);
    $query     = is_array($terms_raw) && !empty($terms_raw) ? $terms_raw[0] : $sec['description'];
    if (!$query) continue;

    $type    = $sec['section_type'];
    $sec_id  = intval($sec['id']);
    $options = [];

    if ($type === 'broll_image') {
        $options = searchPexelsImages($pexels_key, $query, 6);
    } else {
        $options = searchPexelsVideos($pexels_key, $query, 6);
    }

    if (!empty($options)) {
        $json = mysqli_real_escape_string($db, json_encode($options));
        updateSectionStatus($sec_id, 'found', [
            'media_options' => json_encode($options),
        ]);
        $results[$sec_id] = ['found' => count($options), 'query' => $query];
    } else {
        updateSectionStatus($sec_id, 'found', [
            'media_options' => '[]',
            'error_message' => 'No results for: ' . $query,
        ]);
        $results[$sec_id] = ['found' => 0, 'query' => $query];
    }
}

updateProjectStatus($project_id, 'searching_media');

// Return all sections with their options
$sections_updated = getProjectSections($project_id);
jsonResponse(['success' => true, 'results' => $results, 'sections' => $sections_updated]);

// -----------------------------------------------------------------------
// Pexels Image Search
// -----------------------------------------------------------------------
function searchPexelsImages(string $key, string $query, int $count): array {
    $q   = urlencode($query);
    $url = "https://api.pexels.com/v1/search?query=$q&per_page=$count&orientation=landscape";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ["Authorization: $key"],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    $data    = json_decode($body, true);
    $options = [];
    foreach ($data['photos'] ?? [] as $photo) {
        $options[] = [
            'id'       => $photo['id'],
            'type'     => 'image',
            'thumb'    => $photo['src']['medium']   ?? '',
            'url'      => $photo['src']['large2x']  ?? $photo['src']['large'] ?? '',
            'original' => $photo['src']['original'] ?? '',
            'width'    => $photo['width'],
            'height'   => $photo['height'],
            'alt'      => $photo['alt'] ?? '',
            'source'   => 'pexels',
            'link'     => $photo['url'] ?? '',
        ];
    }
    return $options;
}

// -----------------------------------------------------------------------
// Pexels Video Search
// -----------------------------------------------------------------------
function searchPexelsVideos(string $key, string $query, int $count): array {
    $q   = urlencode($query);
    $url = "https://api.pexels.com/videos/search?query=$q&per_page=$count&orientation=landscape";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ["Authorization: $key"],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    $data    = json_decode($body, true);
    $options = [];
    foreach ($data['videos'] ?? [] as $video) {
        // Pick best file (prefer 1080p, fallback to 720p)
        $files    = $video['video_files'] ?? [];
        $best_url = '';
        $best_w   = 0;
        foreach ($files as $f) {
            if (($f['width'] ?? 0) >= 1280 && ($f['width'] ?? 0) > $best_w) {
                $best_url = $f['link'] ?? '';
                $best_w   = $f['width'];
            }
        }
        if (!$best_url && !empty($files)) $best_url = $files[0]['link'] ?? '';

        $options[] = [
            'id'       => $video['id'],
            'type'     => 'video',
            'thumb'    => $video['image'] ?? '',
            'url'      => $best_url,
            'duration' => $video['duration'] ?? 0,
            'width'    => $video['width']    ?? 0,
            'height'   => $video['height']   ?? 0,
            'source'   => 'pexels',
            'link'     => $video['url'] ?? '',
        ];
    }
    return $options;
}
