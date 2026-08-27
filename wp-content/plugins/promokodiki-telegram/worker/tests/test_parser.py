import unittest
from datetime import datetime, timedelta, timezone
from types import SimpleNamespace

from promokodiki_telegram_worker.parser import parse_message


NOW = datetime(2026, 8, 24, 12, 0, tzinfo=timezone.utc)


def message(text, **overrides):
    values = dict(id=101, text=text, date=NOW, edit_date=None, views=200, forward=None, reply_to=None)
    values.update(overrides)
    return SimpleNamespace(**values)


class ParserTests(unittest.TestCase):
    def test_accepts_one_explicit_cart_discount_without_code(self):
        result = parse_message(
            message("Неимоверно дёшево\n\n✅ -5% в корзине\n\nhttps://market.yandex.ru/cc/AtsHDi?erid=tracking"),
            "tranzhiraru",
            NOW,
        )
        self.assertTrue(result.accepted)
        self.assertEqual("cart_discount", result.item["offer_type"])
        self.assertEqual("", result.item["code"])
        self.assertEqual(0, result.item["detected_code_count"])
        self.assertEqual("Неимоверно дёшево — скидка 5% в корзине", result.item["title"])
        self.assertEqual("https://market.yandex.ru/cc/AtsHDi", result.item["destination_url"])

    def test_rejects_ambiguous_cart_discounts_and_links(self):
        multiple_discounts = parse_message(
            message("Товар\n-5% в корзине\n-10% в корзине\nhttps://market.yandex.ru/a"),
            "tranzhiraru",
            NOW,
        )
        multiple_links = parse_message(
            message("Товар\n5% в корзине\nhttps://market.yandex.ru/a\nhttps://market.yandex.ru/b"),
            "tranzhiraru",
            NOW,
        )
        self.assertEqual("multiple_cart_discounts", multiple_discounts.reason)
        self.assertEqual("multiple_links", multiple_links.reason)

    def test_uses_neutral_title_for_empty_or_hype_heading(self):
        result = parse_message(
            message("Виу Виу 🔥\nскидка 5% в корзине\nhttps://market.yandex.ru/a"),
            "tranzhiraru",
            NOW,
        )
        self.assertEqual("Скидка 5% в корзине на Яндекс Маркете", result.item["title"])

    def test_default_expiry_is_based_on_publication_and_stale_offer_is_rejected(self):
        published = NOW - timedelta(hours=48)
        active = parse_message(
            message("Товар\n5% в корзине\nhttps://market.yandex.ru/a", date=published),
            "tranzhiraru",
            NOW,
        )
        stale = parse_message(
            message("Товар\n5% в корзине\nhttps://market.yandex.ru/a", date=NOW - timedelta(hours=80)),
            "tranzhiraru",
            NOW,
        )
        self.assertEqual((published + timedelta(hours=72)).timestamp(), active.item["expires_at"])
        self.assertEqual("expired", stale.reason)

    def test_accepts_one_explicit_code_and_external_link(self):
        result = parse_message(message("Промокод SAVE15 дает скидку 15% https://market.yandex.ru/item"), "tranzhiraru", NOW)
        self.assertTrue(result.accepted)
        self.assertEqual("SAVE15", result.item["code"])
        self.assertEqual(15, result.item["discount_value"])
        self.assertEqual(1, result.item["detected_code_count"])
        self.assertEqual("promocode", result.item["offer_type"])
        self.assertEqual("Скидка 15% по промокоду на Яндекс Маркете", result.item["title"])

    def test_code_offer_uses_the_first_meaningful_line_as_title(self):
        result = parse_message(
            message("Кофе Орнелио 1 кг!\n✅ -50%\nПромокод COFFEE50\nhttps://market.yandex.ru/coffee"),
            "tranzhiraru",
            NOW,
        )
        self.assertEqual("Кофе Орнелио 1 кг — скидка 50% по промокоду", result.item["title"])

    def test_repeated_same_code_is_one_code(self):
        result = parse_message(message("Промокод SAVE15. Используйте промокод SAVE15 https://market.yandex.ru"), "tranzhiraru", NOW)
        self.assertTrue(result.accepted)

    def test_uses_final_resolved_destination(self):
        result = parse_message(
            message("Промокод SAVE15 https://short.example/a"),
            "tranzhiraru",
            NOW,
            lambda url: "https://market.yandex.ru/final",
        )
        self.assertEqual("https://market.yandex.ru/final", result.item["destination_url"])

    def test_accepts_promo_label_and_removes_erid_from_destination(self):
        result = parse_message(
            message("Цена с промо JULY2000 https://market.yandex.ru/item?sku=10&erid=tracking"),
            "tranzhiraru",
            NOW,
        )

        self.assertTrue(result.accepted)
        self.assertEqual("JULY2000", result.item["code"])
        self.assertEqual("https://market.yandex.ru/item", result.item["destination_url"])

    def test_accepts_hyphenated_promo_code_label(self):
        result = parse_message(
            message("Промо-код SAVE15 https://market.yandex.ru"),
            "tranzhiraru",
            NOW,
        )

        self.assertTrue(result.accepted)

    def test_accepts_only_yandex_market_and_removes_entire_query(self):
        result = parse_message(
            message(
                "Промокод SAVE15 "
                "https://market.yandex.ru/wishlist/75ecbad4-9924-5f0d-75ec-bad499245f0d?publicId=abc&clid=2580165#offer"
            ),
            "tranzhiraru",
            NOW,
        )

        self.assertTrue(result.accepted)
        self.assertEqual(
            "https://market.yandex.ru/wishlist/75ecbad4-9924-5f0d-75ec-bad499245f0d",
            result.item["destination_url"],
        )

    def test_rejects_non_yandex_market_destination(self):
        result = parse_message(
            message("Промокод SAVE15 https://shop.example/item"),
            "tranzhiraru",
            NOW,
        )

        self.assertEqual("unsupported_merchant", result.reason)

    def test_rejects_multiple_distinct_codes(self):
        result = parse_message(message("Промокод SAVE15 или промокод BONUS20 https://market.yandex.ru"), "tranzhiraru", NOW)
        self.assertEqual("multiple_codes", result.reason)

    def test_rejects_missing_link_forward_and_reply(self):
        self.assertEqual("missing_link", parse_message(message("Промокод SAVE15"), "tranzhiraru", NOW).reason)
        self.assertEqual("forwarded", parse_message(message("Промокод SAVE15 https://market.yandex.ru", forward=object()), "tranzhiraru", NOW).reason)
        self.assertEqual("reply", parse_message(message("Промокод SAVE15 https://market.yandex.ru", reply_to=object()), "tranzhiraru", NOW).reason)

    def test_expiry_today_tomorrow_explicit_and_default(self):
        today = parse_message(message("Промокод SAVE15 до сегодня https://market.yandex.ru"), "tranzhiraru", NOW).item["expires_at"]
        tomorrow = parse_message(message("Промокод SAVE15 до завтра https://market.yandex.ru"), "tranzhiraru", NOW).item["expires_at"]
        explicit = parse_message(message("Промокод SAVE15 до 30.08.2026 https://market.yandex.ru"), "tranzhiraru", NOW).item["expires_at"]
        default = parse_message(message("Промокод SAVE15 https://market.yandex.ru"), "tranzhiraru", NOW).item["expires_at"]
        self.assertLess(today, tomorrow)
        self.assertEqual(datetime(2026, 8, 30, 23, 59, 59, tzinfo=timezone.utc).timestamp(), explicit)
        self.assertEqual((NOW.timestamp() + 72 * 3600), default)


if __name__ == "__main__":
    unittest.main()
