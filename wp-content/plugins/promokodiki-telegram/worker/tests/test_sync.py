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


class LargeMediaTelegram:
    def messages(self, channel, limit, min_id, min_date):
        return [
            SimpleNamespace(id=message_id, text=f"Промокод SAVE{message_id} https://market.yandex.ru/product/{message_id}", date=datetime.now(timezone.utc), edit_date=None, views=10, forward=None, reply_to=None)
            for message_id in range(10, 13)
        ]

    def tracked(self, channel, ids): return []

    def media(self, message):
        return {"filename": f"{message.id}.jpg", "mime_type": "image/jpeg", "data": "x" * (2 * 1024 * 1024)}


class OversizedMediaTelegram(FakeTelegram):
    def media(self, message):
        return {"filename": "large.jpg", "mime_type": "image/jpeg", "data": "x" * (5 * 1024 * 1024)}


class SyncTests(unittest.TestCase):
    def test_initial_scan_and_inactive_revalidation_are_reported(self):
        wordpress = FakeWordPress()
        result = sync_all(wordpress, FakeTelegram(), datetime.now(timezone.utc))
        self.assertEqual(1, result["imported"])
        payload = wordpress.payloads[0]
        self.assertEqual(1, payload["inspected_count"])
        self.assertEqual([5], payload["inactive_message_ids"])
        self.assertEqual(10, payload["newest_message_id"])

    def test_large_media_import_is_split_into_bounded_requests(self):
        wordpress = FakeWordPress()
        result = sync_all(wordpress, LargeMediaTelegram(), datetime.now(timezone.utc))

        self.assertEqual(3, result["imported"])
        self.assertGreater(len(wordpress.payloads), 1)
        self.assertTrue(all(len(payload["items"]) == 1 for payload in wordpress.payloads))
        self.assertTrue(all("newest_message_id" not in payload for payload in wordpress.payloads[:-1]))
        self.assertEqual(12, wordpress.payloads[-1]["newest_message_id"])
        self.assertEqual([5], wordpress.payloads[-1]["inactive_message_ids"])

    def test_oversized_single_media_is_omitted_from_import(self):
        wordpress = FakeWordPress()
        result = sync_all(wordpress, OversizedMediaTelegram(), datetime.now(timezone.utc))

        self.assertEqual(1, result["imported"])
        self.assertNotIn("media", wordpress.payloads[0]["items"][0])


if __name__ == "__main__":
    unittest.main()
