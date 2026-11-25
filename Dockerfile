# Single image: builds the React app, then serves it together with the PHP API from Apache.

# ---- 1. Build the frontend ----
FROM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# ---- 2. PHP + Apache runtime ----
FROM php:8.3-apache
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers

WORKDIR /var/www/html
COPY backend/ backend/
# The React build goes next to index.php so Apache serves both from one folder.
COPY --from=frontend /app/dist/ backend/public/
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh && chown -R www-data:www-data backend/public/images

ENV APP_DEBUG=false
CMD ["start.sh"]
