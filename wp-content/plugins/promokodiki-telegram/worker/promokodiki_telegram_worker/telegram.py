"""Thin synchronous Telethon adapter."""

import base64
import asyncio
from datetime import timezone
from getpass import getpass
import mimetypes
from urllib.request import Request, urlopen


class TelethonAdapter:
    MAX_MEDIA_BYTES = 8 * 1024 * 1024

    def __init__(self, api_id, api_hash, session_path, phone=None, login_mode="code", client_factory=None, qr_renderer=None, qr_finalizer=None):
        if client_factory is None:
            try:
                from telethon.sync import TelegramClient
            except ImportError as error:
                raise RuntimeError("Install worker requirements before running") from error
            client_factory = TelegramClient
        self.client = client_factory(
            session_path,
            int(api_id),
            api_hash,
            request_retries=0,
            flood_sleep_threshold=0,
            raise_last_call_error=True,
        )
        self.qr_finalizer = qr_finalizer or self._finalize_qr_after_flood
        if login_mode == "qr":
            self._start_qr(qr_renderer or render_qr)
        else:
            self.client.start(phone=phone)

    def _start_qr(self, renderer):
        from telethon.errors import FloodWaitError, RpcCallFailError, ServerError, SessionPasswordNeededError, TimedOutError

        self.client.connect()
        if self.client.is_user_authorized():
            return
        last_error = None
        for attempt in range(3):
            qr_login = self.client.qr_login()
            renderer(qr_login.url)
            try:
                self.client.loop.run_until_complete(qr_login.wait())
            except asyncio.TimeoutError:
                print("QR-код истёк, показываю новый..." if attempt < 2 else "QR-код истёк.")
                continue
            except FloodWaitError as error:
                try:
                    self.qr_finalizer(qr_login, error.seconds)
                except SessionPasswordNeededError:
                    self.client.sign_in(password=getpass("Введите пароль двухэтапной аутентификации Telegram: "))
                if self.client.is_user_authorized():
                    break
                last_error = error
                continue
            except (ValueError, RpcCallFailError, ServerError, TimedOutError) as error:
                last_error = error
                print(
                    f"Telegram не завершил QR-вход ({type(error).__name__}). "
                    + ("Показываю новый QR..." if attempt < 2 else "Попытки исчерпаны.")
                )
                continue
            except SessionPasswordNeededError:
                self.client.sign_in(password=getpass("Введите пароль двухэтапной аутентификации Telegram: "))
            break
        if not self.client.is_user_authorized():
            raise RuntimeError("Telegram session не авторизована. Повторите запуск с --qr-login.") from last_error

    def _finalize_qr_after_flood(self, qr_login, wait_seconds):
        self.client.loop.run_until_complete(self._finalize_qr_after_flood_async(qr_login, wait_seconds))

    async def _finalize_qr_after_flood_async(self, qr_login, wait_seconds):
        from telethon import functions, types
        from telethon.errors import FloodWaitError

        await asyncio.sleep(max(1, int(wait_seconds)) + 1)
        response = None
        for _ in range(5):
            try:
                response = await self.client(qr_login._request)
                break
            except FloodWaitError as error:
                await asyncio.sleep(max(1, error.seconds) + 1)
        if isinstance(response, types.auth.LoginTokenMigrateTo):
            await self.client._switch_dc(response.dc_id)
            response = await self.client(functions.auth.ImportLoginTokenRequest(response.token))
        if not isinstance(response, types.auth.LoginTokenSuccess):
            raise RuntimeError("Telegram не вернул подтверждение QR-авторизации.")
        await self.client._on_login(response.authorization.user)

    def messages(self, channel, limit, min_id, min_date):
        values = self.client.get_messages(channel, limit=limit, min_id=min_id)
        if min_id > 0:
            return values
        return [message for message in values if message.date.astimezone(timezone.utc) >= min_date.astimezone(timezone.utc)]

    def tracked(self, channel, ids):
        if not ids:
            return []
        return [message for message in self.client.get_messages(channel, ids=ids) if message]

    def media(self, message):
        is_photo = bool(getattr(message, "photo", None))
        video = getattr(message, "video", None)
        has_video_thumbnail = bool(video and getattr(video, "thumbs", None))
        if not is_photo and not has_video_thumbnail:
            return None
        source = getattr(message, "photo", None) if is_photo else video
        data = self.client.download_media(source, bytes) if is_photo else self.client.download_media(source, bytes, thumb=-1)
        if not data or len(data) > self.MAX_MEDIA_BYTES:
            return None
        filename = getattr(getattr(message, "file", None), "name", None) or f"telegram-{message.id}.jpg"
        mime_type = "image/jpeg" if has_video_thumbnail and not is_photo else (getattr(getattr(message, "file", None), "mime_type", None) or mimetypes.guess_type(filename)[0] or "image/jpeg")
        return {"filename": filename, "mime_type": mime_type, "data": base64.b64encode(data).decode("ascii")}

    def resolve_url(self, url):
        request = Request(url, method="HEAD", headers={"User-Agent": "PromokodikiTelegramWorker/1.0"})
        with urlopen(request, timeout=10) as response:
            return response.geturl()

    def disconnect(self):
        self.client.disconnect()


def render_qr(url):
    try:
        import qrcode
    except ImportError as error:
        raise RuntimeError("Install worker requirements before QR login") from error
    qr = qrcode.QRCode(border=1)
    qr.add_data(url)
    qr.make(fit=True)
    print("Откройте Telegram → Настройки → Устройства → Подключить устройство и отсканируйте QR-код:")
    qr.print_ascii(tty=False, invert=True)
