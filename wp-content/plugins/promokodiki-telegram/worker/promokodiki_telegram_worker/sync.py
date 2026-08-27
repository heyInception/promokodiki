"""One-shot channel synchronization orchestration."""

from collections import Counter
from datetime import timedelta

from .parser import parse_message


def sync_all(wordpress, telegram, now):
    config = wordpress.config()
    totals = {"channels": 0, "imported": 0, "skipped": 0}
    for channel in config.get("channels", []):
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
        response = wordpress.import_batch(payload)
        totals["channels"] += 1
        totals["imported"] += int(response.get("imported", 0))
        totals["skipped"] += sum(skipped.values())
    return totals
