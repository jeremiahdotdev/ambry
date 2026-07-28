# Ambry

Ambry is a Laravel app for searching and browsing saint profiles. It includes a searchable home page, profile pages, local saint imagery, and a small import pipeline for source-derived data.

## Requirements

- PHP 8.4+
- Composer
- Node.js and npm
- SQLite

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

The default local database path is `database/database.sqlite`.

## Development

```bash
composer dev
```

For separate processes:

```bash
php artisan serve
npm run dev
```

## Tests

```bash
php artisan test
npm run build
```

## Data Scripts

Database cleanup scripts live in `database/scripts`.

```bash
php database/scripts/normalize_life_dates.php
```

That script normalizes stored `life_dates` values by appending `AD` to dated strings that do not already specify an era.
