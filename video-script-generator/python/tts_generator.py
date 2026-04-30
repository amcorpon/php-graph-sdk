"""
tts_generator.py
Text-to-speech via Google Cloud TTS or ElevenLabs.
"""

import os
import json
import base64
import logging
import requests

log = logging.getLogger(__name__)

GOOGLE_TTS_URL  = 'https://texttospeech.googleapis.com/v1/text:synthesize'
ELEVENLABS_URL  = 'https://api.elevenlabs.io/v1/text-to-speech/{voice_id}'


def google_tts(api_key: str, voice_name: str, language_code: str, text: str, output_path: str) -> bool:
    """Generate MP3 via Google Cloud Text-to-Speech API."""
    if not api_key:
        log.warning("Google TTS API key not set")
        return False

    payload = {
        'input':       {'text': text},
        'voice':       {'languageCode': language_code, 'name': voice_name},
        'audioConfig': {
            'audioEncoding': 'MP3',
            'speakingRate':  1.0,
            'pitch':         0.0,
            'volumeGainDb':  0.0,
        },
    }

    try:
        resp = requests.post(
            f'{GOOGLE_TTS_URL}?key={api_key}',
            json=payload,
            timeout=30,
        )
        resp.raise_for_status()
        audio_content = resp.json().get('audioContent', '')
        if not audio_content:
            log.error("Google TTS returned empty audioContent")
            return False
        with open(output_path, 'wb') as f:
            f.write(base64.b64decode(audio_content))
        log.info(f"Google TTS saved: {output_path}")
        return True
    except Exception as e:
        log.error(f"Google TTS error: {e}")
        return False


def elevenlabs_tts(api_key: str, voice_id: str, text: str, output_path: str,
                   model: str = 'eleven_multilingual_v2') -> bool:
    """Generate MP3 via ElevenLabs API."""
    if not api_key or not voice_id:
        log.warning("ElevenLabs API key or voice_id not set")
        return False

    url     = ELEVENLABS_URL.format(voice_id=voice_id)
    payload = {
        'text':           text,
        'model_id':       model,
        'voice_settings': {'stability': 0.5, 'similarity_boost': 0.8, 'style': 0.2},
    }
    headers = {
        'xi-api-key':   api_key,
        'Content-Type': 'application/json',
        'Accept':       'audio/mpeg',
    }

    try:
        resp = requests.post(url, json=payload, headers=headers, timeout=60)
        resp.raise_for_status()
        with open(output_path, 'wb') as f:
            f.write(resp.content)
        size = os.path.getsize(output_path)
        if size < 1000:
            log.error(f"ElevenLabs output too small ({size} bytes)")
            return False
        log.info(f"ElevenLabs TTS saved: {output_path} ({size} bytes)")
        return True
    except Exception as e:
        log.error(f"ElevenLabs error: {e}")
        return False


def list_elevenlabs_voices(api_key: str) -> list[dict]:
    """Return list of available ElevenLabs voices."""
    if not api_key:
        return []
    try:
        resp = requests.get(
            'https://api.elevenlabs.io/v1/voices',
            headers={'xi-api-key': api_key},
            timeout=10,
        )
        resp.raise_for_status()
        return resp.json().get('voices', [])
    except Exception as e:
        log.error(f"list_elevenlabs_voices error: {e}")
        return []
