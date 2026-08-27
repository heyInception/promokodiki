# Telegram Promocodes: эксплуатация

## Архитектура

- WordPress-плагин: `wp-content/plugins/promokodiki-telegram`.
- Worker: `wp-content/plugins/promokodiki-telegram/worker`.
- Telegram читается отдельным пользовательским аккаунтом через MTProto/Telethon.
- Worker запускается cron-ом раз в 3 часа, отправляет подписанный batch в WordPress и завершается.
- `.env` и Telethon `.session` должны находиться вне `public_html`.

## WordPress

1. Разверните код и активируйте плагин **Promokodiki Telegram**.
2. Откройте **Промокоды → Telegram**.
3. Проверьте канал `tranzhiraru`, число карточек (4–20) и скопируйте worker secret из базы/CLI. В интерфейсе секрет намеренно маскируется.
4. Для получения секрета через WP-CLI используйте:

   ```bash
   wp option get promokodiki_telegram_settings --format=json
   ```

5. Добавляйте публичные каналы на этой же странице. Статусы и последние результаты отображаются в журнале.

## VPS worker

WordPress может оставаться на Sprinthost, но MTProto worker должен работать на VPS с доступом к серверам Telegram. Worker запускается по расписанию и не должен постоянно работать в фоне.

Пример каталогов:

```text
/opt/promokodiki-telegram/          # worker, venv, .env
/opt/promokodiki-telegram/data/     # Telethon session
```

Создание окружения по SSH:

```bash
python3 -m venv /opt/promokodiki-telegram/venv
/opt/promokodiki-telegram/venv/bin/python -m pip install -r /opt/promokodiki-telegram/requirements.txt
chmod 600 /opt/promokodiki-telegram/.env
```

Заполните `.env`: `TELEGRAM_API_ID`, `TELEGRAM_API_HASH`, телефон отдельного Telegram-аккаунта, абсолютный `TELEGRAM_SESSION_PATH`, адрес сайта и `WORDPRESS_SECRET`. API ID/hash создаются в кабинете Telegram для отдельного аккаунта.

Worker ищет `.env` рядом с `run.py`. На хостинге безопаснее создать символическую ссылку на приватный файл или экспортировать переменные в wrapper-скрипте, права которого `700`. Не копируйте секреты и session в Git.

## Первый вход MTProto

Первый запуск выполните вручную в SSH-интерактивной сессии:

```bash
cd /opt/promokodiki-telegram
./venv/bin/python run.py --env-file /opt/promokodiki-telegram/.env
```

Telethon запросит код подтверждения и, если включено, пароль 2FA. После успешного входа убедитесь, что `.session` создан по приватному пути и имеет права `600`.

Если код подтверждения не приходит, выполните QR-вход из интерактивного терминала:

```bash
/opt/promokodiki-telegram/venv/bin/python /opt/promokodiki-telegram/run.py --env-file /opt/promokodiki-telegram/.env --qr-login
```

В официальном приложении Telegram откройте **Настройки → Устройства → Подключить устройство**, отсканируйте QR и при необходимости введите пароль 2FA в терминале. После создания session последующие cron-запуски выполняются обычной командой без `--qr-login`.

## Cron

LandVPS предоставляет полный `root`-доступ к VPS, поэтому системный Cron доступен как обычная служба Ubuntu. Проверьте его состояние:

```bash
systemctl status cron --no-pager
command -v crontab
```

Если служба не установлена или выключена:

```bash
apt update
apt install -y cron
systemctl enable --now cron
```

Команда cron каждые 3 часа:

```cron
17 */3 * * * /usr/bin/flock -n /tmp/promokodiki-telegram.lock /opt/promokodiki-telegram/venv/bin/python /opt/promokodiki-telegram/run.py --env-file /opt/promokodiki-telegram/.env >> /opt/promokodiki-telegram/worker.log 2>&1
```

Добавьте строку через `crontab -e`, затем проверьте расписание и службу:

```bash
crontab -l
systemctl is-active cron
```

`flock` не позволяет запустить второй экземпляр worker, если предыдущая синхронизация ещё не завершилась.

Минуту `17` можно заменить, чтобы не запускаться одновременно с другими задачами. Ограничьте лог через панель хостинга или `logrotate`.

## Поведение и восстановление

- Первый проход читает не более 200 сообщений за последние 7 дней; дальше используются новые сообщения и повторная проверка известных ID.
- Публикуется только пост с одним явно обозначенным промокодом и ссылкой на `market.yandex.ru`. Посты с несколькими кодами и ссылки на другие магазины пропускаются полностью.
- Также публикуется пост без кода, если в нём есть ровно одна явная скидка вида `-5% в корзине`, `5% в корзине` или `скидка 5% в корзине` и ровно одна ссылка на `market.yandex.ru`. Такая карточка ведёт сразу в магазин без модального окна.
- Заголовок Telegram-карточки строится из первой содержательной строки поста и типа выгоды. Ссылки, эмодзи и служебные строки не используются; для бессодержательного текста применяется нейтральный заголовок.
- Срок по умолчанию составляет 72 часа от времени публикации Telegram-поста. Уже просроченные предложения при повторном сканировании не импортируются.
- Из ссылок Яндекс Маркета удаляются вся query-строка и fragment до передачи в Admitad или сохранения прямой ссылки.
- Telegram-изображение сохраняется как миниатюра записи; результат прикрепления фиксируется в метаполе `_telegram_media_status`.
- Если Admitad-кампания найдена, создаётся deeplink. При отсутствии кампании или ошибке API сохраняется очищенная прямая ссылка.
- Ручная блокировка в метабоксе Telegram защищает запись от обновления, снятия с публикации и автоматического истечения.
- При ошибке авторизации удалите только повреждённый приватный `.session`, затем повторите интерактивный вход. Не удаляйте WordPress-записи.
- При ошибке подписи сверьте `WORDPRESS_SECRET`, системное время хостинга и адрес WordPress без завершающего `/wp-json`.
- После сбоя смотрите приватный `worker.log`, затем журнал **Промокоды → Telegram**.
