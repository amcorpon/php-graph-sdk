# VideoScript AI — Video Script Generator

AI-powered system that generates complete video scripts with B-ROLL media, voiceover audio, and all assets organized for direct import into your video editor.

## Stack

| Layer | Tech |
|-------|------|
| Frontend | HTML + CSS + Vanilla JS |
| Backend | PHP 8+ + mysqli |
| Database | MySQL 8+ |
| Processing | Python 3.10+ |
| Video cutting | FFmpeg |
| B-ROLL search | Pexels API |
| Script AI | Claude (Anthropic) / Gemini (Google) |
| Video analysis | Gemini 1.5 Pro |
| TTS | Google Cloud TTS / ElevenLabs |

---

## Installation

### 1. Requirements

```bash
# PHP
php >= 8.1 with extensions: mysqli, curl, zip

# Python
pip install -r python/requirements.txt

# System
ffmpeg  # apt install ffmpeg / brew install ffmpeg
```

### 2. Setup database

```bash
mysql -u root -p < database/schema.sql
```

Or visit `install.php` in your browser for a guided setup.

### 3. Configure API keys

Visit `config.php` and enter your keys:

| Key | Where to get it |
|-----|-----------------|
| **Claude** | [console.anthropic.com](https://console.anthropic.com) |
| **Gemini** | [aistudio.google.com](https://aistudio.google.com) |
| **Pexels** | [pexels.com/api](https://www.pexels.com/api/) — Free |
| **Google TTS** | [console.cloud.google.com](https://console.cloud.google.com) → Cloud Text-to-Speech API |
| **ElevenLabs** | [elevenlabs.io](https://elevenlabs.io) |

---

## Workflow

```
1. Create Project  →  Set theme + duration + AI + voice
2. Generate Script →  Claude/Gemini creates full structured JSON script
3. Search Media    →  Pexels API finds B-ROLL images & videos
4. Select Media    →  Review and pick the best option for each section
5. Process         →  Python downloads, cuts videos, generates TTS audio
6. Download ZIP    →  All files numbered in order, ready for your editor
```

## Output file naming

All processed files are saved in `projects/<id>_<title>/processed/` with sequence numbers:

```
001_text_overlay.png        ← Title card
001_text_overlay.txt        ← Overlay text metadata
002_broll_video.mp4         ← Cut B-ROLL video (Gemini-analyzed timestamps)
002_broll_video_narration.mp3
003_broll_image.jpg         ← Stock photo
003_broll_image_narration.mp3
004_narration.mp3           ← Pure voiceover
005_text_overlay.png        ← CTA card
script.json                 ← Full script data
```

Import the `processed/` folder into your video editor — files are already in the correct sequence order.

---

## How Gemini video analysis works

When a B-ROLL video section needs to be cut:

1. Python uploads the video to Gemini Files API
2. Sends prompt: *"Find the best 15-second clip showing 'mountain aerial view'"*
3. Gemini returns `{"start_seconds": 5.2, "end_seconds": 20.2, "reason": "..."}`
4. FFmpeg cuts the video at those exact timestamps

This way every video clip is precisely selected by AI, not random.

---

## Python requirements

```
requests>=2.31.0
mysql-connector-python>=8.3.0
google-generativeai>=0.7.0
yt-dlp>=2024.1.0
Pillow>=10.0.0
```

Install: `pip install -r python/requirements.txt`
