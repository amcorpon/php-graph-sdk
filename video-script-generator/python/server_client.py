"""
server_client.py
HTTP client that communicates with the remote PHP server.
All DB operations (read/write) go through this — no direct MySQL access.
"""

import os
import json
import logging
import requests
from pathlib import Path

log = logging.getLogger(__name__)


class ServerClient:
    """
    Thin wrapper around the /api/worker/ PHP endpoints.
    Every request is authenticated with the worker API token.
    """

    def __init__(self, server_url: str, api_token: str):
        self.base    = server_url.rstrip('/')
        self.token   = api_token
        self.session = requests.Session()
        self.session.headers.update({
            'Authorization': f'Bearer {api_token}',
            'User-Agent':    'VideoScriptWorker/1.0',
        })

    # ------------------------------------------------------------------
    # Projects
    # ------------------------------------------------------------------
    def get_pending_projects(self) -> list[dict]:
        """Return list of projects with status='queued'."""
        resp = self._get('/api/worker/pending_projects.php')
        return resp.get('projects', [])

    def get_project(self, project_id: int) -> dict:
        """
        Return full project data + sections + API keys.
        Also marks the project as 'processing' on the server.
        """
        resp = self._get('/api/worker/get_project.php', params={'project_id': project_id})
        return resp  # keys: project, sections, worker_config

    def update_project_status(self, project_id: int, status: str, error: str = '') -> None:
        self._post('/api/worker/update_project.php', {
            'project_id':    project_id,
            'status':        status,
            'error_message': error,
        })
        log.info(f"Project {project_id} → {status}")

    # ------------------------------------------------------------------
    # Sections
    # ------------------------------------------------------------------
    def update_section(self, section_id: int, status: str, **kwargs) -> None:
        payload = {'section_id': section_id, 'status': status, **kwargs}
        self._post('/api/worker/update_section.php', payload)

    def upload_file(self, project_id: int, section_id: int,
                    file_path: str, file_field: str = 'media') -> dict:
        """
        Upload a processed file to the remote server.
        file_field: 'media' (video/image) or 'audio' (mp3)
        Returns the server response with 'url' and 'file_name'.
        """
        path = Path(file_path)
        if not path.exists():
            raise FileNotFoundError(f"File not found: {file_path}")

        log.info(f"Uploading {path.name} ({path.stat().st_size // 1024} KB) → server")

        with open(file_path, 'rb') as f:
            resp = self.session.post(
                self.base + '/api/worker/upload_file.php',
                data={
                    'project_id': project_id,
                    'section_id': section_id,
                    'file_field': file_field,
                },
                files={'file': (path.name, f)},
                timeout=300,
            )

        resp.raise_for_status()
        data = resp.json()
        if not data.get('success'):
            raise RuntimeError(f"Upload failed: {data.get('error','unknown')}")

        log.info(f"Upload OK: {data.get('file_name')} → {data.get('url','')}")
        return data

    def send_log(self, project_id: int, message: str, level: str = 'INFO') -> None:
        """Stream a log line to the server (shows in the web UI log panel)."""
        try:
            self._post('/api/worker/log.php', {
                'project_id': project_id,
                'message':    message,
                'level':      level,
            })
        except Exception:
            pass  # Non-critical

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------
    def _get(self, path: str, params: dict = None) -> dict:
        url  = self.base + path
        resp = self.session.get(url, params=params, timeout=30)
        self._check(resp)
        return resp.json()

    def _post(self, path: str, payload: dict) -> dict:
        url  = self.base + path
        resp = self.session.post(url, json=payload, timeout=30)
        self._check(resp)
        return resp.json()

    @staticmethod
    def _check(resp: requests.Response) -> None:
        if resp.status_code == 401:
            raise PermissionError("Worker token invalid or not configured on server. "
                                  "Go to server Settings → Worker Setup.")
        if resp.status_code == 403:
            raise PermissionError(f"HTTP 403: {resp.text[:200]}")
        if not resp.ok:
            raise RuntimeError(f"HTTP {resp.status_code}: {resp.text[:300]}")


# ------------------------------------------------------------------
# Config loader
# ------------------------------------------------------------------
def load_config(config_path: str = None) -> dict:
    """
    Load worker_config.json. Search order:
      1. --config argument
      2. ./worker_config.json (same dir as script)
      3. ~/.videoscript/worker_config.json
    """
    candidates = []
    if config_path:
        candidates.append(config_path)

    script_dir = Path(__file__).parent
    candidates += [
        str(script_dir / 'worker_config.json'),
        str(Path.home() / '.videoscript' / 'worker_config.json'),
    ]

    for path in candidates:
        if os.path.exists(path):
            with open(path, encoding='utf-8') as f:
                cfg = json.load(f)
            log.info(f"Loaded config from: {path}")
            return cfg

    raise FileNotFoundError(
        "worker_config.json not found.\n"
        "Create it with:\n"
        "{\n"
        '  "server_url":     "https://yourserver.com/video-script-generator",\n'
        '  "api_token":      "your-token-from-config-page",\n'
        '  "local_work_dir": "/tmp/videoscript_work",\n'
        '  "ffmpeg_bin":     "ffmpeg"\n'
        "}\n"
        "You can generate the token on the server at: Settings → Worker Setup"
    )
