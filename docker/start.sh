#!/bin/sh
# Container start-up: listen on the port the host gives us, set up the database, start Apache.
set -e
PORT="${PORT:-80}"
ROOT=/var/www/html/backend/public
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i -e "s/__PORT__/${PORT}/" -e "s#__ROOT__#${ROOT}#g" /etc/apache2/sites-available/000-default.conf

# Runs new migrations; inserts the sample data only if the database is still empty,
# so restarts/redeploys never wipe real orders.
php /var/www/html/backend/database/migrate.php --seed-if-empty

exec apache2-foreground
