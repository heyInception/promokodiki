"""Cron entry point for one Telegram synchronization pass."""

from datetime import datetime, timezone
import argparse
import os
from pathlib import Path

from promokodiki_telegram_worker.client import WordPressClient
from promokodiki_telegram_worker.sync import sync_all
from promokodiki_telegram_worker.telegram import TelethonAdapter


def load_env(path):
    if not path.exists():
        return
    for line in path.read_text(encoding="utf-8-sig").splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            key, value = line.split("=", 1)
            os.environ.setdefault(key.strip(), value.strip())


def required(name):
    value = os.environ.get(name, "").strip()
    if not value:
        raise SystemExit(f"Missing required environment variable: {name}")
    return value


def parse_arguments(argv=None):
    parser = argparse.ArgumentParser(description="Import high-confidence Telegram promocodes into WordPress")
    parser.add_argument("--qr-login", action="store_true", help="Authorize the Telegram session by QR code")
    parser.add_argument("--env-file", default=os.environ.get("PROMOKODIKI_ENV_FILE", ""), help="Read secrets from an external env file")
    return parser.parse_args(argv)


def main():
    arguments = parse_arguments()
    env_file = Path(arguments.env_file) if arguments.env_file else Path(__file__).with_name(".env")
    load_env(env_file)
    telegram = TelethonAdapter(
        required("TELEGRAM_API_ID"),
        required("TELEGRAM_API_HASH"),
        required("TELEGRAM_SESSION_PATH"),
        os.environ.get("TELEGRAM_PHONE"),
        login_mode="qr" if arguments.qr_login else "code",
    )
    try:
        result = sync_all(WordPressClient(required("WORDPRESS_URL"), required("WORDPRESS_SECRET")), telegram, datetime.now(timezone.utc))
        print(f"channels={result['channels']} imported={result['imported']} skipped={result['skipped']}")
    finally:
        telegram.disconnect()


if __name__ == "__main__":
    main()
