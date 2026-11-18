#!/bin/sh
set -e
cd /var/www/html

echo "==> ODF backend starting"

# 1. .env (settings from docker-compose.yml override the file)
[ -f .env ] || cp .env.example .env

# 2. APP_KEY – generated once and kept in the storage volume,
#    so it survives container rebuilds (needed to read two-factor secrets).
if [ -z "$APP_KEY" ]; then
  KEY_FILE=storage/app/.app_key
  if [ ! -s "$KEY_FILE" ]; then
    php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$KEY_FILE"
    echo "==> Generated a new APP_KEY"
  fi
  APP_KEY=$(cat "$KEY_FILE")
  export APP_KEY
fi
php -r '
$path = ".env";
$contents = file_get_contents($path);
$key = getenv("APP_KEY");
if (!is_string($contents) || !is_string($key) || $key === "") {
    fwrite(STDERR, "!! Unable to read .env or APP_KEY\n");
    exit(1);
}
$updated = preg_replace_callback(
    "/^APP_KEY=.*$/m",
    static fn () => "APP_KEY=".$key,
    $contents,
    1,
    $count
);
if (!is_string($updated) || $count !== 1 || file_put_contents($path, $updated) === false) {
    fwrite(STDERR, "!! Unable to persist APP_KEY in .env\n");
    exit(1);
}
'

# 3. Wait for MySQL (max ~3 minutes)
echo "==> Waiting for MySQL at ${DB_HOST}:${DB_PORT} ..."
i=0
until php -r '
  try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); }
  catch (Throwable $e) { fwrite(STDERR, "    ".$e->getMessage()."\n"); exit(1); }'; do
  i=$((i+1))
  if [ "$i" -ge 90 ]; then
    echo "!! MySQL is not reachable. Check: docker compose logs mysql"
    exit 1
  fi
  sleep 2
done
echo "==> MySQL is ready"

# 4. Folders, tables and demo data (the seeder is safe to run again)
mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
         storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 777 storage bootstrap/cache
php artisan config:clear >/dev/null 2>&1 || true
php artisan migrate --force
php artisan db:seed --force
[ -L public/storage ] || php artisan storage:link

# 5. Serve the API
echo "==> API ready on http://localhost:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
