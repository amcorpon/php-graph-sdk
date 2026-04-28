#!/usr/bin/env python3
"""
process_project.py — Main pipeline orchestrator
Usage: python3 process_project.py <config_json_path>

Steps per section:
  broll_image  → download image from Pexels URL → save as 001_broll_image.jpg
  broll_video  → download video → Gemini timestamps → ffmpeg cut → 002_broll_video.mp4
  narration    → Google TTS / ElevenLabs → 003_narration.mp3
  text_overlay → write .txt with content + create thumbnail via Pillow → 004_text_overlay.png
"""

import sys
import os
import json
import logging
import re
import subprocess
import time
import requests
import mysql.connector

logging.basicConfig(
    level=logging.INFO,
    format='[%(asctime)s] %(levelname)s: %(message)s',
    datefmt='%H:%M:%S',
)
log = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
def main():
    if len(sys.argv) < 2:
        log.error("Usage: process_project.py <config_json_path>")
        sys.exit(1)

    cfg_path = sys.argv[1]
    with open(cfg_path, 'r', encoding='utf-8') as f:
        cfg = json.load(f)

    project_id   = cfg['project_id']
    output_dir   = cfg['output_dir']
    processed_dir = os.path.join(output_dir, 'processed')
    raw_dir       = os.path.join(output_dir, 'raw')
    os.makedirs(processed_dir, exist_ok=True)
    os.makedirs(raw_dir,       exist_ok=True)

    log.info(f"=== Starting project {project_id}: {cfg.get('project_title','?')} ===")

    db = connect_db(cfg)

    sections = cfg.get('sections', [])
    total    = len(sections)

    for i, sec in enumerate(sections, 1):
        sec_id  = sec['id']
        seq     = str(sec['sequence_number']).zfill(3)
        stype   = sec['section_type']
        log.info(f"[{i}/{total}] Section {seq} — type={stype} status={sec['status']}")

        try:
            if stype == 'broll_image':
                process_image(sec, seq, processed_dir, raw_dir, db, cfg)
            elif stype == 'broll_video':
                process_video(sec, seq, processed_dir, raw_dir, db, cfg)
            elif stype == 'narration':
                process_narration(sec, seq, processed_dir, db, cfg)
            elif stype == 'text_overlay':
                process_text_overlay(sec, seq, processed_dir, db, cfg)
        except Exception as e:
            log.error(f"Error on section {sec_id}: {e}")
            update_section(db, sec_id, 'error', error_message=str(e))

    # Write full script JSON
    script_path = os.path.join(output_dir, 'script.json')
    with open(script_path, 'w', encoding='utf-8') as f:
        json.dump({'project_id': project_id, 'sections': sections}, f, ensure_ascii=False, indent=2)

    update_project_status(db, project_id, 'completed')
    log.info("=== Pipeline complete ===")
    db.close()


# ---------------------------------------------------------------------------
# B-ROLL Image
# ---------------------------------------------------------------------------
def process_image(sec, seq, processed_dir, raw_dir, db, cfg):
    sec_id    = sec['id']
    media_url = sec.get('media_url', '')
    if not media_url:
        log.warning(f"  No media URL for section {sec_id}, skipping")
        return

    ext       = 'jpg'
    raw_file  = os.path.join(raw_dir, f"{seq}_broll_image_raw.{ext}")
    final_file = os.path.join(processed_dir, f"{seq}_broll_image.{ext}")

    update_section(db, sec_id, 'downloading')
    download_file(media_url, raw_file)
    log.info(f"  Downloaded image: {raw_file}")

    # Copy to processed (could apply Pillow processing here)
    import shutil
    shutil.copy2(raw_file, final_file)

    fname = os.path.basename(final_file)
    update_section(db, sec_id, 'done', file_path=final_file, file_name=fname)
    log.info(f"  Saved: {final_file}")

    # Generate narration audio if needed
    maybe_generate_narration(sec, seq, processed_dir, db, cfg, tag='broll_image')


