"""Authenticated WordPress REST client."""

import hashlib
import hmac
import json
import secrets
import time
from urllib.request import Request, urlopen


class WordPressClient:
    def __init__(self, site_url: str, secret: str, timeout: int = 30):
        self.site_url = site_url.rstrip("/")
        self.secret = secret
        self.timeout = timeout

    def request(self, method: str, route: str, payload=None):
        body = b"" if payload is None else json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
        timestamp = str(int(time.time()))
        nonce = secrets.token_urlsafe(24)
        canonical = f"{method.upper()}\n{route}\n{timestamp}\n{nonce}\n".encode("utf-8") + body
        signature = hmac.new(self.secret.encode("utf-8"), canonical, hashlib.sha256).hexdigest()
        request = Request(
            self.site_url + "/wp-json" + route,
            data=body if method.upper() != "GET" else None,
            method=method.upper(),
            headers={
                "Content-Type": "application/json",
                "X-Promokodiki-Timestamp": timestamp,
                "X-Promokodiki-Nonce": nonce,
                "X-Promokodiki-Signature": signature,
            },
        )
        with urlopen(request, timeout=self.timeout) as response:
            return json.loads(response.read().decode("utf-8"))

    def config(self):
        return self.request("GET", "/promokodiki-telegram/v1/config")

    def import_batch(self, payload):
        return self.request("POST", "/promokodiki-telegram/v1/import", payload)
