import unittest
from datetime import datetime, timezone
from types import SimpleNamespace

from promokodiki_telegram_worker.sync import sync_all


class FakeWordPress:
    def __init__(self): self.payloads = []
    def config(self):
        return {"initial_limit": 200, "initial_days": 7, "channels": [{"username": "tranzhiraru", "last_message_id": 0, "tracked_message_ids": [5]}]}
    def import_batch(self, payload):
        self.payloads.append(payload)
        return {"imported": len(payload["items"])}


class FakeTelegram:
    def messages(self, channel, limit, min_id, min_date):
        return [SimpleNamespace(id=10, text="Промокод SAVE15 https://market.yandex.ru/product", date=datetime.now(timezone.utc), edit_date=None, views=10, forward=None, reply_to=None)]
    def tracked(self, channel, ids): return []
    def media(self, message): return None


class SyncTests(unittest.TestCase):
    def test_initial_scan_and_inactive_revalidation_are_reported(self):
        wordpress = FakeWordPress()
        result = sync_all(wordpress, FakeTelegram(), datetime.now(timezone.utc))
        self.assertEqual(1, result["imported"])
        payload = wordpress.payloads[0]
        self.assertEqual(1, payload["inspected_count"])
        self.assertEqual([5], payload["inactive_message_ids"])
        self.assertEqual(10, payload["newest_message_id"])


if __name__ == "__main__":
    unittest.main()
