# Инструкция по развёртыванию системы отложенной очистки thumbnails

## Шаги развёртывания

### 1. Применить миграцию базы данных

```bash
php artisan migrate
```

Это добавит поле `accepted_at` в таблицу `task_file_thumbnails`.

### 2. Обновить существующие данные (опционально)

Если нужно пометить thumbnails уже принятых задач:

```bash
php artisan tinker
```

```php
DB::table('task_file_thumbnails as t')
    ->join('tasks as tk', 't.task_id', '=', 'tk.id')
    ->whereIn('tk.status', ['accepted', 'cancelled'])
    ->whereNull('t.accepted_at')
    ->update(['t.accepted_at' => DB::raw('tk.updated_at')]);
```

Или через SQL:

```sql
UPDATE task_file_thumbnails t
INNER JOIN tasks tk ON t.task_id = tk.id
SET t.accepted_at = tk.updated_at
WHERE tk.status IN ('accepted', 'cancelled')
  AND t.accepted_at IS NULL;
```

### 3. Проверить команду очистки

**Тестовый запуск (без удаления):**

```bash
php artisan thumbnails:cleanup --dry-run
```

Команда покажет список файлов, которые будут удалены.

### 4. Настроить cron для Laravel Scheduler

Убедитесь, что настроен cron для автоматического запуска команд:

```bash
crontab -e
```

Добавьте строку:

```
* * * * * cd /var/www/html/decuspro/yd && php artisan schedule:run >> /dev/null 2>&1
```

### 5. Проверить расписание

```bash
php artisan schedule:list
```

Вы должны увидеть:

```
0 3 * * * php artisan thumbnails:cleanup .... Next Due: 1 day from now
```

### 6. Мониторинг логов

После развёртывания следите за логами:

```bash
tail -f storage/logs/laravel.log | grep -i thumbnail
```

## Откат изменений (если необходимо)

### 1. Откатить миграцию

```bash
php artisan migrate:rollback --step=1
```

### 2. Удалить расписание

Закомментируйте в `routes/console.php`:

```php
// Schedule::command('thumbnails:cleanup')->dailyAt('03:00');
```

## Проверка работоспособности

### Тест 1: Пометка для удаления

1. Создайте задачу и загрузите файл
2. Измените статус на "accepted"
3. Проверьте БД:

```sql
SELECT * FROM task_file_thumbnails WHERE task_id = <ID>;
```

Поле `accepted_at` должно быть заполнено.

### Тест 2: Автоматическая очистка

1. Вручную установите `accepted_at` на 15 дней назад:

```sql
UPDATE task_file_thumbnails 
SET accepted_at = DATE_SUB(NOW(), INTERVAL 15 DAY) 
WHERE task_id = <ID>;
```

2. Запустите команду:

```bash
php artisan thumbnails:cleanup
```

3. Проверьте, что файлы удалены:

```bash
ls -la storage/app/public/tasks/<ID>/thumbnails/
```

## Что изменилось

### ✅ Изменено

- **TaskController::cleanupTaskThumbnails()** - теперь помечает вместо удаления
- **TaskFileThumbnail** модель - добавлено поле `accepted_at`
- **routes/console.php** - добавлено расписание очистки

### ✅ Добавлено

- **Миграция:** `2025_10_02_000000_add_accepted_at_to_task_file_thumbnails.php`
- **Команда:** `app/Console/Commands/CleanupOldThumbnails.php`
- **Документация:** `THUMBNAIL_CLEANUP.md`

### ✅ Не изменено

- Загрузка файлов на Яндекс.Диск
- Создание thumbnails
- Отображение в интерфейсе
- Явное удаление файлов пользователем
- Удаление задач

## Безопасность

- ✅ Все изменения обратно совместимы
- ✅ Существующий функционал не затронут
- ✅ Thumbnails доступны 14 дней после принятия
- ✅ Можно откатить без потери данных

## Поддержка

При возникновении проблем проверьте:

1. **Логи Laravel:** `storage/logs/laravel.log`
2. **Права доступа:** `storage/app/public/tasks/` должна быть доступна для записи
3. **Cron работает:** `grep CRON /var/log/syslog`
4. **Scheduler запускается:** `php artisan schedule:list`
