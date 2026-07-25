# Updating SmartForum on the VPS

How to push code changes to the live server after you edit the project.

Live URL: **http://147.224.178.246/forum/login**

---

## Server at a glance

| Item | Value |
|------|-------|
| SSH | `ssh dockeruser@147.224.178.246` |
| App directory | `/var/www/smartforum` |
| Branch deployed | `main` |
| PHP | 8.4 (`php8.4-fpm`) |
| Web server | Nginx |
| Database | MySQL, database `smart_forum` |
| Queue worker | Supervisor program `smartforum-worker` |
| Scheduler | Cron runs `schedule:run` every minute |
| Files owned by | `dockeruser:www-data` |

The app itself listens on port **8082** (localhost only). Nginx serves it publicly
at **`/forum/`** on port 80, because port 80 is shared with another application and
Oracle Cloud blocks other ports at the network level.

---

## The normal update

Do this whenever you change code. Commit and push from your machine first, then pull on the server.

**1. On your computer:**

```bash
git add .
git commit -m "Describe your change"
git push origin main
```

**2. On the server:**

```bash
ssh dockeruser@147.224.178.246
cd /var/www/smartforum

git pull origin main

composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo supervisorctl restart smartforum-worker:*
```

Then hard-refresh your browser (**Ctrl+F5**) so it doesn't reuse the old CSS and JS.

---

## Shorter updates

You don't always need every step. Pick based on what you changed.

**Blade views, CSS, JS, or anything in `resources/`:**

```bash
cd /var/www/smartforum && git pull origin main
npm run build
php artisan view:cache
```

**PHP only (controllers, models, services), no new packages:**

```bash
cd /var/www/smartforum && git pull origin main
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart smartforum-worker:*
```

**You added a Composer package:**

```bash
cd /var/www/smartforum && git pull origin main
composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan route:cache
```

**You added a migration:**

```bash
cd /var/www/smartforum && git pull origin main
php artisan migrate --force
```

The `--force` flag is required; without it Laravel refuses to migrate in production.

---

## Changing environment settings

`.env` is **not** in git and must be edited on the server:

```bash
nano /var/www/smartforum/.env
php artisan config:cache
```

Always re-run `config:cache` after editing `.env`. While the config cache exists,
Laravel ignores the `.env` file until it is rebuilt.

Do not change `APP_URL`. It must stay `http://147.224.178.246/forum` or links and
assets will break.

### Email verification (SMTP)

Registration codes only reach inboxes when SMTP is configured. `MAIL_MAILER=log`
writes messages to `storage/logs/laravel.log` and never delivers them.

On the server `.env` (Gmail example — use an [App Password](https://myaccount.google.com/apppasswords), not your normal password):

```env
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=xxxx xxxx xxxx xxxx
MAIL_FROM_ADDRESS=your@gmail.com
MAIL_FROM_NAME=SmartForum
```

Use port **587** (not 465). Port 465 is often blocked on cellular links and some cloud networks.

Then rebuild config:

```bash
php artisan config:cache
php artisan tinker --execute="Mail::raw('SmartForum mail test', fn (\$m) => \$m->to('your@gmail.com')->subject('Test'));"
```

Apply the same `MAIL_*` values in your local `.env` (no `config:cache` needed locally).

---

## Avoiding downtime during bigger updates

For updates with migrations or dependency changes:

```bash
cd /var/www/smartforum
php artisan down

git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart smartforum-worker:*

php artisan up
```

Visitors see a maintenance page between `down` and `up` instead of half-updated code.

---

## Checking that it worked

```bash
curl -I http://127.0.0.1/forum/login          # expect HTTP/1.1 200 OK
sudo supervisorctl status                      # expect smartforum-worker RUNNING
tail -30 /var/www/smartforum/storage/logs/laravel.log
```

From your own machine, confirm the site is reachable publicly:

```bash
curl -I http://147.224.178.246/forum/login
```

---

## Rolling back a bad deploy

```bash
cd /var/www/smartforum
git log --oneline -5             # find the last good commit
git reset --hard <commit-hash>

composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart smartforum-worker:*
```

Migrations are not undone by this. If the bad deploy added a migration, roll it back
first with `php artisan migrate:rollback --step=1`.

---

## Troubleshooting

**Styles missing or the page looks unstyled**

Usually the browser cache. Hard-refresh with Ctrl+F5. If it persists, rebuild assets
and confirm the URLs include the `/forum` prefix:

```bash
cd /var/www/smartforum
npm run build
php artisan view:cache
curl -s http://127.0.0.1/forum/login | grep -o 'href="[^"]*\.css"'
```

Asset URLs must contain `/forum/build/...`. If the `/forum` part is missing, the
trusted-proxy setting in `bootstrap/app.php` was lost — see the note at the bottom.

**500 error after deploying**

```bash
tail -50 /var/www/smartforum/storage/logs/laravel.log
```

A common cause is a stale cache referencing code that no longer exists:

```bash
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Permission denied writing to storage or cache**

```bash
sudo chown -R dockeruser:www-data /var/www/smartforum/storage /var/www/smartforum/bootstrap/cache
sudo chmod -R 775 /var/www/smartforum/storage /var/www/smartforum/bootstrap/cache
```

**Jobs, emails, or notifications not processing**

```bash
sudo supervisorctl status
sudo supervisorctl restart smartforum-worker:*
tail -30 /var/www/smartforum/storage/logs/worker.log
```

The worker loads code into memory at startup, so it must be restarted after **every**
deploy or it keeps running the old code.

**Scheduled inactivity checks not running**

```bash
crontab -l                       # should list schedule:run
systemctl is-active cron
php artisan schedule:run         # run once by hand to see errors
```

**`git pull` refuses to run because of local changes**

```bash
cd /var/www/smartforum
git status                       # see what differs
git checkout -- <file>           # discard the server-side edit
git pull origin main
```

---

## Optional: run updates from your computer

`deploy-remote.py` runs these steps over SSH from Windows so you don't have to log
in manually. It reads the server password from an environment variable:

```powershell
$env:SF_VPS_PASSWORD = "your-vps-password"

python deploy-remote.py status         # health check
python deploy-remote.py smoke-test     # login + dashboard test
python deploy-remote.py fix-services   # repair queue worker and cron
```

Never put the password back into the file itself. The repository is public.

---

## Two things to settle before your next push

**1. The server has an uncommitted edit to `bootstrap/app.php`.**

That file trusts the local reverse proxy. Without it Laravel drops the `/forum`
prefix and all CSS, JS, and links break. The same change is already committed on
your machine, so once you push, replace the server's copy so future pulls are clean:

```bash
# on your computer
git push origin main

# on the server
cd /var/www/smartforum
git checkout -- bootstrap/app.php
git pull origin main
php artisan config:cache
```

Until then, `git pull` on the server will report a conflict on that file.

**2. Your VPS password is inside an unpushed commit.**

`deploy-remote.py` was committed while it still contained the password in plain
text. That commit has not been pushed, so nothing is public yet, but pushing as-is
would publish the password in a public repository.

The file no longer contains the password, but the old commit still does. Pick one:

```bash
# Option A - squash the credential out of the unpushed commit
git add deploy-remote.py
git commit --amend --no-edit

# Option B - keep history, and change the password on the server instead
ssh dockeruser@147.224.178.246 passwd
```

Changing the server password is worth doing regardless, since it has also been
typed into chat.
