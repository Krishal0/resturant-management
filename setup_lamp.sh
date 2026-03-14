#!/bin/bash
# ============================================================
#  NepDine LAMP Setup Script for Ubuntu/Debian
#  Run: bash setup_lamp.sh
# ============================================================
set -e

echo "=== Updating packages ==="
sudo apt-get update -y

echo "=== Installing Apache2 ==="
sudo apt-get install -y apache2

echo "=== Installing MySQL Server ==="
sudo apt-get install -y mysql-server

echo "=== Installing PHP & extensions ==="
sudo apt-get install -y php libapache2-mod-php php-mysql php-mbstring php-bcmath

echo "=== Starting services ==="
sudo service apache2 start
sudo service mysql start

# --- DB credentials (override with env vars if needed) ---
DB_ROOT_USER="${DB_ROOT_USER:-root}"
DB_ROOT_PASS="${DB_ROOT_PASS:-}"
DB_NAME="${DB_NAME:-nepdine_db}"

echo "=== Creating database (${DB_NAME}) ==="
DB_SQL_PATH="$(cd "$(dirname "$0")" && pwd)/db.sql"

# Try passwordless via sudo (auth_socket). If that fails and a password is set, use it. Otherwise abort with a clear message.
if sudo mysql -u "$DB_ROOT_USER" -e "SELECT 1" >/dev/null 2>&1; then
    sudo mysql -u "$DB_ROOT_USER" < "$DB_SQL_PATH"
elif [ -n "$DB_ROOT_PASS" ]; then
    mysql -u "$DB_ROOT_USER" -p"$DB_ROOT_PASS" < "$DB_SQL_PATH"
else
    echo "!! MySQL root requires a password. Re-run with: DB_ROOT_PASS=yourpass bash setup_lamp.sh" >&2
    exit 1
fi

echo "=== Running DB migrations ==="
MIGRATION_SQL="
USE ${DB_NAME};
SET @db='${DB_NAME}';

SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='bills' AND COLUMN_NAME='payment_status'
);
SET @sql = IF(@col_exists = 0,
    \"ALTER TABLE bills ADD COLUMN payment_status ENUM('pending','success','failed') DEFAULT 'success'\",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='bills' AND COLUMN_NAME='esewa_pid'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE bills ADD COLUMN esewa_pid VARCHAR(100) UNIQUE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='bills' AND COLUMN_NAME='esewa_ref_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE bills ADD COLUMN esewa_ref_id VARCHAR(100)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
"

if sudo mysql -u "$DB_ROOT_USER" -e "SELECT 1" >/dev/null 2>&1; then
    echo "$MIGRATION_SQL" | sudo mysql -u "$DB_ROOT_USER"
elif [ -n "$DB_ROOT_PASS" ]; then
    echo "$MIGRATION_SQL" | mysql -u "$DB_ROOT_USER" -p"$DB_ROOT_PASS"
fi

echo "=== Symlinking project to Apache web root ==="
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
sudo ln -sfn "$PROJECT_DIR" /var/www/html/nepdine

echo ""
echo " Setup complete!"
echo "   Open: http://localhost/nepdine/login.php"
echo "   Default admin: admin@nepdine.com / password: password"
