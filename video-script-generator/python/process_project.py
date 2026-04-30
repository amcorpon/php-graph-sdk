"""
process_project.py
Core processing pipeline — runs locally, talks to server via ServerClient.
No direct MySQL access. All I/O goes through the server HTTP API.
"""

import os
import shutil
import logging
import subprocess
import time
from pathlib import Path
import requests

log = logging.getLogger(__name__)


def process_project(client, project: dict, sections: list, worker_config: dict, work_dir: str):
    """
    Process all sections of a project:
      broll_image  → download image → upload to server
      broll_video  → download video → Gemini timestamps → ffmpeg cut → upload
      narration    → Google TTS / ElevenLabs → upload MP3
      text_overlay → Pillow PNG + optional TTS → upload
    """
    project_id = int(project['id'])
    ffmpeg_bin = worker_config.get('ffmpeg_bin', 'ffmpeg')

    Path(work_dir).mkdir(parents=True, exist_ok=True)
    raw_dir = os.path.join(work_dir, 'raw')
    out_dir = os.path.join(work_dir, 'processed')
    Path(raw_dir).mkdir(exist_ok=True)
    Path(out_dir).mkdir(exist_ok=True)

    total = len(sections)
    for i, sec in enumerate(sections, 1):
        sec_id  = int(sec['id'])
        seq     = str(sec['sequence_number']).zfill(3)
        stype   = sec['section_type']
        status  = sec['status']

        log.info(f"[{i}/{total}] #{seq} {stype} (status={status})")
        client.send_log(project_id, f"[{i}/{total}] Processing {seq}_{stype}...")

        try:
            if stype == 'broll_image':
                _process_image(client, project_id, sec, seq, raw_dir, out_dir)

            elif stype == 'broll_video':
                _process_video(client, project_id, sec, seq, raw_dir, out_dir,
                               worker_config, ffmpeg_bin)

            elif stype == 'narration':
                _process_narration(client, project_id, sec, seq, out_dir, worker_config)

            elif stype == 'text_overlay':
                _process_text_overlay(client, project_id, sec, seq, out_dir, worker_config)

        except Exception as e:
            log.error(f"  Section {sec_id} error: {e}")
            client.update_section(sec_id, 'error', error_message=str(e))
            client.send_log(project_id, f"❌ Section {seq} failed: {e}", level='ERROR')


# ---------------------------------------------------------------------------
# B-ROLL Image
# ---------------------------------------------------------------------------
def _process_image(client, project_id, sec, seq, raw_dir, out_dir):
    sec_id    = int(sec['id'])
    media_url = sec.get('media_url', '')
    if not media_url:
        log.warning(f"  No media_url for section {sec_id}")
        return

    ext        = 'jpg'
    raw_file   = os.path.join(raw_dir,  f"{seq}_broll_image_raw.{ext}")
    final_file = os.path.join(out_dir,  f"{seq}_broll_image.{ext}")

    client.update_section(sec_id, 'downloading')
    _download(media_url, raw_file)
    shutil.copy2(raw_file, final_file)

    result = client.upload_file(project_id, sec_id, final_file, file_field='media')
    client.update_section(sec_id, 'done', file_name=os.path.basename(final_file))
    log.info(f"  Image done: {os.path.basename(final_file)}")

    # Narration audio for this section
    _maybe_narration(client, project_id, sec, sec_id, seq, out_dir, None, 'broll_image')


# ---------------------------------------------------------------------------
# B-ROLL Video
# ---------------------------------------------------------------------------
def _process_video(client, project_id, sec, seq, raw_dir, out_dir, worker_config, ffmpeg_bin):
    sec_id       = int(sec['id'])
    media_url    = sec.get('media_url', '')
    if not media_url:
        log.warning(f"  No media_url for section {sec_id}")
        return

    duration_sec = int(sec.get('duration_seconds', 10))
    raw_file     = os.path.join(raw_dir, f"{seq}_broll_video_raw.mp4")
    final_file   = os.path.join(out_dir, f"{seq}_broll_video.mp4")

    is_yt = _is_youtube(media_url)
    source_label = 'YouTube' if is_yt else 'Pexels'

    client.update_section(sec_id, 'downloading')
    log.info(f"  Downloading from {source_label}: {media_url}")
    _download(media_url, raw_file)
    log.info(f"  Downloaded: {os.path.getsize(raw_file) // 1024} KB → {raw_file}")

    cut_start = sec.get('cut_start') or ''
    cut_end   = sec.get('cut_end')   or ''

    # Ask Gemini for best timestamps if available
    gemini_key = worker_config.get('gemini_api_key', '')
    if not cut_start and gemini_key:
        description = sec.get('description') or sec.get('narration_text', '')
        log.info(f"  Asking Gemini for timestamps...")
        try:
            from gemini_analyzer import analyze_video_timestamps
            start_t, end_t = analyze_video_timestamps(
                gemini_key, raw_file, description, duration_sec
            )
            if start_t is not None:
                cut_start = _fmt_time(start_t)
                cut_end   = _fmt_time(end_t)
                client.update_section(sec_id, 'downloading',
                                      cut_start=cut_start, cut_end=cut_end)
                log.info(f"  Gemini: {cut_start} → {cut_end}")
        except Exception as e:
            log.warning(f"  Gemini failed ({e}) — using start of video")

    # Cut with ffmpeg
    client.update_section(sec_id, 'processing')
    _cut_video(ffmpeg_bin, raw_file, final_file,
               cut_start or '00:00:00', cut_end or '', duration_sec)
    log.info(f"  Cut video: {final_file}")

    client.upload_file(project_id, sec_id, final_file, file_field='media')
    client.update_section(sec_id, 'done',
                          file_name=os.path.basename(final_file),
                          cut_start=cut_start, cut_end=cut_end)

    _maybe_narration(client, project_id, sec, sec_id, seq, out_dir, worker_config, 'broll_video')


