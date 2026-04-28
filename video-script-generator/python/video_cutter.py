"""
video_cutter.py
Standalone ffmpeg wrapper for cutting/processing video files.
Can be run independently:
  python3 video_cutter.py input.mp4 output.mp4 --start 00:00:05 --end 00:00:25
"""

import argparse
import subprocess
import sys
import os
import logging

log = logging.getLogger(__name__)


def cut_video(
    input_file: str,
    output_file: str,
    start_time: str = '00:00:00',
    end_time: str   = '',
    duration_sec: int = 0,
    ffmpeg_bin: str = 'ffmpeg',
    scale: str = '1920:1080',
    fps: int   = 30,
) -> bool:
    """
    Cut and re-encode a video segment.

    Args:
        input_file:   Path to input video
        output_file:  Path for output video
        start_time:   Start timestamp (HH:MM:SS or HH:MM:SS.mmm)
        end_time:     End timestamp (empty to use duration_sec)
        duration_sec: Duration in seconds (used if end_time is empty)
        ffmpeg_bin:   Path to ffmpeg binary
        scale:        Output resolution (WxH)
        fps:          Output frame rate
    """
    if not os.path.exists(input_file):
        log.error(f"Input file not found: {input_file}")
        return False

    os.makedirs(os.path.dirname(os.path.abspath(output_file)), exist_ok=True)

    cmd = [ffmpeg_bin, '-y']

    # Seek before input for speed (keyframe-accurate at input)
    if start_time and start_time != '00:00:00':
        cmd += ['-ss', start_time]

    cmd += ['-i', input_file]

    if end_time:
        cmd += ['-to', end_time]
    elif duration_sec > 0:
        cmd += ['-t', str(duration_sec)]

    cmd += [
        '-vf',    f'scale={scale}:force_original_aspect_ratio=decrease,pad={scale}:(ow-iw)/2:(oh-ih)/2,fps={fps}',
        '-c:v',   'libx264',
        '-preset','fast',
        '-crf',   '23',
        '-c:a',   'aac',
        '-b:a',   '192k',
        '-movflags', '+faststart',
        output_file,
    ]

    log.info(f"Running: {' '.join(cmd)}")
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=600)

    if result.returncode != 0:
        log.error(f"ffmpeg error:\n{result.stderr[-1000:]}")
        return False

    log.info(f"Video cut complete: {output_file}")
    return True


def get_video_duration(input_file: str, ffprobe_bin: str = 'ffprobe') -> float:
    """Return video duration in seconds using ffprobe."""
    cmd = [
        ffprobe_bin, '-v', 'quiet', '-print_format', 'json',
        '-show_streams', input_file,
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
    if result.returncode != 0:
        return 0.0
    import json
    data = json.loads(result.stdout)
    for stream in data.get('streams', []):
        if stream.get('codec_type') == 'video':
            return float(stream.get('duration', 0))
    return 0.0


def create_image_video(image_path: str, output_path: str, duration: int,
                       scale: str = '1920:1080', fps: int = 30,
                       ffmpeg_bin: str = 'ffmpeg') -> bool:
    """Convert a static image to a video of given duration (with Ken Burns effect)."""
    cmd = [
        ffmpeg_bin, '-y',
        '-loop', '1',
        '-i', image_path,
        '-vf', (
            f"scale={scale}:force_original_aspect_ratio=increase,"
            f"crop={scale},"
            f"zoompan=z='min(zoom+0.0008,1.3)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'"
            f":d={duration * fps}:s={scale},fps={fps}"
        ),
        '-t', str(duration),
        '-c:v', 'libx264',
        '-preset', 'fast',
        '-crf', '23',
        '-movflags', '+faststart',
        '-pix_fmt', 'yuv420p',
        output_path,
    ]
    log.info(f"Creating image video: {output_path}")
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
    if result.returncode != 0:
        log.error(f"ffmpeg error:\n{result.stderr[-500:]}")
        return False
    return True


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------
if __name__ == '__main__':
    logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

    parser = argparse.ArgumentParser(description='Cut a video with ffmpeg')
    parser.add_argument('input',  help='Input video file')
    parser.add_argument('output', help='Output video file')
    parser.add_argument('--start',    default='00:00:00', help='Start time (HH:MM:SS)')
    parser.add_argument('--end',      default='',         help='End time (HH:MM:SS)')
    parser.add_argument('--duration', type=int, default=0,help='Duration in seconds')
    parser.add_argument('--ffmpeg',   default='ffmpeg',   help='Path to ffmpeg')
    parser.add_argument('--scale',    default='1920:1080')
    args = parser.parse_args()

    ok = cut_video(args.input, args.output, args.start, args.end,
                   args.duration, args.ffmpeg, args.scale)
    sys.exit(0 if ok else 1)
