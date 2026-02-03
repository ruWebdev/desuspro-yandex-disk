# Laravel Docker Setup

Этот проект настроен для запуска в Docker контейнерах. Настройка включает PHP 8.2, MySQL 8.0, Redis и опционально Node.js для разработки.

## Быстрый старт

1. **Клонируйте репозиторий и перейдите в директорию проекта**
   ```bash
   cd /path/to/your/project
   ```

2. **Скопируйте файл окружения**
   ```bash
   cp .env.docker .env
   ```

3. **Сгенерируйте ключ приложения**
   ```bash
   # Если у вас уже есть .env файл с APP_KEY, пропустите этот шаг
   echo "APP_KEY=" > .env
   docker-compose run --rm app php artisan key:generate
   ```

4. **Запустите контейнеры**
   ```bash
   docker-compose up -d
   ```

5. **Выполните миграции базы данных**
   ```bash
   docker-compose exec app php artisan migrate
   ```

Приложение будет доступно по адресу: http://localhost:8000

## Детальная настройка

### Файлы конфигурации

- `Dockerfile` - Конфигурация PHP контейнера с Apache
- `docker-compose.yml` - Оркестрация сервисов (app, mysql, redis, node)
- `.dockerignore` - Исключения для Docker контекста
- `.env.docker` - Шаблон переменных окружения для Docker

### Сервисы

#### App (Laravel)
- **Порт**: 8000
- **База данных**: MySQL
- **Кэш**: Redis
- **Очереди**: Redis

#### MySQL
- **Порт**: 3306
- **База данных**: laravel
- **Пользователь**: laravel
- **Пароль**: laravel_password

#### Redis
- **Порт**: 6379
- **Используется для**: Кэширование, сессии, очереди

#### Node.js (для разработки)
- **Порт**: 5173
- **Профиль**: dev
- **Команда**: `npm run dev -- --host 0.0.0.0`

## Команды для работы с Docker

### Основные команды

```bash
# Запуск всех сервисов
docker-compose up -d

# Остановка всех сервисов
docker-compose down

# Просмотр логов
docker-compose logs -f

# Просмотр логов конкретного сервиса
docker-compose logs -f app

# Пересборка контейнеров
docker-compose up -d --build
```

### Работа с Laravel в контейнере

```bash
# Выполнение Artisan команд
docker-compose exec app php artisan migrate
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear

# Установка Composer пакетов
docker-compose exec app composer install

# Установка NPM пакетов
docker-compose exec app npm install

# Сборка frontend assets
docker-compose exec app npm run build
```

### Работа с базой данных

```bash
# Подключение к MySQL
docker-compose exec mysql mysql -u laravel -plaravel_password laravel

# Создание дампа базы данных
docker-compose exec mysql mysqldump -u laravel -plaravel_password laravel > backup.sql

# Восстановление из дампа
docker-compose exec -T mysql mysql -u laravel -plaravel_password laravel < backup.sql
```

## Режимы разработки

### С горячей перезагрузкой (Hot Reload)

Для разработки с автоматической перезагрузкой при изменении файлов:

```bash
# Запуск с Node.js сервисом
docker-compose --profile dev up -d

# Или отдельно запустить Node.js
docker-compose up -d node
```

### Локальная разработка

Если вы хотите разрабатывать локально, но использовать Docker для базы данных:

```bash
# Запустите только базу данных и Redis
docker-compose up -d mysql redis

# В вашем локальном .env файле укажите:
DB_HOST=127.0.0.1
REDIS_HOST=127.0.0.1
```

## Переменные окружения

Основные переменные для настройки:

```bash
# Приложение
APP_NAME=Laravel
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8000

# База данных
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=laravel_password

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Yandex Disk (если используется)
YANDEX_CLIENT_ID=your_client_id
YANDEX_CLIENT_SECRET=your_client_secret
```

## Устранение неполадок

### Проблемы с разрешениями

```bash
# Исправление прав на storage
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chmod -R 775 /var/www/html/storage
```

### Очистка Docker

```bash
# Остановка и удаление всех контейнеров
docker-compose down

# Удаление volumes (внимание: удалятся данные!)
docker-compose down -v

# Очистка неиспользуемых образов
docker image prune -f
```

### Логи и отладка

```bash
# Просмотр логов приложения
docker-compose logs -f app

# Просмотр логов базы данных
docker-compose logs -f mysql

# Вход в контейнер
docker-compose exec app bash

# Проверка статуса сервисов
docker-compose ps
```

## Производственное развертывание

Для production окружения:

1. **Измените переменные окружения** в `.env` файле
2. **Настройте внешнюю базу данных** вместо локального MySQL
3. **Настройте Redis кластер** при необходимости
4. **Добавьте SSL/TLS** через reverse proxy (nginx, traefik)
5. **Настройте мониторинг** и логирование

### Пример production docker-compose.yml

```yaml
version: '3.8'
services:
  app:
    build: .
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - DB_HOST=external-db-host
      - REDIS_HOST=external-redis-host
    # Добавьте healthcheck
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3
```

## Структура проекта

```
/var/www/html/              # Корневая директория Laravel
├── app/                    # Исходный код приложения
├── bootstrap/              # Загрузчик приложения
├── config/                 # Конфигурационные файлы
├── database/               # Миграции, seeders
├── public/                 # Публичные файлы (index.php, assets)
├── resources/              # Views, assets
├── routes/                 # Маршруты
├── storage/                # Логи, кэш, файлы
├── Dockerfile             # Конфигурация PHP контейнера
├── docker-compose.yml     # Оркестрация сервисов
├── .dockerignore          # Исключения для сборки
└── .env.docker           # Шаблон переменных окружения
```

## Поддержка

Если возникнут проблемы с настройкой Docker:

1. Проверьте логи контейнеров: `docker-compose logs`
2. Убедитесь, что порты 8000, 3306, 6379 свободны
3. Проверьте переменные окружения в `.env` файле
4. Убедитесь, что Docker и docker-compose установлены

Приложение будет доступно по адресу: http://localhost:8000