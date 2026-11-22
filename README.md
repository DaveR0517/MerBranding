# MEBRANDING

Platform dat ondernemers koppelt aan creators. Eén Laravel-applicatie voor de website en alle portalen.

| Adres | Wat | Code |
|---|---|---|
| https://mebranding.nl | Website (Blade) | `routes/site.php`, `resources/views/site` |
| https://app.mebranding.nl | Portalen (Inertia, React, TypeScript, Tailwind) | `routes/web.php`, `resources/js` |
| https://demo.mebranding.nl | Statische testversie voor gesprekken | `demo/public` (aparte Forge-site) |

## Techniek

Laravel 13, PHP 8.5, MySQL 8.4, Redis met Horizon, Fortify voor inloggen en tweestapsverificatie, spatie/laravel-permission voor rollen, Pest voor tests, Pint voor codestijl. Lettertypes (Montserrat, Noto Sans) zijn zelf gehost.

## Lokaal draaien

```bash
cp .env.example .env   # zet DB_CONNECTION=sqlite voor snel lokaal werk
composer install && npm install
php artisan key:generate && php artisan migrate --seed
npm run build && php artisan serve
```

Lokaal draaien de domeinen als `mebranding.test` en `app.mebranding.test` (zie `.env`). Zet die in je hosts-bestand naar 127.0.0.1.

## Tests

```bash
./vendor/bin/pest
```

## Server (Laravel Forge)

- Server `MeBranding`, site `mebranding.nl` met domeinen `www.mebranding.nl` (doorsturen) en `app.mebranding.nl`.
- Deploy vanuit `main`, zero-downtime. Script: `deploy/forge-deploy.sh`.
- Horizon als daemon, planner (`schedule:run`) elke minuut.
- Demo: aparte site `demo.mebranding.nl`, statisch, map `demo/public`.
- Super Admin aanmaken (eenmalig, in Forge bij Commands): `php artisan mebranding:super-admin <email> "<naam>"`. Dit toont een link om het wachtwoord in te stellen.

## Geheimen

Nooit in Git. Alleen in `.env` op de server (Forge, tabblad Environment).

## Instelbare voorstellen

Waarden die nog geen besluit zijn, staan gemarkeerd als `VOORSTEL` in code en `config/mebranding.php`.
