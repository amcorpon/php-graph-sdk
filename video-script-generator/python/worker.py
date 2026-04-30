#!/usr/bin/env python3
"""
worker.py — VideoScript AI local worker
Runs on YOUR machine, communicates with the remote PHP server.

Usage:
  # Watch for queued projects automatically (polls every 10s):
  python3 worker.py

  # Process a specific project by ID:
  python3 worker.py --project-id 42

  # Use a custom config file location:
  python3 worker.py --config /path/to/worker_config.json

  # One-shot (process pending then exit):
  python3 worker.py --once
"""

import argparse
import logging
import sys
import time
import traceback
from pathlib import Path

# Add script directory to path
sys.path.insert(0, str(Path(__file__).parent))

from server_client import ServerClient, load_config
from process_project import process_project

logging.basicConfig(
    level=logging.INFO,
    format='[%(asctime)s] %(levelname)s  %(message)s',
    datefmt='%H:%M:%S',
    handlers=[
        logging.StreamHandler(sys.stdout),
    ]
)
log = logging.getLogger(__name__)

POLL_INTERVAL = 10  # seconds between polls


def main():
    parser = argparse.ArgumentParser(description='VideoScript AI — Local Worker')
    parser.add_argument('--config',     help='Path to worker_config.json')
    parser.add_argument('--project-id', type=int, help='Process a specific project ID')
    parser.add_argument('--once',       action='store_true', help='Process pending once then exit')
    parser.add_argument('--verbose',    action='store_true', help='Debug logging')
    args = parser.parse_args()

    if args.verbose:
        logging.getLogger().setLevel(logging.DEBUG)

    # Load config
    try:
        cfg = load_config(args.config)
    except FileNotFoundError as e:
        log.error(str(e))
        sys.exit(1)

    server_url = cfg.get('server_url', '').rstrip('/')
    api_token  = cfg.get('api_token',  '')

    if not server_url or not api_token:
        log.error("worker_config.json must contain 'server_url' and 'api_token'")
        sys.exit(1)

    client = ServerClient(server_url, api_token)
    local_work_dir = cfg.get('local_work_dir', '/tmp/videoscript_work')

    log.info(f"=== VideoScript AI Worker ===")
    log.info(f"Server : {server_url}")
    log.info(f"Work dir: {local_work_dir}")

    # Single project mode
    if args.project_id:
        log.info(f"Processing project {args.project_id} (manual mode)")
        run_project(client, args.project_id, local_work_dir)
        return

    # Poll mode
    log.info(f"Polling for queued projects every {POLL_INTERVAL}s — press Ctrl+C to stop")
    while True:
        try:
            projects = client.get_pending_projects()
            if projects:
                log.info(f"Found {len(projects)} queued project(s)")
                for p in projects:
                    run_project(client, p['id'], local_work_dir)
            else:
                log.debug("No queued projects.")
        except PermissionError as e:
            log.error(f"Auth error: {e}")
            sys.exit(1)
        except Exception as e:
            log.warning(f"Poll error: {e}")

        if args.once:
            break
        time.sleep(POLL_INTERVAL)


def run_project(client: ServerClient, project_id: int, local_work_dir: str):
    log.info(f"--- Starting project {project_id} ---")
    try:
        data           = client.get_project(project_id)
        project        = data['project']
        sections       = data['sections']
        worker_config  = data.get('worker_config', {})

        project_slug   = ''.join(c if c.isalnum() else '_' for c in project['title'].lower())
        work_dir       = f"{local_work_dir}/{project_id}_{project_slug}"

        client.send_log(project_id, f"Worker started — {len(sections)} sections to process")

        process_project(
            client=client,
            project=project,
            sections=sections,
            worker_config=worker_config,
            work_dir=work_dir,
        )

        client.update_project_status(project_id, 'completed')
        client.send_log(project_id, "✅ All sections processed — project complete!")
        log.info(f"--- Project {project_id} COMPLETE ---")

    except Exception as e:
        tb = traceback.format_exc()
        log.error(f"Project {project_id} failed:\n{tb}")
        try:
            client.update_project_status(project_id, 'error', str(e))
            client.send_log(project_id, f"❌ Fatal error: {e}", level='ERROR')
        except Exception:
            pass


if __name__ == '__main__':
    main()
