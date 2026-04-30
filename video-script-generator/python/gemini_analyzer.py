"""
gemini_analyzer.py
Uses Gemini 1.5 Pro to analyze a local video file and return the best
timestamp range for a given description and target duration.
"""

import os
import json
import re
import logging
import time
import google.generativeai as genai

log = logging.getLogger(__name__)


def analyze_video_timestamps(api_key: str, video_path: str, description: str,
                              target_duration: int) -> tuple[float | None, float | None]:
    """
    Upload a video to Gemini and ask for the best clip timestamps.
    Returns (start_seconds, end_seconds) or (None, None) on failure.
    """
    if not api_key:
        return None, None
    if not os.path.exists(video_path):
        log.warning(f"Video file not found: {video_path}")
        return None, None

    genai.configure(api_key=api_key)

    log.info(f"Uploading video to Gemini: {video_path}")
    video_file = genai.upload_file(video_path)

    # Wait for processing
    max_wait = 120
    waited   = 0
    while video_file.state.name == 'PROCESSING':
        time.sleep(3)
        waited += 3
        video_file = genai.get_file(video_file.name)
        if waited >= max_wait:
            log.warning("Gemini video processing timeout")
            return None, None

    if video_file.state.name != 'ACTIVE':
        log.warning(f"Gemini file state: {video_file.state.name}")
        return None, None

    prompt = f"""Analyze this video and find the BEST {target_duration}-second clip that shows:
"{description}"

Return ONLY a JSON object with this exact format (no explanation, no markdown):
{{
  "start_seconds": 5.0,
  "end_seconds": 20.0,
  "reason": "brief explanation"
}}

Rules:
- start_seconds and end_seconds must be valid timestamps within the video duration
- The clip must be exactly {target_duration} seconds (end - start = {target_duration})
- Choose the most visually compelling and relevant segment
- If the whole video matches, return 0.0 as start_seconds
"""

    model    = genai.GenerativeModel('gemini-1.5-pro')
    response = model.generate_content([video_file, prompt])
    text     = response.text.strip()
    log.info(f"Gemini response: {text[:200]}")

    # Parse JSON
    text = re.sub(r'```(?:json)?', '', text).replace('```', '').strip()
    try:
        data  = json.loads(text)
        start = float(data['start_seconds'])
        end   = float(data['end_seconds'])
        # Validate
        if end <= start:
            end = start + target_duration
        return start, end
    except Exception as e:
        log.warning(f"Could not parse Gemini timestamp response: {e}")
        return None, None


def analyze_video_for_script(api_key: str, video_url: str, topic: str) -> dict:
    """
    Given a YouTube or web video, let Gemini describe what's in it
    and suggest which segment would work best for a given topic.
    Returns {'summary': ..., 'best_segment': ..., 'start': ..., 'end': ...}
    """
    if not api_key:
        return {}

    genai.configure(api_key=api_key)
    model = genai.GenerativeModel('gemini-1.5-pro')

    prompt = f"""You are a video editor assistant.
For a video about "{topic}", analyze the following video URL and return a JSON:
{{
  "summary": "Brief description of what's in the video",
  "relevance_score": 8,
  "best_segment_description": "What the best segment shows",
  "recommended_start_seconds": 10,
  "recommended_end_seconds": 40
}}

Video URL: {video_url}

Return ONLY the JSON object, no markdown or explanation.
"""
    try:
        response = model.generate_content(prompt)
        text     = response.text.strip()
        text     = re.sub(r'```(?:json)?', '', text).replace('```', '').strip()
        return json.loads(text)
    except Exception as e:
        log.warning(f"analyze_video_for_script failed: {e}")
        return {}
