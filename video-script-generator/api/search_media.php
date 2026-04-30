<?php
/**
 * Search Pexels (images + videos) AND YouTube for B-ROLL sections.
 * Results are merged and stored in vsg_sections.media_options (JSON).
 *
 * Sources per section type:
 *   broll_image → Pexels images only
 *   broll_video → Pexels videos + YouTube (if API key configured)
 */
require_once '../db.php';
requireMethod('POST');
set_time_limit(180);

$data       = getInput();
$project_id = intval($data['project_id'] ?? 0);
if (!$project_id) jsonResponse(['error' => 'project_id required'], 400);

$pexels_key  = getConfig('pexels_api_key');
$youtube_key = getConfig('youtube_api_key');

// At least one source must be configured for videos
if (!$pexels_key && !$youtube_key) {
    jsonResponse(['error' => 'Configure at least one API key: Pexels or YouTube Data API'], 400);
}

$db       = getDB();
$sections = getProjectSections($project_id);
$results  = [];

foreach ($sections as $sec) {
    if (!in_array($sec['section_type'], ['broll_image', 'broll_video'])) continue;
    if (in_array($sec['status'], ['confirmed'])) continue; // skip already confirmed

    $terms_raw   = json_decode($sec['search_terms'] ?? '[]', true);
    $primary_q   = is_array($terms_raw) && !empty($terms_raw) ? $terms_raw[0] : ($sec['description'] ?? '');
    $alt_queries = is_array($terms_raw) ? array_slice($terms_raw, 1, 2) : [];
    if (!$primary_q) continue;

    $sec_id  = intval($sec['id']);
    $type    = $sec['section_type'];
    $options = [];

    if ($type === 'broll_image') {
        // Images: Pexels only
        if ($pexels_key) {
            $options = searchPexelsImages($pexels_key, $primary_q, 6);
            // If few results, try alt query
            if (count($options) < 3 && !empty($alt_queries)) {
                $extra = searchPexelsImages($pexels_key, $alt_queries[0], 4);
                $options = array_merge($options, $extra);
            }
        }
    } else {
        // Videos: Pexels + YouTube (merged)
        if ($pexels_key) {
            $pexels_videos = searchPexelsVideos($pexels_key, $primary_q, 4);
            $options       = array_merge($options, $pexels_videos);
        }
        if ($youtube_key) {
            $yt_videos = searchYouTube($youtube_key, $primary_q, 5);
            $options   = array_merge($options, $yt_videos);
            // Try alternative query on YouTube too for more variety
            if (!empty($alt_queries)) {
                $yt_alt  = searchYouTube($youtube_key, $alt_queries[0], 3);
                $options = array_merge($options, $yt_alt);
            }
        }
    }

    updateSectionStatus($sec_id, 'found', ['media_options' => json_encode($options)]);
    $results[$sec_id] = [
        'found'   => count($options),
        'query'   => $primary_q,
        'sources' => array_unique(array_column($options, 'source')),
    ];
}

updateProjectStatus($project_id, 'searching_media');

$sections_updated = getProjectSections($project_id);
jsonResponse(['success' => true, 'results' => $results, 'sections' => $sections_updated]);

// -----------------------------------------------------------------------
// Pexels Image Search
// -----------------------------------------------------------------------
function searchPexelsImages(string $key, string $query, int $count): array {
    $q    = urlencode($query);
    $body = curlGet("https://api.pexels.com/v1/search?query=$q&per_page=$count&orientation=landscape",
                    ["Authorization: $key"]);
    $data = json_decode($body, true);

    $options = [];
    foreach ($data['photos'] ?? [] as $photo) {
        $options[] = [
            'id'       => $photo['id'],
            'type'     => 'image',
            'source'   => 'pexels',
            'thumb'    => $photo['src']['medium']   ?? '',
            'url'      => $photo['src']['large2x']  ?? $photo['src']['large'] ?? '',
            'original' => $photo['src']['original'] ?? '',
            'width'    => $photo['width'],
            'height'   => $photo['height'],
            'title'    => $photo['alt'] ?? '',
            'channel'  => 'Pexels',
            'duration' => 0,
            'link'     => $photo['url'] ?? '',
        ];
    }
    return $options;
}

