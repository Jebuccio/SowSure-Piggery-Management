FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install curl mbstring \
    && a2enmod headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/sowsure
COPY . .

RUN sed -ri 's!/var/www/html!/var/www/sowsure/public!g' /etc/apache2/sites-available/*.conf \
    && chown -R www-data:www-data /var/www/sowsure

EXPOSE 80
