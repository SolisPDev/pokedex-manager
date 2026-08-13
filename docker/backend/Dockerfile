FROM php:8.2-cli

# Instalar dependencias del sistema y extensiones de PHP necesarias
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-install pdo_pgsql pgsql zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar el código del backend al contenedor
COPY backend/ /var/www/html

# Instalar dependencias de composer
RUN composer install --no-interaction --optimize-autoloader

# Exponer puerto para artisan serve
EXPOSE 8000

# El comando se pasará por compose, pero definimos uno por defecto para desarrollo
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}