// -----------------------------------------------------------------------
// Pexels Video Search
// -----------------------------------------------------------------------
function searchPexelsVideos(string $key, string $query, int $count): array {
    $q    = urlencode($query);
    $body = curlGet("https://api.pexels.com/videos/search?query=$q&per_page=$count&orientation=landscape",
                    ["Authorization: $key"]);
    $data = json_decode($body, true);

    $options = [];
    foreach ($data['videos'] ?? [] as $video) {
        $files    = $video['video_files'] ?? [];
        $best_url = '';
        $best_w   = 0;
        foreach ($files as $f) {
            $fw = $f['width'] ?? 0;
            if ($fw >= 1280 && $fw > $best_w) {
                $best_url = $f['link'] ?? '';
                $best_w   = $fw;
            }
        }
        if (!$best_url && !empty($files)) $best_url = $files[0]['link'] ?? '';

        $options[] = [
            'id'       => $video['id'],
            'type'     => 'video',
            'source'   => 'pexels',
            'thumb'    => $video['image'] ?? '',
            'url'      => $best_url,
            'title'    => "Pexels #{$video['id']}",
            'channel'  => 'Pexels',
            'duration' => $video['duration'] ?? 0,
            'width'    => $video['width']    ?? 0,
            'height'   => $video['height']   ?? 0,
            'link'     => $video['url'] ?? '',
        ];
    }
    return $options;
}

// -----------------------------------------------------------------------
// YouTube Data API v3 Search
// Returns video metadata. Download handled by yt-dlp in Python worker.
// -----------------------------------------------------------------------
function searchYouTube(string $key, string $query, int $count): array {
    // Step 1: Search — get video IDs + snippet
    $q       = urlencode($query);
    $search  = curlGet(
        "https://www.googleapis.com/youtube/v3/search"
        . "?part=snippet&q=$q&type=video&maxResults=$count"
        . "&videoEmbeddable=true&key=$key",
        []
    );
    $sdata   = json_decode($search, true);
    if (empty($sdata['items'])) return [];

    $video_ids = [];
    $snippets  = [];
    foreach ($sdata['items'] as $item) {
        $vid_id = $item['id']['videoId'] ?? '';
        if (!$vid_id) continue;
        $video_ids[]        = $vid_id;
        $snippets[$vid_id]  = $item['snippet'];
    }
    if (!$video_ids) return [];

    // Step 2: Fetch durations
    $ids_str   = implode(',', $video_ids);
    $vid_resp  = curlGet(
        "https://www.googleapis.com/youtube/v3/videos"
        . "?part=contentDetails&id=$ids_str&key=$key",
        []
    );
    $vdata     = json_decode($vid_resp, true);
    $durations = [];
    foreach ($vdata['items'] ?? [] as $item) {
        $durations[$item['id']] = parseISO8601($item['contentDetails']['duration'] ?? 'PT0S');
    }

    // Step 3: Build options
    $options = [];
    foreach ($video_ids as $vid_id) {
        $snip = $snippets[$vid_id];
        // Skip live streams
        if (($snip['liveBroadcastContent'] ?? '') === 'live') continue;

        $options[] = [
            'id'       => "yt_$vid_id",
            'type'     => 'video',
            'source'   => 'youtube',
            'thumb'    => "https://img.youtube.com/vi/$vid_id/hqdefault.jpg",
            'url'      => "https://www.youtube.com/watch?v=$vid_id",
            'title'    => $snip['title'] ?? '',
            'channel'  => $snip['channelTitle'] ?? '',
            'duration' => $durations[$vid_id] ?? 0,
            'published'=> substr($snip['publishedAt'] ?? '', 0, 10),
            'link'     => "https://www.youtube.com/watch?v=$vid_id",
        ];
    }
    return $options;
}

// Parse ISO 8601 duration: PT4M12S → 252 seconds
function parseISO8601(string $d): int {
    preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $d, $m);
    return intval($m[1] ?? 0) * 3600 + intval($m[2] ?? 0) * 60 + intval($m[3] ?? 0);
}

// Generic cURL GET helper
function curlGet(string $url, array $headers): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => 'VideoScriptAI/1.0',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body ?: '';
}
