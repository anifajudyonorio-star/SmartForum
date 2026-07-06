# SmartForum (Smart Discussion)

A Laravel-based academic discussion forum where **lecturers** create groups and topics, **students** join groups and participate in discussions, and **admins** oversee platform activity.

## Requirements

| Tool | Version |
|------|---------|
| PHP | 8.3+ (extensions: `pdo_sqlite` or `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`) |
| Composer | 2.x |
| Node.js | 18+ (20+ recommended) |
| npm | 9+ |

Optional for MySQL instead of SQLite:

- MySQL 8.x (note: some local installs use port **3307** instead of 3306)

## Quick start (recommended)

From the project root:

```bash
composer setup
php artisan db:seed
php artisan serve
```

Then open **http://127.0.0.1:8000** in your browser.

`composer setup` will:

1. Install PHP dependencies
2. Copy `.env.example` to `.env` (if missing)
3. Generate the application key
4. Run database migrations
5. Install npm packages and build frontend assets

> **Important:** Run `php artisan db:seed` after setup to create demo login accounts.

---

## Manual setup (step by step)

### 1. Clone and enter the project

```bash
cd SmartForum
```

### 2. Install PHP dependencies

```bash
composer install
```

If you see a `composer.lock` merge conflict error, resolve conflict markers in `composer.lock`, then run:

```bash
composer update --lock
composer install
```

### 3. Environment file

```bash
cp .env.example .env   # Windows: copy .env.example .env
php artisan key:generate
```

### 4. Database

#### Option A — SQLite (default, easiest for local dev)

The project is preconfigured for SQLite in `.env.example`:

```env
DB_CONNECTION=sqlite
```

Create the database file and migrate:

```bash
# Windows PowerShell
New-Item -ItemType File -Path database\database.sqlite -Force

php artisan migrate
```

#### Option B — MySQL

Update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=smart_forum
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

Create the database in MySQL, then:

```bash
php artisan migrate
```

> Use port `3306` if that is what your MySQL instance listens on.

### 5. Seed demo users

```bash
php artisan db:seed
```

### 6. Frontend assets

```bash
npm install
npm run build
```

Without this step, the login page will fail with a Vite manifest error.

### 7. Start the application

```bash
php artisan serve
```

Visit **http://127.0.0.1:8000** — you will be redirected to the login page.

---

## Demo accounts

All seeded accounts use the password **`password`**.

| Role | Email |
|------|-------|
| Student | `student@smartforum.com` |
| Lecturer | `lecturer@smartforum.com` |
| Admin | `admin@smartforum.com` |
| Test student | `test@example.com` |

You can also register a new account from the **Register** tab on the login page.

---

## Development mode (with hot reload)

For CSS/JS live reload during development, run:

```bash
composer dev
```

This starts the PHP server, queue worker, log viewer, and Vite dev server together.

Or run them separately in two terminals:

```bash
php artisan serve
npm run dev
```

---

## Typical usage flow

1. **Lecturer** logs in → creates a **group** → creates **topics** inside the group.
2. **Student** logs in → joins a lecturer's group → views topics → posts and replies.
3. **Admin** logs in → views dashboard statistics and manages the platform.

### Main routes

| URL | Description |
|-----|-------------|
| `/login` | Login and registration |
| `/dashboard` | Role-based dashboard (after login) |
| `/groups` | Browse and manage groups |
| `/topics/search` | Search topics |

---

## Running tests

```bash
php artisan test
```

Or:

```bash
composer test
```

---

## Troubleshooting

### `vendor/autoload.php` not found

```bash
composer install
```

### Login page shows a 500 / Vite error

Build frontend assets:

```bash
npm install
npm run build
```

### "Credentials do not match" on login

Seed the database:

```bash
php artisan db:seed
```

### `bootstrap/cache` must be present and writable

```bash
# Windows PowerShell
attrib -R bootstrap\cache /S /D
```

### MySQL connection refused

- Confirm MySQL is running.
- Check the port in `.env` (`3306` vs `3307`).
- Verify username and password.

### Dashboard or topic errors after fresh migrate

Ensure all migrations ran:

```bash
php artisan migrate
```

If issues persist on an old local database, reset SQLite (development only):

```bash
# Windows PowerShell — deletes local SQLite data
Remove-Item database\database.sqlite
New-Item -ItemType File -Path database\database.sqlite -Force
php artisan migrate
php artisan db:seed
```

---

## Project structure (high level)

```
app/                 Application logic (controllers, models, services)
database/
  migrations/        Database schema
  seeders/           Demo data
resources/
  views/             Blade templates
  sass/              Styles (Bootstrap)
  js/                Frontend scripts
routes/web.php       Web routes
public/              Web root (includes built assets in public/build)
```

---

## License

MIT
