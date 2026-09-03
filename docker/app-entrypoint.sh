#!/bin/sh
set -eu

if [ ! -x vendor/bin/phinx ]; then
    echo "Phinx is missing from vendor; installing Composer dependencies..."
    composer install --prefer-source --no-interaction
fi

echo "Waiting for database at ${MARIADB_HOST:-mysql}:${MARIADB_PORT:-3306}..."
until php -r '
$host = getenv("MARIADB_HOST") ?: "mysql";
$port = getenv("MARIADB_PORT") ?: "3306";
$db = getenv("MARIADB_DATABASE") ?: "lumio_db";
$user = getenv("MARIADB_USER") ?: "root";
$pass = getenv("MARIADB_PASSWORD") ?: getenv("MYSQL_ROOT_PASSWORD") ?: "";
try {
    new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
'; do
    sleep 2
done

echo "Running database migrations..."
vendor/bin/phinx migrate --configuration phinx.php --no-interaction

exec "$@"
