#!/usr/bin/env python3
import io
import os
import sys
import tarfile
import paramiko

HOST = os.environ.get("SF_VPS_HOST", "147.224.178.246")
USER = os.environ.get("SF_VPS_USER", "dockeruser")
PASSWORD = os.environ.get("SF_VPS_PASSWORD")
APP_DIR = "/var/www/smartforum"
LOCAL_ROOT = os.path.dirname(os.path.abspath(__file__))

SKIP_DIRS = {
    ".git",
    "node_modules",
    "vendor",
    "DESKTOP-CLIENT",
    ".devcontainer",
}
SKIP_FILES = {".env"}


def run(client, cmd, timeout=300):
    print(f"\n>>> {cmd}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=timeout, get_pty=True)
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    code = stdout.channel.recv_exit_status()
    if out.strip():
        try:
            print(out.rstrip())
        except UnicodeEncodeError:
            print(out.encode("ascii", errors="replace").decode("ascii").rstrip())
    if err.strip():
        print(err.rstrip(), file=sys.stderr)
    print(f"[exit {code}]")
    return code, out, err


def should_skip(path: str) -> bool:
    normalised = path.replace("\\", "/")
    parts = normalised.split("/")
    if any(part in SKIP_DIRS for part in parts):
        return True
    if os.path.basename(path) in SKIP_FILES:
        return True
    if "/storage/logs/" in normalised:
        return True
    # Vite's hot file makes production load assets from a dev server on :5173.
    if normalised.endswith("/public/hot"):
        return True
    return False


def clone_project(client):
    repo = "https://github.com/anifajudyonorio-star/SmartForum.git"
    run(client, f"sudo rm -rf {APP_DIR}")
    code, _, _ = run(client, f"sudo git clone {repo} {APP_DIR}", timeout=300)
    if code != 0:
        sys.exit(code)
    run(client, f"sudo chown -R {USER}:www-data {APP_DIR}")


def upload_project(client):
    print("Creating deployment archive...")
    buf = io.BytesIO()
    with tarfile.open(fileobj=buf, mode="w:gz") as tar:
        for root, dirs, files in os.walk(LOCAL_ROOT):
            dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
            rel_root = os.path.relpath(root, LOCAL_ROOT)
            if rel_root == ".":
                rel_root = ""
            for name in files:
                full = os.path.join(root, name)
                rel = os.path.join(rel_root, name).replace("\\", "/")
                if should_skip(full):
                    continue
                tar.add(full, arcname=rel)
    data = buf.getvalue()
    print(f"Archive size: {len(data) / 1024 / 1024:.1f} MB")

    sftp = client.open_sftp()
    remote_tar = "/tmp/smartforum-deploy.tar.gz"
    print(f"Uploading to {remote_tar}...")
    with sftp.file(remote_tar, "wb") as f:
        f.write(data)
    sftp.close()

    run(client, f"sudo mkdir -p {APP_DIR} && sudo rm -rf {APP_DIR}/*")
    run(client, f"sudo tar -xzf {remote_tar} -C {APP_DIR}")
    run(client, f"rm -f {remote_tar}")
    run(client, f"sudo rm -f {APP_DIR}/public/hot")
    run(
        client,
        f"sudo mkdir -p {APP_DIR}/storage/logs "
        f"{APP_DIR}/storage/framework/cache/data "
        f"{APP_DIR}/storage/framework/sessions "
        f"{APP_DIR}/storage/framework/views "
        f"{APP_DIR}/bootstrap/cache",
    )
    run(client, f"sudo chown -R {USER}:www-data {APP_DIR}")
    # php-fpm runs as www-data and must be able to write logs, sessions and caches.
    run(client, f"sudo chmod -R 775 {APP_DIR}/storage {APP_DIR}/bootstrap/cache")


def configure_app(client):
    script = rf"""
set -e
APP_DIR={APP_DIR}
DB_NAME=smart_forum
DB_USER=smartforum
DB_PASS='SfDeploy2026!'
PHP_VER='8.4'

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
fi

if ! php8.4 -v >/dev/null 2>&1; then
  if sudo fuser /var/lib/apt/lists/lock >/dev/null 2>&1; then
    echo "Clearing stale apt lock..."
    sudo kill -9 2140848 2>/dev/null || true
    sudo rm -f /var/lib/apt/lists/lock /var/cache/apt/archives/lock /var/lib/dpkg/lock-frontend /var/lib/dpkg/lock
    sudo dpkg --configure -a || true
  fi
  sudo apt-get install -y software-properties-common
  sudo add-apt-repository -y ppa:ondrej/php
  sudo apt-get update -qq
  sudo apt-get install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-gd php8.4-intl php8.4-fileinfo supervisor
fi

sudo update-alternatives --set php /usr/bin/php8.4 2>/dev/null || true

sudo mysql -e "CREATE DATABASE IF NOT EXISTS ${{DB_NAME}} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '${{DB_USER}}'@'localhost' IDENTIFIED BY '${{DB_PASS}}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON ${{DB_NAME}}.* TO '${{DB_USER}}'@'localhost'; FLUSH PRIVILEGES;"

cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
sudo rm -f public/hot

if [ ! -f .env ]; then
  cp .env.example .env
fi

python3 - <<'PY'
from pathlib import Path
p = Path('.env')
text = p.read_text()
updates = {{
    'APP_NAME': 'SmartForum',
    'APP_ENV': 'production',
    'APP_DEBUG': 'false',
    'APP_URL': 'http://147.224.178.246/forum',
    'DB_CONNECTION': 'mysql',
    'DB_HOST': '127.0.0.1',
    'DB_PORT': '3306',
    'DB_DATABASE': 'smart_forum',
    'DB_USERNAME': 'smartforum',
    'DB_PASSWORD': 'SfDeploy2026!',
    'SESSION_DRIVER': 'database',
    'QUEUE_CONNECTION': 'database',
    'CACHE_STORE': 'database',
    'ML_SERVICE_URL': 'http://127.0.0.1:5001',
}}
lines = text.splitlines()
keys_done = set()
new_lines = []
for line in lines:
    if '=' in line and not line.strip().startswith('#'):
        key = line.split('=', 1)[0].strip()
        if key in updates:
            new_lines.append(f"{{key}}={{updates[key]}}")
            keys_done.add(key)
            continue
    new_lines.append(line)
for key, val in updates.items():
    if key not in keys_done:
        new_lines.append(f"{{key}}={{val}}")
p.write_text('\n'.join(new_lines) + '\n')
PY

php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force || true
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo chown -R dockeruser:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

sudo tee /etc/nginx/sites-available/smartforum > /dev/null <<'NGINX'
server {{
    listen 8082;
    listen [::]:8082;
    server_name 147.224.178.246 _;
    root /var/www/smartforum/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {{
        try_files $uri $uri/ /index.php?$query_string;
    }}

    location = /favicon.ico {{ access_log off; log_not_found off; }}
    location = /robots.txt  {{ access_log off; log_not_found off; }}

    error_page 404 /index.php;

    location ~ \\.php$ {{
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }}

    location ~ /\\.(?!well-known).* {{
        deny all;
    }}
}}
NGINX

sudo ln -sf /etc/nginx/sites-available/smartforum /etc/nginx/sites-enabled/smartforum
sudo nginx -t
sudo systemctl start php8.4-fpm
sudo systemctl enable php8.4-fpm
sudo systemctl reload nginx
sudo ufw allow 8082/tcp 2>/dev/null || true

sudo tee /etc/supervisor/conf.d/smartforum-worker.conf > /dev/null <<'SUP'
[program:smartforum-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/smartforum/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=dockeruser
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/smartforum/storage/logs/worker.log
stopwaitsecs=3600
SUP

sudo systemctl enable supervisor 2>/dev/null || true
sudo systemctl start supervisor 2>/dev/null || true
sudo supervisorctl reread 2>/dev/null || true
sudo supervisorctl update 2>/dev/null || true
sudo supervisorctl start smartforum-worker:* 2>/dev/null || true

( crontab -l 2>/dev/null | grep -v 'smartforum.*schedule:run'; echo '* * * * * cd /var/www/smartforum && php artisan schedule:run >> /dev/null 2>&1' ) | crontab -

echo DEPLOY_COMPLETE
curl -sI http://127.0.0.1:8082/login | head -5
"""
    return run(client, script, timeout=1800)


def main():
    action = sys.argv[1] if len(sys.argv) > 1 else "explore"

    if not PASSWORD:
        sys.exit("Set SF_VPS_PASSWORD in the environment before running this script.")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

    if action == "explore":
        cmds = [
            "composer --version 2>/dev/null || echo NO_COMPOSER",
            "php -m",
            "systemctl is-active nginx php8.3-fpm mysql || true",
            "ls -la /etc/nginx/sites-enabled/",
            "sudo mysql -e 'SHOW DATABASES;'",
            "cat /etc/nginx/sites-available/askmak 2>/dev/null",
            "cat /etc/nginx/sites-available/backends 2>/dev/null",
            "ss -tlnp",
        ]
        for c in cmds:
            run(client, c)

    elif action == "status":
        cmds = [
            "ps aux | grep -E 'git|composer|npm|apt' | grep -v grep || echo no_install_process",
            "ls -la /var/www/smartforum 2>/dev/null | head -15 || echo no_app_dir",
            "test -f /var/www/smartforum/public/index.php && echo app_present || echo app_missing",
            "curl -sI http://127.0.0.1:8082/login 2>/dev/null | head -8 || echo curl_failed",
            "ss -tlnp | grep 8082 || echo port_8082_not_listening",
            "systemctl is-active nginx php8.4-fpm php8.3-fpm mysql supervisor 2>/dev/null || true",
            "tail -20 /var/www/smartforum/storage/logs/laravel.log 2>/dev/null || echo no_laravel_log",
        ]
        for c in cmds:
            run(client, c)

    elif action == "fix-git":
        cmds = [
            "sudo pkill -f 'git clone.*SmartForum' || true",
            "sudo rm -rf /var/www/smartforum",
            "timeout 60 git ls-remote https://github.com/anifajudyonorio-star/SmartForum.git HEAD || echo git_unreachable",
        ]
        for c in cmds:
            run(client, c)

    elif action == "diagnose":
        cmds = [
            "ls -la /etc/nginx/sites-enabled/",
            "grep -n listen /etc/nginx/sites-available/smartforum 2>/dev/null || echo no_smartforum_conf",
            "grep -E '^(APP_|DB_)' /var/www/smartforum/.env",
            "sudo mysql -e 'SHOW DATABASES LIKE \"smart_forum\";'",
            "test -d /var/www/smartforum/vendor && echo vendor_ok || echo vendor_missing",
            "test -f /var/www/smartforum/public/build/manifest.json && echo vite_ok || echo vite_missing",
        ]
        for c in cmds:
            run(client, c)

    elif action == "finish":
        code, _, _ = run(client, rf"""
set -e
cd {APP_DIR}
cp .env.example .env
sed -i 's/^APP_NAME=.*/APP_NAME=SmartForum/' .env
sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env
sed -i 's|^APP_URL=.*|APP_URL=http://147.224.178.246/forum|' .env
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env
sed -i 's/^# DB_HOST=.*/DB_HOST=127.0.0.1/' .env
sed -i 's/^# DB_PORT=.*/DB_PORT=3306/' .env
sed -i 's/^# DB_DATABASE=.*/DB_DATABASE=smart_forum/' .env
sed -i 's/^# DB_USERNAME=.*/DB_USERNAME=smartforum/' .env
sed -i 's/^# DB_PASSWORD=.*/DB_PASSWORD=SfDeploy2026!/' .env
grep -q '^DB_HOST=' .env || echo 'DB_HOST=127.0.0.1' >> .env
grep -q '^DB_PORT=' .env || echo 'DB_PORT=3306' >> .env
grep -q '^DB_DATABASE=' .env || echo 'DB_DATABASE=smart_forum' >> .env
grep -q '^DB_USERNAME=' .env || echo 'DB_USERNAME=smartforum' >> .env
grep -q '^DB_PASSWORD=' .env || echo 'DB_PASSWORD=SfDeploy2026!' >> .env
php artisan key:generate --force
grep -E '^(APP_|DB_)' .env
php artisan config:clear
php artisan migrate --force
php artisan db:seed --force || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo tee /etc/nginx/sites-available/smartforum > /dev/null <<'NGINX'
server {{
    listen 8082;
    listen [::]:8082;
    server_name 147.224.178.246 _;
    root /var/www/smartforum/public;
    index index.php;
    location / {{ try_files $uri $uri/ /index.php?$query_string; }}
    location ~ \\.php$ {{
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }}
}}
NGINX
sudo ln -sf /etc/nginx/sites-available/smartforum /etc/nginx/sites-enabled/smartforum
sudo nginx -t
sudo systemctl reload nginx
curl -sI http://127.0.0.1:8082/login | head -8
echo FINISH_COMPLETE
""", timeout=600)
        sys.exit(code)

    elif action == "push-file":
        rel = sys.argv[2]
        local = os.path.join(LOCAL_ROOT, rel)
        remote = f"{APP_DIR}/{rel}"
        sftp = client.open_sftp()
        tmp = f"/tmp/{os.path.basename(rel)}"
        sftp.put(local, tmp)
        sftp.close()
        run(client, f"sudo cp {tmp} {remote} && rm -f {tmp}")
        run(client, f"sudo chown {USER}:www-data {remote}")
        run(client, rf"""
set -e
cd {APP_DIR}
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.4-fpm
curl -s http://127.0.0.1/forum/login | grep -o 'href="[^"]*app-[^"]*css"' | head -3
echo PUSH_COMPLETE
""", timeout=300)

    elif action == "fix-services":
        code, _, _ = run(client, rf"""
set -e
rm -f {APP_DIR}/smart_forum
sudo tee /etc/supervisor/conf.d/smartforum-worker.conf > /dev/null <<'SUP'
[program:smartforum-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/smartforum/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=dockeruser
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/smartforum/storage/logs/worker.log
stopwaitsecs=3600
SUP
sudo systemctl enable supervisor
sudo systemctl start supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart smartforum-worker:* || sudo supervisorctl start smartforum-worker:*
CRONTMP=$(mktemp)
crontab -l 2>/dev/null | grep -v 'smartforum.*schedule:run' > "$CRONTMP" || true
echo '* * * * * cd /var/www/smartforum && php artisan schedule:run >> /dev/null 2>&1' >> "$CRONTMP"
crontab "$CRONTMP"
rm -f "$CRONTMP"
echo '--- supervisor ---'
sudo supervisorctl status
echo '--- crontab ---'
sudo grep -c 'schedule:run' /var/spool/cron/crontabs/dockeruser
echo '--- scheduler dry run ---'
cd {APP_DIR} && php artisan schedule:run
echo SERVICES_OK
""", timeout=300)
        sys.exit(code)

    elif action == "facts":
        cmds = [
            "cd /var/www/smartforum && git branch --show-current && git remote get-url origin",
            "cd /var/www/smartforum && git status --porcelain",
            "php -v | head -1; which php composer node npm",
            "sudo supervisorctl status",
            "sudo cat /var/spool/cron/crontabs/dockeruser | cat -A | tail -5",
            "systemctl is-active cron",
            "stat -c '%U:%G' /var/www/smartforum /var/www/smartforum/storage",
        ]
        for c in cmds:
            run(client, c)

    elif action == "smoke-test":
        run(client, r"""
cd /tmp && rm -f sf-cookies.txt
BASE=http://127.0.0.1/forum
TOKEN=$(curl -s -c sf-cookies.txt "$BASE/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
echo "TOKEN_LEN=${#TOKEN}"
echo "LOGIN_REDIRECT=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b sf-cookies.txt -c sf-cookies.txt -X POST "$BASE/login" -d "_token=$TOKEN" -d 'email=admin@smartforum.com' -d 'password=password')"
DASH=$(curl -s -b sf-cookies.txt -c sf-cookies.txt -L "$BASE/dashboard")
echo "DASHBOARD_BYTES=$(echo "$DASH" | wc -c)"
echo "UNPREFIXED_ASSETS=$(echo "$DASH" | grep -oE '(href|src)="https?://[^"]*/(build|storage)/[^"]*"' | grep -v '/forum/' | wc -l)"
echo "PREFIXED_ASSETS=$(echo "$DASH" | grep -oE '(href|src)="[^"]*/forum/(build|storage)/[^"]*"' | wc -l)"
rm -f sf-cookies.txt
echo SMOKE_DONE
""", timeout=180)

    elif action == "check-proxy":
        cmds = [
            "grep -n 'HEADER_X_FORWARDED' /var/www/smartforum/vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php",
            "grep -n 'X-Forwarded-Prefix' /etc/nginx/sites-available/askmak",
            "grep -n 'trustProxies' /var/www/smartforum/bootstrap/app.php || echo no_trustproxies",
        ]
        for c in cmds:
            run(client, c)

    elif action == "expose-80":
        code, _, _ = run(client, rf"""
set -e
cd {APP_DIR}
sed -i 's|^APP_URL=.*|APP_URL=http://147.224.178.246/forum|' .env
php artisan config:cache
php artisan route:cache
sudo python3 - <<'PY'
from pathlib import Path
path = Path('/etc/nginx/sites-available/askmak')
text = path.read_text()
block = '''
    # SmartForum
    location /forum/ {{
        proxy_pass http://127.0.0.1:8082/;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Prefix /forum;
    }}

'''
if 'location /forum/' not in text:
    text = text.replace('    location / {{', block + '    location / {{', 1)
    path.write_text(text)
PY
sudo nginx -t
sudo systemctl reload nginx
curl -sI http://127.0.0.1/forum/login | head -8
echo EXPOSE_COMPLETE
""", timeout=120)
        sys.exit(code)

    elif action == "open-firewall":
        cmds = [
            "sudo ufw status verbose || true",
            "sudo ufw allow 8082/tcp",
            "sudo iptables -I INPUT -p tcp --dport 8082 -j ACCEPT 2>/dev/null || true",
            "ss -tlnp | grep 8082",
        ]
        for c in cmds:
            run(client, c)

    elif action == "upload":
        upload_project(client)

    elif action == "configure":
        code, _, _ = configure_app(client)
        sys.exit(code)

    elif action == "deploy":
        clone_project(client)
        code, _, _ = configure_app(client)
        sys.exit(code)

    client.close()


if __name__ == "__main__":
    main()

