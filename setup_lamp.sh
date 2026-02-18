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

echo "=== Securing MySQL (set root password to empty for dev) ==="
sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY ''; FLUSH PRIVILEGES;" 2>/dev/null || true

echo "=== Creating database ==="
sudo mysql -u root -e "SOURCE $(pwd)/db.sql;" 2>/dev/null || \
    mysql -u root --password='' < "$(dirname "$0")/db.sql"

echo "=== Symlinking project to Apache web root ==="
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
sudo ln -sfn "$PROJECT_DIR" /var/www/html/nepdine

echo ""
echo "✅ Setup complete!"
echo "   Open: http://localhost/nepdine/login.php"
echo "   Default admin: admin@nepdine.com / password: Admin@1234"
echo ""
echo "⚠  If using WSL2, run this in a real Linux terminal or update"
echo "   config.php with your actual MySQL credentials."
