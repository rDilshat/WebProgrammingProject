# KinoMonster

## Requirements

- PHP 8.1 or newer with `pdo_pgsql` enabled
- PostgreSQL 15 or newer

## Local database setup

Each developer should use their own local PostgreSQL database. Do not commit database passwords.

1. Create a PostgreSQL login role named `kinomonster_app`.
2. Create a database named `kinomonster` with `kinomonster_app` as its owner.
3. Run [`database/schema.sql`](database/schema.sql) in that database.
4. Copy the local configuration template:

   ```powershell
   Copy-Item config/database.local.php.example config/database.local.php
   ```

5. Put the local database password in `config/database.local.php`. This file is ignored by Git.

Example configuration:

```php
<?php

return [
    'host' => '127.0.0.1',
    'port' => '5432',
    'database' => 'kinomonster',
    'user' => 'kinomonster_app',
    'password' => 'your-local-password',
];
```

If the `users` table was created by another PostgreSQL role, transfer ownership while connected as an administrator:

```sql
ALTER TABLE public.users OWNER TO kinomonster_app;
ALTER SEQUENCE public.users_id_seq OWNER TO kinomonster_app;
```

## Run locally

From the `Kinomonster` directory:

```powershell
php -m | Select-String "pgsql"
php -S localhost:8000
```

Open <http://localhost:8000/register.html>.

The PHP module check should list `pdo_pgsql`. Live Server cannot execute the PHP endpoints, so use the PHP development server.