# ---------------------------------------------------------------------------
# B-ROLL Video
# ---------------------------------------------------------------------------
def process_video(sec, seq, processed_dir, raw_dir, db, cfg):
    sec_id    = sec['id']
    media_url = sec.get('media_url', '')
    if not media_url:
        log.warning(f"  No media URL for section {sec_id}, skipping")
        return

    duration_sec = int(sec.get('duration_seconds', 10))
    raw_file     = os.path.join(raw_dir, f"{seq}_broll_video_raw.mp4")
    final_file   = os.path.join(processed_dir, f"{seq}_broll_video.mp4")

    update_section(db, sec_id, 'downloading')

    # Download
    download_file(media_url, raw_file)
    log.info(f"  Downloaded video: {raw_file}")

    # Determine cut range
    cut_start = sec.get('cut_start') or ''
    cut_end   = sec.get('cut_end')   or ''

    # If no explicit cut times, try Gemini analysis
    if not cut_start and cfg.get('gemini_api_key'):
        description = sec.get('description') or sec.get('narration_text', '')
        log.info(f"  Asking Gemini for best timestamp in video...")
        try:
            from gemini_analyzer import analyze_video_timestamps
            start_t, end_t = analyze_video_timestamps(
                cfg['gemini_api_key'], raw_file, description, duration_sec
            )
            if start_t is not None:
                cut_start = format_time(start_t)
                cut_end   = format_time(end_t)
                update_section(db, sec_id, 'downloading', cut_start=cut_start, cut_end=cut_end)
                log.info(f"  Gemini timestamps: {cut_start} → {cut_end}")
        except Exception as e:
            log.warning(f"  Gemini analysis failed: {e} — using start of video")

    # Cut video with ffmpeg
    update_section(db, sec_id, 'processing')
    ffmpeg = cfg.get('ffmpeg_bin', 'ffmpeg')
    cut_video(ffmpeg, raw_file, final_file, cut_start or '00:00:00', cut_end or '', duration_sec)
    log.info(f"  Cut video saved: {final_file}")

    fname = os.path.basename(final_file)
    update_section(db, sec_id, 'done', file_path=final_file, file_name=fname,
                   cut_start=cut_start, cut_end=cut_end)

    maybe_generate_narration(sec, seq, processed_dir, db, cfg, tag='broll_video')


# ---------------------------------------------------------------------------
# Narration (pure audio section)
# ---------------------------------------------------------------------------
def process_narration(sec, seq, processed_dir, db, cfg):
    sec_id = sec['id']
    text   = (sec.get('narration_text') or '').strip()
    if not text:
        update_section(db, sec_id, 'done')
        return

    audio_file = os.path.join(processed_dir, f"{seq}_narration.mp3")
    if os.path.exists(audio_file):
        update_section(db, sec_id, 'done', audio_file=audio_file, file_path=audio_file)
        return

    update_section(db, sec_id, 'processing')
    ok = generate_tts(cfg, text, audio_file)
    if ok:
        update_section(db, sec_id, 'done', audio_file=audio_file, file_path=audio_file,
                       file_name=os.path.basename(audio_file))
    else:
        update_section(db, sec_id, 'error', error_message='TTS generation failed')


# ---------------------------------------------------------------------------
# Text Overlay
# ---------------------------------------------------------------------------
def process_text_overlay(sec, seq, processed_dir, db, cfg):
    sec_id  = sec['id']
    text    = (sec.get('overlay_text') or '').strip()
    nartext = (sec.get('narration_text') or '').strip()
    bg_col  = sec.get('overlay_bg_color')  or '#1a1a2e'
    txt_col = sec.get('overlay_text_color') or '#ffffff'
    dur     = int(sec.get('duration_seconds') or 5)

    # Write .txt metadata
    meta = {
        'overlay_text':  text,
        'narration_text': nartext,
        'background_color': bg_col,
        'text_color': txt_col,
        'duration_seconds': dur,
    }
    txt_file = os.path.join(processed_dir, f"{seq}_text_overlay.txt")
    with open(txt_file, 'w', encoding='utf-8') as f:
        json.dump(meta, f, ensure_ascii=False, indent=2)

    # Generate a PNG thumbnail via Pillow
    png_file = os.path.join(processed_dir, f"{seq}_text_overlay.png")
    try:
        create_text_overlay_image(text, bg_col, txt_col, png_file)
        final_file = png_file
        fname      = os.path.basename(png_file)
    except Exception as e:
        log.warning(f"  Pillow image generation failed: {e} — saving .txt only")
        final_file = txt_file
        fname      = os.path.basename(txt_file)

    update_section(db, sec_id, 'done', file_path=final_file, file_name=fname)
    log.info(f"  Text overlay saved: {final_file}")

    # Generate narration audio if section has spoken text
    if nartext:
        audio_file = os.path.join(processed_dir, f"{seq}_text_overlay_narration.mp3")
        if not os.path.exists(audio_file):
            generate_tts(cfg, nartext, audio_file)
        if os.path.exists(audio_file):
            update_section(db, sec_id, 'done', audio_file=audio_file)


# ---------------------------------------------------------------------------
# TTS helper (shared)
# ---------------------------------------------------------------------------
def maybe_generate_narration(sec, seq, processed_dir, db, cfg, tag='broll'):
    text = (sec.get('narration_text') or '').strip()
    if not text:
        return
    audio_file = os.path.join(processed_dir, f"{seq}_{tag}_narration.mp3")
    if os.path.exists(audio_file):
        update_section(db, int(sec['id']), sec.get('status','done'), audio_file=audio_file)
        return
    ok = generate_tts(cfg, text, audio_file)
    if ok:
        update_section(db, int(sec['id']), sec.get('status','done'), audio_file=audio_file)


