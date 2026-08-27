import hashlib
import hmac
import json
import unittest
from unittest.mock import patch

from promokodiki_telegram_worker.client import WordPressClient


class FakeResponse:
    def __enter__(self): return self
    def __exit__(self, *args): return None
    def read(self): return b'{"ok":true}'


class ClientTests(unittest.TestCase):
    @patch("promokodiki_telegram_worker.client.time.time", return_value=1700000000)
    @patch("promokodiki_telegram_worker.client.secrets.token_urlsafe", return_value="fixed-nonce")
    @patch("promokodiki_telegram_worker.client.urlopen", return_value=FakeResponse())
    def test_exact_hmac_headers_and_json_body(self, opener, nonce, clock):
        client = WordPressClient("https://site.example", "secret")
        client.request("POST", "/promokodiki-telegram/v1/import", {"channel": "tranzhiraru"})
        request = opener.call_args.args[0]
        body = json.dumps({"channel": "tranzhiraru"}, ensure_ascii=False, separators=(",", ":")).encode()
        canonical = b"POST\n/promokodiki-telegram/v1/import\n1700000000\nfixed-nonce\n" + body
        expected = hmac.new(b"secret", canonical, hashlib.sha256).hexdigest()
        self.assertEqual(expected, request.get_header("X-promokodiki-signature"))
        self.assertEqual(body, request.data)


if __name__ == "__main__":
    unittest.main()
