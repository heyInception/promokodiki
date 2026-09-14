"""One-shot channel synchronization orchestration."""

from collections import Counter
from datetime import timedelta
import json
import time

from .parser import parse_message


MAX_IMPORT_ITEMS = 20
MAX_IMPORT_BODY_BYTES = 4 * 1024 * 1024


def _payload_size(payload):
    return len(json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8"))


def _split_payload(payload):
    metadata = {key: value for key, value in payload.items() if key != "items"}
    batches = []
    current = []

    for original_item in payload["items"]:
        item = original_item
        if _payload_size({**metadata, "items": [item]}) > MAX_IMPORT_BODY_BYTES and "media" in item:
            item = {key: value for key, value in item.items() if key != "media"}

        candidate = current + [item]
        if current and (len(candidate) > MAX_IMPORT_ITEMS or _payload_size({**metadata, "items": candidate}) > MAX_IMPORT_BODY_BYTES):
            batches.append(current)
            current = [item]
        else:
            current = candidate

    if current:
        batches.append(current)
    return batches or [[]]


def sync_all(wordpress, telegram, now):
    config = wordpress.config()
    totals = {"channels": 0, "imported": 0, "skipped": 0}
    for channel in config.get("channels", []):
        started_at = time.monotonic()
        username = channel["username"]
        last_id = int(channel.get("last_message_id", 0))
        messages = list(telegram.messages(username, int(config.get("initial_limit", 200)), last_id, now - timedelta(days=int(config.get("initial_days", 7)))))
        tracked_ids = [int(value) for value in channel.get("tracked_message_ids", [])]
        tracked_messages = list(telegram.tracked(username, tracked_ids))
        active_tracked = {int(message.id) for message in tracked_messages}
        inactive = [message_id for message_id in tracked_ids if message_id not in active_tracked]
        by_id = {int(message.id): message for message in tracked_messages}
        by_id.update({int(message.id): message for message in messages})

        items = []
        skipped = Counter()
        for message in by_id.values():
            result = parse_message(message, username, now, getattr(telegram, "resolve_url", None))
            if not result.accepted:
                skipped[result.reason] += 1
                if int(message.id) in tracked_ids:
                    inactive.append(int(message.id))
                continue
            item = result.item
            media = telegram.media(message)
            if media:
                item["media"] = media
            items.append(item)

        payload = {
            "channel": username,
            "newest_message_id": max([last_id] + [int(message.id) for message in messages]),
            "inspected_count": len(by_id),
            "skipped": dict(skipped),
            "inactive_message_ids": sorted(set(inactive)),
            "items": items,
        }
        batches = _split_payload(payload)
        imported = 0
        for index, batch in enumerate(batches):
            batch_payload = {"channel": username, "items": batch}
            if index == len(batches) - 1:
                batch_payload.update({key: value for key, value in payload.items() if key not in {"channel", "items"}})
                batch_payload["duration_ms"] = max(0, int((time.monotonic() - started_at) * 1000))
            response = wordpress.import_batch(batch_payload)
            imported += int(response.get("imported", 0))
        totals["channels"] += 1
        totals["imported"] += imported
        totals["skipped"] += sum(skipped.values())
    return totals
