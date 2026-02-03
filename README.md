<p align="center">
  <a href="https://laravel.com" target="_blank">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
  </a>
  <h1 align="center">DecusPro Yandex.Disk Integration</h1>
</p>

<p align="center">
  <a href="#"><img src="https://img.shields.io/badge/version-1.0.0-blue" alt="Version"></a>
  <a href="#"><img src="https://img.shields.io/badge/php-8.2%2B-8892BF" alt="PHP 8.2+"></a>
  <a href="#"><img src="https://img.shields.io/badge/laravel-10.x-FF2D20" alt="Laravel 10.x"></a>
  <a href="#"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License"></a>
</p>

## О проекте

DecusPro Yandex.Disk Integration - это веб-приложение для управления задачами с интеграцией Yandex.Disk, построенное на Laravel и Inertia.js с использованием Vue.js на фронтенде.

### Основные возможности

- Управление задачами и подзадачами
- Интеграция с Yandex.Disk для хранения файлов
- Разграничение прав доступа (роли: Администратор, Менеджер, Исполнитель)
- Гибкая система фильтрации и поиска задач
- Удобный интерфейс для работы с файлами

## Требования

- PHP 8.2+
- Composer 2.0+
- Node.js 18+
- MySQL 8.0+
- Redis (для кеширования и очередей)
- Yandex.Disk API ключи

## Установка

1. Клонируйте репозиторий:
   ```bash
   git clone git@github.com:your-organization/decuspro-yd.git
   cd decuspro-yd
   ```

2. Установите зависимости PHP:
   ```bash
   composer install
   ```

3. Установите зависимости Node.js:
   ```bash
   npm install
   ```

4. Настройте окружение:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. Настройте базу данных в `.env`

6. Выполните миграции и сиды:
   ```bash
   php artisan migrate --seed
   ```

7. Соберите ассеты:
   ```bash
   npm run build
   ```

8. Запустите сервер:
   ```bash
   php artisan serve
   ```

## Разработка

Для локальной разработки рекомендуется использовать Laravel Sail:

```bash
# Создайте файл .env
cp .env.example .env

# Запустите контейнеры
./vendor/bin/sail up -d

# Установите зависимости
./vendor/bin/sail composer install
./vendor/bin/sail npm install

# Запустите миграции
./vendor/bin/sail artisan migrate --seed

# Соберите ассеты
./vendor/bin/sail npm run dev
```

## Тестирование

```bash
# Запуск PHPUnit тестов
php artisan test

# Запуск PHPStan (статический анализ)
vendor/bin/phpstan analyse

# Запуск ESLint
npx eslint resources/js --ext .js,.vue
```

## Развертывание

Проект использует GitHub Actions для CI/CD. Развертывание происходит автоматически:
- При пуше в ветку `develop` - на тестовое окружение
- При мерже в ветку `main` - на продакшн

## Лицензия

Этот проект является проприетарным программным обеспечением и защищен авторскими правами.

## Документация

- [Документация Laravel](https://laravel.com/docs)
- [Документация Inertia.js](https://inertiajs.com/)
- [Документация Vue.js](https://vuejs.org/guide/)
- [Документация Yandex.Disk API](https://yandex.ru/dev/disk/api/concepts/)

## Поддержка

По вопросам, связанным с проектом, обращайтесь к команде разработки.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Вклад в проект

Спасибо за ваш интерес к участию в проекте! Пожалуйста, ознакомьтесь с нашим [руководством по вкладу](CONTRIBUTING.md), чтобы узнать, как вы можете помочь.

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
