FROM php:8.4-fpm AS base

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libpng-dev default-mysql-client \
    && docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-install intl gd zip pdo pdo_mysql mysqli \
    && rm -rf /var/lib/apt/lists/*

# Устанавливаем Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Устанавливаем Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Копируем конфигурацию Xdebug
COPY ./docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Создаем директории для загрузок
RUN mkdir -p /var/www/html/public/uploads/avatars && \
    chown -R www-data:www-data /var/www/html/public/uploads && \
    chmod -R 755 /var/www/html/public/uploads

# Устанавливаем рабочую директорию
WORKDIR /var/www/html

FROM base AS dev

# Копируем исходный код
COPY ./src /var/www/html

# Устанавливаем правильные права
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Устанавливаем зависимости Composer
RUN composer install --no-dev --optimize-autoloader