# ---------------------------------------------------------------------------
# Narration (pure audio)
# ---------------------------------------------------------------------------
def _process_narration(client, project_id, sec, seq, out_dir, worker_config):
    sec_id     = int(sec['id'])
    text       = (sec.get('narration_text') or '').strip()
    if not text:
        client.update_section(sec_id, 'done')
        return

    audio_file = os.path.join(out_dir, f"{seq}_narration.mp3")
    client.update_section(sec_id, 'processing')

    ok = _generate_tts(worker_config, text, audio_file,
                       project=sec.get('__project__', {}))
    if ok:
        client.upload_file(project_id, sec_id, audio_file, file_field='audio')
        client.update_section(sec_id, 'done', file_name=os.path.basename(audio_file))
    else:
        client.update_section(sec_id, 'error', error_message='TTS generation failed')


# ---------------------------------------------------------------------------
# Text Overlay
# ---------------------------------------------------------------------------
def _process_text_overlay(client, project_id, sec, seq, out_dir, worker_config):
    sec_id  = int(sec['id'])
    text    = (sec.get('overlay_text')    or '').strip()
    nartext = (sec.get('narration_text')  or '').strip()
    bg      = sec.get('overlay_bg_color')  or '#1a1a2e'
    tc      = sec.get('overlay_text_color') or '#ffffff'
    dur     = int(sec.get('duration_seconds') or 5)

    # Generate PNG with Pillow
    png_file = os.path.join(out_dir, f"{seq}_text_overlay.png")
    try:
        _make_overlay_image(text, bg, tc, png_file)
        client.upload_file(project_id, sec_id, png_file, file_field='media')
        client.update_section(sec_id, 'done', file_name=os.path.basename(png_file))
    except Exception as e:
        log.warning(f"  Pillow overlay failed: {e}")
        # Fallback: txt file with metadata
        import json as _json
        txt_file = os.path.join(out_dir, f"{seq}_text_overlay.txt")
        with open(txt_file, 'w', encoding='utf-8') as f:
            _json.dump({'overlay_text': text, 'bg_color': bg, 'text_color': tc,
                        'narration_text': nartext, 'duration': dur}, f, indent=2)
        client.upload_file(project_id, sec_id, txt_file, file_field='media')
        client.update_section(sec_id, 'done', file_name=os.path.basename(txt_file))

    # TTS for overlay narration
    if nartext:
        audio_file = os.path.join(out_dir, f"{seq}_text_overlay_narration.mp3")
        if _generate_tts(worker_config, nartext, audio_file):
            client.upload_file(project_id, sec_id, audio_file, file_field='audio')
            client.update_section(sec_id, 'done',
                                  audio_file=os.path.basename(audio_file))


# ---------------------------------------------------------------------------
# Shared helpers
# ---------------------------------------------------------------------------
def _maybe_narration(client, project_id, sec, sec_id, seq, out_dir,
                     worker_config, tag):
    text = (sec.get('narration_text') or '').strip()
    if not text or not worker_config:
        return
    audio_file = os.path.join(out_dir, f"{seq}_{tag}_narration.mp3")
    if _generate_tts(worker_config, text, audio_file):
        client.upload_file(project_id, sec_id, audio_file, file_field='audio')
        client.update_section(sec_id, sec.get('status', 'done'),
                              audio_file=os.path.basename(audio_file))


def _generate_tts(worker_config, text: str, output_path: str, project: dict = None) -> bool:
    from tts_generator import google_tts, elevenlabs_tts

    voice_provider = worker_config.get('voice_provider', 'google')
    voice_id       = worker_config.get('voice_id',       'en-US-Neural2-F')
    language_code  = worker_config.get('language_code',  'en-US')

    if voice_provider == 'elevenlabs':
        return elevenlabs_tts(
            worker_config.get('elevenlabs_api_key', ''), voice_id, text, output_path
        )
    return google_tts(
        worker_config.get('google_tts_api_key', ''), voice_id, language_code, text, output_path
    )


