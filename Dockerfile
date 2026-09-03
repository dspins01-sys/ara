FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chown -R root:root /var/www/html \
    && mkdir -p /var/www/html/data /var/www/html/public/uploads

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

EXPOSE 80
