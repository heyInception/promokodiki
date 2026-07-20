# Promokodiki

WordPress-тема и плагин синхронизации промокодов с Admitad. Репозиторий содержит только пользовательский код: ядро WordPress, база, загрузки и сторонние плагины намеренно исключены.

## Архитектура данных

- `promocode` — единственный тип записи для предложений.
- `promocode_category` — тематические рубрики.
- `shops_category` — магазины; стабильный Admitad campaign ID хранится в term meta `admitad_campaign_id`.
- `/promocodes/` — архив промокодов, `/shops/` — каталог магазинов, `/shops-category/{slug}/` — предложения конкретного магазина.
- Старый CPT `shops` и таксономия `shop_coupons` не используются.

## Локальный запуск

Проект разработан в [WordPress Studio](https://developer.wordpress.com/studio/). После импорта сайта:

```powershell
studio start --skip-browser
studio wp plugin activate admitad-coupons
studio wp theme activate promokodiki
studio wp rewrite flush
```

В обычном WordPress-хостинге используйте те же команды без префикса `studio`.

## Настройка Admitad

После публикации старые credentials необходимо отозвать в Admitad. Новые значения задаются либо на странице **Промокоды → Admitad**, либо константами в неотслеживаемом `wp-config.php`:

```php
define( 'PROMOKODIKI_ADMITAD_CLIENT_ID', 'replace-locally' );
define( 'PROMOKODIKI_ADMITAD_CLIENT_SECRET', 'replace-locally' );
define( 'PROMOKODIKI_ADMITAD_WEBSITE_ID', 'replace-locally' );
```

Проверка соединения не показывает токен или secret. Синхронизация запускается дважды в день через WP-Cron либо вручную:

```powershell
studio wp admitad import
```

Импорт обрабатывает API постранично, блокирует параллельные запуски и обновляет запись по `admitad_coupon_id`, не создавая дублей.

## Миграция старой базы

Сначала выполните анализ:

```powershell
studio wp admitad migrate --dry-run
```

Перед выполнением обязателен непустой backup-файл:

```powershell
studio export backups/pre-migration.sql --mode db
studio wp admitad migrate --execute --yes --backup="backups/pre-migration.sql"
```

Команда переносит метаданные, комментарии и таксономии в канонические записи, затем безвозвратно удаляет дубли. Итоговый отчёт сохраняется в option `admitad_last_migration_report`.

Для восстановления локального сайта создайте новый сайт Studio и импортируйте сохранённый `.sql`/полный `.zip`. На production сначала проверьте процедуру восстановления средствами хостинга.

## Проверки

```powershell
studio wp admitad migrate --dry-run
studio wp post list --post_type=promocode --format=count
studio wp post list --post_type=shops --post_status=any --format=count
```

GitHub Actions проверяет синтаксис PHP, опасные TLS-настройки и случайно добавленные credentials. `style.css` и `assets/css/main.css` считаются исходными файлами дизайна и не изменяются; расширения стилей следует добавлять в `assets/css/overrides.css`.

## Лицензия

Пользовательский код распространяется на условиях GPL-2.0-or-later. Сторонние плагины и материалы сохраняют лицензии своих правообладателей.