def _make_overlay_image(text: str, bg: str, fg: str, output: str, size=(1920, 1080)):
    from PIL import Image, ImageDraw, ImageFont

    img  = Image.new('RGB', size, _hex_rgb(bg))
    draw = ImageDraw.Draw(img)

    font_size = 80
    try:
        font = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', font_size)
    except OSError:
        try:
            font = ImageFont.truetype('/System/Library/Fonts/Helvetica.ttc', font_size)
        except OSError:
            font = ImageFont.load_default()

    lines   = text.replace('\\n', '\n').split('\n')
    line_h  = font_size + 24
    total_h = line_h * len(lines)
    y_start = (size[1] - total_h) // 2

    for j, line in enumerate(lines):
        bbox = draw.textbbox((0, 0), line, font=font)
        w    = bbox[2] - bbox[0]
        x    = (size[0] - w) // 2
        y    = y_start + j * line_h
        draw.text((x, y), line, font=font, fill=_hex_rgb(fg))

    img.save(output, 'PNG')


def _hex_rgb(h: str) -> tuple:
    h = h.lstrip('#')
    if len(h) == 3:
        h = ''.join(c * 2 for c in h)
    return tuple(int(h[i:i+2], 16) for i in (0, 2, 4))


def _cut_video(ffmpeg: str, inp: str, out: str, start: str, end: str, dur: int):
    cmd = [ffmpeg, '-y']
    if start and start != '00:00:00':
        cmd += ['-ss', start]
    cmd += ['-i', inp]
    if end:
        cmd += ['-to', end]
    elif dur > 0:
        cmd += ['-t', str(dur)]
    cmd += [
        '-vf',   'scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,fps=30',
        '-c:v',  'libx264', '-preset', 'fast', '-crf', '23',
        '-c:a',  'aac', '-b:a', '192k',
        '-movflags', '+faststart', out,
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=300)
    if result.returncode != 0:
        raise RuntimeError(f"ffmpeg error: {result.stderr[-400:]}")


def _is_youtube(url: str) -> bool:
    return 'youtube.com/watch' in url or 'youtu.be/' in url


def _download(url: str, dest: str, retries: int = 3):
    """Route to the correct downloader based on URL."""
    if _is_youtube(url):
        _download_youtube(url, dest)
    else:
        _download_http(url, dest, retries)


def _download_http(url: str, dest: str, retries: int = 3):
    """Direct HTTP download for Pexels and other direct-link sources."""
    for attempt in range(retries):
        try:
            resp = requests.get(url, stream=True, timeout=60,
                                headers={'User-Agent': 'VideoScriptWorker/1.0'})
            resp.raise_for_status()
            with open(dest, 'wb') as f:
                for chunk in resp.iter_content(65536):
                    f.write(chunk)
            return
        except Exception as e:
            if attempt < retries - 1:
                time.sleep(2 ** attempt)
            else:
                raise RuntimeError(f"HTTP download failed ({url}): {e}")


def _download_youtube(url: str, dest: str):
    """
    Download a YouTube video with yt-dlp.
    Tries up to 1080p, re-encodes to mp4 so ffmpeg can process it later.
    dest should end in .mp4
    """
    import yt_dlp

    dest_base = dest[:-4] if dest.endswith('.mp4') else dest  # strip .mp4 for yt-dlp template

    ydl_opts = {
        # Best video ≤1080p + best audio, merged into mp4
        'format': (
            'bestvideo[height<=1080][ext=mp4]+bestaudio[ext=m4a]'
            '/bestvideo[height<=1080]+bestaudio'
            '/best[height<=1080]'
            '/best'
        ),
        'outtmpl':              dest_base + '.%(ext)s',
        'merge_output_format':  'mp4',
        'quiet':                True,
        'no_warnings':          True,
        'noprogress':           True,
        # Throttle to avoid rate-limits; retries on transient errors
        'retries':              5,
        'fragment_retries':     5,
        'http_chunk_size':      10 * 1024 * 1024,  # 10 MB chunks
        # Cookies / auth can be added here if needed
        # 'cookiesfrombrowser': ('chrome',),
    }

    log.info(f"  yt-dlp downloading: {url}")
    with yt_dlp.YoutubeDL(ydl_opts) as ydl:
        info = ydl.extract_info(url, download=True)
        log.info(f"  yt-dlp done: {info.get('title','?')} [{info.get('duration',0)}s]")

    # yt-dlp may produce dest_base.mp4 or dest_base.mkv etc.
    # Normalise to the expected dest path.
    for ext in ['mp4', 'mkv', 'webm', 'mov']:
        candidate = f"{dest_base}.{ext}"
        if os.path.exists(candidate):
            if candidate != dest:
                os.rename(candidate, dest)
            return

    raise RuntimeError(f"yt-dlp finished but output file not found near: {dest_base}")


def _fmt_time(seconds: float) -> str:
    h = int(seconds // 3600)
    m = int((seconds % 3600) // 60)
    s = seconds % 60
    return f"{h:02d}:{m:02d}:{s:06.3f}"
