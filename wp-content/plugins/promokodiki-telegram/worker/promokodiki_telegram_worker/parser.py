"""Pure high-confidence Telegram promocode parser."""

from dataclasses import dataclass
from datetime import datetime, time, timedelta, timezone
import re
from typing import Any, Optional
from urllib.parse import urlsplit, urlunsplit


CODE_PATTERN = re.compile(
    r"(?:промо\s*[-–—]?\s*код|promo\s*[-–—]?\s*code|(?:цена\s+)?с\s+промо|промо)"
    r"\s*[:\-–—]?\s*[`'\"«»]*([A-Za-zА-Яа-яЁё0-9_-]{4,32})",
    re.IGNORECASE,
)
URL_PATTERN = re.compile(r"https?://[^\s<>\]\[\"']+", re.IGNORECASE)
DISCOUNT_PATTERN = re.compile(r"(?<!\d)(\d{1,3})\s*%")
DATE_PATTERN = re.compile(r"(?:до|по)\s*(\d{1,2})[./](\d{1,2})(?:[./](\d{2,4}))?", re.IGNORECASE)


@dataclass(frozen=True)
class ParseResult:
    accepted: bool
    reason: str = ""
    item: Optional[dict[str, Any]] = None


def parse_message(message: Any, channel: str, now: datetime, url_resolver=None) -> ParseResult:
    text_value = str(getattr(message, "text", "") or "").strip()
    if getattr(message, "forward", None) is not None or getattr(message, "fwd_from", None) is not None:
        return ParseResult(False, "forwarded")
    if getattr(message, "reply_to", None) is not None or getattr(message, "reply_to_msg_id", None) is not None:
        return ParseResult(False, "reply")
    codes = list(dict.fromkeys(match.upper() for match in CODE_PATTERN.findall(text_value) if re.search(r"[A-Za-z0-9]", match)))
    if not codes:
        return ParseResult(False, "missing_code")
    if len(codes) != 1:
        return ParseResult(False, "multiple_codes")

    urls = [url.rstrip(".,;:!?)") for url in URL_PATTERN.findall(text_value)]
    urls = [url for url in urls if not _telegram_host(url)]
    if not urls:
        return ParseResult(False, "missing_link")

    published = _aware(getattr(message, "date", None) or now)
    edited = getattr(message, "edit_date", None)
    discount_match = DISCOUNT_PATTERN.search(text_value)
    discount_value = int(discount_match.group(1)) if discount_match else 0
    code = codes[0]
    destination_url = ""
    for candidate_url in urls:
        resolved_url = candidate_url
        if url_resolver:
            try:
                resolved = str(url_resolver(candidate_url) or "")
                if re.match(r"^https?://", resolved, re.IGNORECASE):
                    resolved_url = resolved
            except Exception:
                pass
        if _yandex_market_host(resolved_url):
            destination_url = _clean_destination(resolved_url)
            break
    if not destination_url:
        return ParseResult(False, "unsupported_merchant")
    item = {
        "channel": channel.lower().lstrip("@"),
        "message_id": int(getattr(message, "id")),
        "detected_code_count": 1,
        "confidence": "high",
        "title": f"Скидка {discount_value}% по промокоду {code}" if discount_value else f"Промокод {code}",
        "excerpt": _excerpt(text_value),
        "code": code,
        "destination_url": destination_url,
        "source_url": f"https://t.me/{channel.lower().lstrip('@')}/{int(getattr(message, 'id'))}",
        "raw_text": text_value,
        "published_at": published.isoformat(),
        "edited_at": _aware(edited).isoformat() if edited else "",
        "views": max(0, int(getattr(message, "views", 0) or 0)),
        "expires_at": int(_expiry(text_value, _aware(now)).timestamp()),
        "discount_label": f"{discount_value}%" if discount_value else "",
        "discount_value": discount_value,
    }
    return ParseResult(True, item=item)


def _expiry(text_value: str, now: datetime) -> datetime:
    explicit = DATE_PATTERN.search(text_value)
    if explicit:
        year = int(explicit.group(3) or now.year)
        year = 2000 + year if year < 100 else year
        try:
            return datetime.combine(datetime(year, int(explicit.group(2)), int(explicit.group(1))).date(), time(23, 59, 59), tzinfo=now.tzinfo)
        except ValueError:
            pass
    if re.search(r"\bсегодня\b", text_value, re.IGNORECASE):
        return datetime.combine(now.date(), time(23, 59, 59), tzinfo=now.tzinfo)
    if re.search(r"\bзавтра\b", text_value, re.IGNORECASE):
        return datetime.combine((now + timedelta(days=1)).date(), time(23, 59, 59), tzinfo=now.tzinfo)
    return now + timedelta(hours=72)


def _telegram_host(url: str) -> bool:
    return bool(re.match(r"https?://(?:www\.)?(?:t\.me|telegram\.me)/", url, re.IGNORECASE))


def _yandex_market_host(url: str) -> bool:
    host = (urlsplit(url).hostname or "").lower()
    return host == "market.yandex.ru" or host == "www.market.yandex.ru"


def _clean_destination(url: str) -> str:
    parts = urlsplit(url)
    return urlunsplit((parts.scheme, parts.netloc, parts.path, "", ""))


def _aware(value: datetime) -> datetime:
    return value.replace(tzinfo=timezone.utc) if value.tzinfo is None else value


def _excerpt(text_value: str) -> str:
    clean = re.sub(URL_PATTERN, "", text_value)
    clean = re.sub(r"\s+", " ", clean).strip()
    return clean[:300]
