#!/bin/bash
set -eu
cd /var/www/html

export CI_ENVIRONMENT="${CI_ENVIRONMENT:-production}"

echo "Waiting for MySQL..."
ready=0
for _ in $(seq 1 40); do
  if php -r '
    mysqli_report(MYSQLI_REPORT_OFF);
    $host = getenv("DB_HOST") ?: "mysql";
    $user = getenv("DB_USERNAME") ?: "brewsbook";
    $pass = getenv("DB_PASSWORD") ?: "";
    $name = getenv("DB_DATABASE") ?: "brewsbook";
    $port = (int) (getenv("DB_PORT") ?: 3306);
    $mysqli = @mysqli_connect($host, $user, $pass, $name, $port);
    if (!$mysqli) {
        fwrite(STDERR, mysqli_connect_error() . PHP_EOL);
        exit(1);
    }
    exit(0);
  '; then
    ready=1
    break
  fi
  sleep 2
done

if [ "$ready" != "1" ]; then
  echo "MySQL did not become ready" >&2
  exit 1
fi

echo "Applying database migrations..."
php spark migrate --all
chown -R www-data:www-data writable

exec apache2-foreground
