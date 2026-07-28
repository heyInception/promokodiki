# Эксплуатация автоматизации Admitad

## Границы автоматизации

Плагин синхронизирует купоны, кампании и справочники Admitad, классифицирует купоны и ведёт историю. Он не создаёт, не переименовывает, не перемещает и не удаляет рубрики `promocode_category`. Все связи указывают только на существующие рубрики сайта.

Подозреваемые дубли никогда не объединяются автоматически. Ручная правка текста или рубрик купона создаёт редакционную блокировку, которую импорт обязан сохранить.

## Первичная настройка

В WordPress откройте «Промокоды → Настройки». Укажите `client_id`, `client_secret` и `website_id` либо задайте константы:

```php
define( 'PROMOKODIKI_ADMITAD_CLIENT_ID', '...' );
define( 'PROMOKODIKI_ADMITAD_CLIENT_SECRET', '...' );
define( 'PROMOKODIKI_ADMITAD_WEBSITE_ID', '2811611' );
```

Secret и access token не отображаются в панели или диагностическом экспорте. После сохранения проверьте разделы «Синхронизация» и «Диагностика».

## Миграция старого маппинга

Сначала выполните только анализ:

```powershell
studio wp admitad automation-migrate --dry-run
```

Перед копированием создайте и проверьте резервную копию базы. Выполнение без существующего непустого файла и явного подтверждения блокируется:

```powershell
studio wp admitad automation-migrate --execute --backup="C:\backups\before-admitad.sql" --yes
```

Миграция пакетная и повторяемая. Старые таблицы не удаляются. Короткие/небезопасные фрагменты приостанавливаются, противоречивые фразы получают статус `conflict`, а компания без однозначного campaign ID попадает в очередь.

## Проверка качества и применение

1. В «История и откат» создайте контрольную выборку из 150 купонов.
2. Запишите ожидаемые рубрики.
3. До массового применения добейтесь:
   - точность high-confidence не ниже 95%;
   - покрытие рубриками конкретнее `other` не ниже 85%;
   - сохранность редакционных блокировок 100%;
   - назначений вне профиля компании 0%.
4. Создайте preview для нужных ID купонов. Preview не меняет таксономию и исключает заблокированные записи.
5. Проверьте пары «было → станет», затем подтвердите пакетное применение.
6. При необходимости выполните откат по тому же UUID-снимку.

Повторное нажатие «применить» не создаёт второй CRON-event. Применение возможно только для принадлежащего текущему администратору, неистёкшего снимка со статусом `previewed`.

## CRON

Поддерживаемая базовая схема — WP-Cron. В WP Crontrol должны быть видны:

- `promokodiki_admitad_coupon_sync`;
- `promokodiki_admitad_reference_sync`;
- `promokodiki_admitad_reconcile`;
- `promokodiki_admitad_retention`.

Локальная ручная проверка:

```powershell
studio wp cron event run promokodiki_admitad_coupon_sync
studio wp cron event run promokodiki_admitad_reference_sync
studio wp cron event run promokodiki_admitad_reconcile
```

На production-хостинге используются те же команды без префикса `studio`. Если системный CRON доступен, запускайте `wp cron event run --due-now` каждые 5 минут. Это необязательное усиление; WP-Cron остаётся поддерживаемым режимом.

Просроченную блокировку задачи можно снять в разделе «Синхронизация». Активную блокировку плагин снять не позволит.

## Мониторинг и восстановление

- «Обзор» показывает расписание и объём очереди.
- «Диагностика» показывает состояние конфигурации, CRON, блокировок и последних запусков без credentials.
- Email-оповещения сообщают об OAuth-ошибках, повторных сбоях и задержанных заданиях.
- Ежедневный retention удаляет завершённые журнальные детали старше настроенного срока, но не удаляет открытую очередь и действующие rollback-снимки.
- При ошибке API проверьте HTTP-код, OAuth-настройки и следующий retry; синхронные `sleep` не используются.

## Проверки перед публикацией

Все интеграционные тесты одной командой:

```powershell
.\wp-content\plugins\admitad-coupons\tests\run-all.ps1 -SitePath "C:\Users\Inception\Studio\promokodiki"
```

Для живого smoke-теста нужны локально настроенные credentials. Выполните две последовательные синхронизации и убедитесь, что второй проход возвращает преимущественно `unchanged`, не создаёт дубли и не меняет редакционные блокировки.
# Recovery backup registration

Before starting the Admitad recovery migration, create and register a database backup. The registration stores only a normalized path, size, SHA-256 checksum, and timestamp; it is valid for 24 hours.

```powershell
studio export backups/admitad-before-recovery.sql --mode db
studio wp admitad backup-register --path="C:\\absolute\\path\\to\\admitad-before-recovery.sql"
```
