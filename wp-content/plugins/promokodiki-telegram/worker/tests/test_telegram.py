import asyncio
import unittest
from types import SimpleNamespace

from telethon.errors import FloodWaitError

from promokodiki_telegram_worker.telegram import TelethonAdapter


class FakeQrLogin:
    url = "tg://login?token=test-token"

    def __init__(self, client, should_fail=False):
        self.client = client
        self.should_fail = should_fail
        self.waited = False

    async def wait(self, timeout=None):
        self.waited = timeout is None
        if self.should_fail:
            raise ValueError("temporary Telegram RPC failure")
        self.client.authorized = True


class FakeClient:
    def __init__(self, failures=0):
        self.connected = False
        self.authorized = False
        self.failures = failures
        self.qr = None
        self.loop = asyncio.new_event_loop()

    def connect(self):
        self.connected = True

    def is_user_authorized(self):
        return self.authorized

    def qr_login(self):
        self.qr = FakeQrLogin(self, self.failures > 0)
        self.failures = max(0, self.failures - 1)
        return self.qr


class TelegramLoginTests(unittest.TestCase):
    def test_qr_login_renders_token_and_waits_for_scan(self):
        client = FakeClient()
        rendered = []
        options = {}

        def client_factory(*args, **kwargs):
            options.update(kwargs)
            return client

        adapter = TelethonAdapter(
            12345,
            "hash",
            "session",
            login_mode="qr",
            client_factory=client_factory,
            qr_renderer=rendered.append,
        )
        self.assertTrue(client.connected)
        self.assertEqual(["tg://login?token=test-token"], rendered)
        self.assertTrue(client.qr.waited)
        self.assertIs(adapter.client, client)
        self.assertEqual(0, options["request_retries"])
        self.assertEqual(0, options["flood_sleep_threshold"])

    def test_qr_login_retries_transient_final_rpc_failure(self):
        client = FakeClient(failures=1)
        rendered = []
        TelethonAdapter(
            12345,
            "hash",
            "session",
            login_mode="qr",
            client_factory=lambda *args, **kwargs: client,
            qr_renderer=rendered.append,
        )
        self.assertEqual(2, len(rendered))
        self.assertTrue(client.authorized)

    def test_qr_login_finalizes_same_token_after_flood_wait(self):
        client = FakeClient()
        finalized = []

        async def flood_wait(timeout=None):
            raise FloodWaitError(request=None, capture=3)

        client.qr = FakeQrLogin(client)
        client.qr.wait = flood_wait
        client.qr_login = lambda: client.qr
        def finalizer(qr_login, seconds):
            finalized.append((qr_login, seconds))
            client.authorized = True

        TelethonAdapter(
            12345,
            "hash",
            "session",
            login_mode="qr",
            client_factory=lambda *args, **kwargs: client,
            qr_renderer=lambda url: None,
            qr_finalizer=finalizer,
        )
        self.assertEqual([(client.qr, 3)], finalized)

    def test_media_downloads_the_resolved_photo_object(self):
        photo = object()
        downloaded = []
        adapter = object.__new__(TelethonAdapter)
        adapter.client = SimpleNamespace(download_media=lambda media, file, **kwargs: downloaded.append(media) or b"jpeg")
        message = SimpleNamespace(
            id=10,
            photo=photo,
            video=None,
            file=SimpleNamespace(name=None, mime_type="image/jpeg"),
        )

        media = adapter.media(message)

        self.assertEqual([photo], downloaded)
        self.assertEqual("image/jpeg", media["mime_type"])


if __name__ == "__main__":
    unittest.main()