def generate_tts(cfg, text, output_path):
    from tts_generator import google_tts, elevenlabs_tts
    provider = cfg.get('voice_provider', 'google')
    voice_id = cfg.get('voice_id', 'en-US-Neural2-F')
    lang     = cfg.get('language_code', 'en-US')

    if provider == 'elevenlabs':
        key = cfg.get('elevenlabs_api_key', '')
        return elevenlabs_tts(key, voice_id, text, output_path)
    else:
        key = cfg.get('google_tts_api_key', '')
        return google_tts(key, voice_id, lang, text, output_path)


# ---------------------------------------------------------------------------
# Create text overlay image with Pillow
# ---------------------------------------------------------------------------
def create_text_overlay_image(text, bg_color, text_color, output_path, size=(1920, 1080)):
    from PIL import Image, ImageDraw, ImageFont

    img  = Image.new('RGB', size, color=hex_to_rgb(bg_color))
    draw = ImageDraw.Draw(img)

    # Try to load a font, fall back to default
    font_size = 80
    try:
        font = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', font_size)
    except OSError:
        font = ImageFont.load_default()

    lines   = text.replace('\\n', '\n').split('\n')
    line_h  = font_size + 20
    total_h = line_h * len(lines)
    y_start = (size[1] - total_h) // 2

    for j, line in enumerate(lines):
        bbox = draw.textbbox((0, 0), line, font=font)
        w    = bbox[2] - bbox[0]
        x    = (size[0] - w) // 2
        y    = y_start + j * line_h
        draw.text((x, y), line, font=font, fill=hex_to_rgb(text_color))

    img.save(output_path, 'PNG')


def hex_to_rgb(hex_color):
    h = hex_color.lstrip('#')
    if len(h) == 3:
        h = ''.join(c*2 for c in h)
    return tuple(int(h[i:i+2], 16) for i in (0, 2, 4))


# ---------------------------------------------------------------------------
# FFmpeg video cutting
# ---------------------------------------------------------------------------
def cut_video(ffmpeg_bin, input_file, output_file, start_time, end_time, duration_sec):
    cmd = [ffmpeg_bin, '-y', '-i', input_file]

    if start_time:
        cmd += ['-ss', start_time]

    if end_time:
        cmd += ['-to', end_time]
    else:
        cmd += ['-t', str(duration_sec)]

    cmd += [
        '-c:v', 'libx264',
        '-c:a', 'aac',
        '-preset', 'fast',
        '-crf', '23',
        '-movflags', '+faststart',
        output_file,
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=300)
    if result.returncode != 0:
        raise RuntimeError(f"ffmpeg failed: {result.stderr[-500:]}")


def format_time(seconds):
    h = int(seconds // 3600)
    m = int((seconds % 3600) // 60)
    s = seconds % 60
    return f"{h:02d}:{m:02d}:{s:06.3f}"


# ---------------------------------------------------------------------------
# File downloader with retry
# ---------------------------------------------------------------------------
def download_file(url, dest, retries=3):
    for attempt in range(retries):
        try:
            resp = requests.get(url, stream=True, timeout=60,
                                headers={'User-Agent': 'VideoScriptGen/1.0'})
            resp.raise_for_status()
            with open(dest, 'wb') as f:
                for chunk in resp.iter_content(chunk_size=65536):
                    f.write(chunk)
            return
        except Exception as e:
            if attempt < retries - 1:
                time.sleep(2 ** attempt)
            else:
                raise RuntimeError(f"Failed to download {url}: {e}")


# ---------------------------------------------------------------------------
# DB helpers
# ---------------------------------------------------------------------------
def connect_db(cfg):
    import re as re_module
    env_file = os.path.join(os.path.dirname(__file__), '..', '.env')
    creds = {'host': 'localhost', 'user': 'root', 'password': '', 'database': 'video_script_gen'}
    if os.path.exists(env_file):
        for line in open(env_file):
            line = line.strip()
            if not line or line.startswith('#'): continue
            k, _, v = line.partition('=')
            m = {'DB_HOST': 'host', 'DB_USER': 'user', 'DB_PASS': 'password', 'DB_NAME': 'database'}
            if k in m:
                creds[m[k]] = v
    return mysql.connector.connect(**creds)


def update_section(db, sec_id, status, **kwargs):
    cursor = db.cursor()
    sets   = ['status=%s', 'updated_at=NOW()' if False else '']
    vals   = [status]
    col_map = {
        'file_path': 'file_path', 'file_name': 'file_name',
        'audio_file': 'audio_file', 'cut_start': 'cut_start',
        'cut_end': 'cut_end', 'error_message': 'error_message',
    }
    for k, col in col_map.items():
        if k in kwargs:
            sets.append(f'{col}=%s')
            vals.append(kwargs[k])

    sql = f"UPDATE vsg_sections SET {', '.join(filter(None, sets))} WHERE id=%s"
    vals.append(sec_id)
    cursor.execute(sql, vals)
    db.commit()
    cursor.close()


def update_project_status(db, project_id, status):
    cursor = db.cursor()
    cursor.execute("UPDATE vsg_projects SET status=%s, updated_at=NOW() WHERE id=%s", (status, project_id))
    db.commit()
    cursor.close()


if __name__ == '__main__':
    main()
