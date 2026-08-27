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
CART_DISCOUNT_PATTERN = re.compile(r"(?:скидка\s*)?-?\s*(\d{1,3})\s*%\s*в\s+корзине", re.IGNORECASE)
DATE_PATTERN = re.compile(r"(?:до|по)\s*(\d{1,2})[./](\d{1,2})(?:[./](\d{2,4}))?", re.IGNORECASE)
HYPE_TITLE_PATTERN = re.compile(r"^(?:(?:виу|вау|срочно|шок|огонь)\s*)+|^(?:разбира\w*|налета\w*|успева\w*)!?$", re.IGNORECASE)


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
    if len(codes) != 1:
        if len(codes) > 1:
            return ParseResult(False, "multiple_codes")

    cart_discounts = list(
        dict.fromkeys(
            int(value)
            for value in CART_DISCOUNT_PATTERN.findall(text_value)
            if 0 < int(value) <= 100
        )
    )
    if len(cart_discounts) > 1:
        return ParseResult(False, "multiple_cart_discounts")
    if codes:
        offer_type = "promocode"
        code = codes[0]
    elif len(cart_discounts) == 1:
        offer_type = "cart_discount"
        code = ""
    else:
        return ParseResult(False, "missing_code")

    urls = [url.rstrip(".,;:!?)") for url in URL_PATTERN.findall(text_value)]
    urls = [url for url in urls if not _telegram_host(url)]
    if not urls:
        return ParseResult(False, "missing_link")

    destinations = []
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
            destinations.append(_clean_destination(resolved_url))
    destinations = list(dict.fromkeys(destinations))
    if not destinations:
        return ParseResult(False, "unsupported_merchant")
    if len(destinations) > 1:
        return ParseResult(False, "multiple_links")

    published = _aware(getattr(message, "date", None) or now)
    edited = getattr(message, "edit_date", None)
    discount_match = DISCOUNT_PATTERN.search(text_value)
    discount_value = cart_discounts[0] if offer_type == "cart_discount" else (int(discount_match.group(1)) if discount_match else 0)
    expires_at = _expiry(text_value, published)
    if expires_at <= _aware(now):
        return ParseResult(False, "expired")
    item = {
        "channel": channel.lower().lstrip("@"),
        "message_id": int(getattr(message, "id")),
        "offer_type": offer_type,
        "detected_code_count": 1 if code else 0,
        "confidence": "high",
        "title": _title(text_value, offer_type, discount_value),
        "excerpt": _excerpt(text_value),
        "code": code,
        "destination_url": destinations[0],
        "source_url": f"https://t.me/{channel.lower().lstrip('@')}/{int(getattr(message, 'id'))}",
        "raw_text": text_value,
        "published_at": published.isoformat(),
        "edited_at": _aware(edited).isoformat() if edited else "",
        "views": max(0, int(getattr(message, "views", 0) or 0)),
        "expires_at": int(expires_at.timestamp()),
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


def _title(text_value: str, offer_type: str, discount_value: int) -> str:
    candidate = ""
    for raw_line in text_value.splitlines():
        line = URL_PATTERN.sub("", raw_line)
        marker = CART_DISCOUNT_PATTERN.search(line) or CODE_PATTERN.search(line)
        if marker:
            line = line[: marker.start()]
        line = re.sub(r"[*_~`\[\]()]+", " ", line)
        line = re.sub(r"\s+", " ", line).strip()
        line = re.sub(r"^[\W_]+|[\W_]+$", "", line, flags=re.UNICODE).strip()
        if len(line) < 4 or HYPE_TITLE_PATTERN.search(line):
            continue
        candidate = line[:90].rstrip(" .,;:!—-")
        break

    if offer_type == "cart_discount":
        benefit = f"скидка {discount_value}% в корзине"
        fallback = f"Скидка {discount_value}% в корзине на Яндекс Маркете"
    elif discount_value:
        benefit = f"скидка {discount_value}% по промокоду"
        fallback = f"Скидка {discount_value}% по промокоду на Яндекс Маркете"
    else:
        benefit = "предложение по промокоду"
        fallback = "Промокод на Яндекс Маркете"
    return f"{candidate} — {benefit}" if candidate else fallback


def _excerpt(text_value: str) -> str:
    clean = re.sub(URL_PATTERN, "", text_value)
    clean = re.sub(r"\s+", " ", clean).strip()
    return clean[:300]
