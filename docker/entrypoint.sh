#!/bin/bash
set -e

echo "⏳ Waiting for MySQL..."
until php -r "
try {
    new PDO('mysql:host=${DB_HOST};dbname=${DB_NAME}', '${DB_USER}', '${DB_PASS}');
    echo 'ok';
} catch(Exception \$e) { exit(1); }
" 2>/dev/null | grep -q ok; do
    echo "   still waiting..."
    sleep 3
done

echo "✅ MySQL ready!"
echo "👤 Creating demo accounts..."
php /var/www/html/create_accounts.php
echo "🚀 Starting Apache..."
exec apache2-foreground
