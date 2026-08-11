# Usar imagen oficial de PHP 8.2 con Apache
FROM php:8.2-apache

# Instalar dependencias del sistema y extensiones necesarias de PHP
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    unzip \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo pdo_mysql gd zip mbstring \
    && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite de Apache para manejo de URLs y redirecciones
RUN a2enmod rewrite

# Copiar ajustes de PHP para importaciones de archivos grandes (CSV/XLSX)
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Establecer directorio de trabajo en Apache
WORKDIR /var/www/html

# Copiar archivos del proyecto al contenedor
COPY . /var/www/html/

# Configurar permisos para carpetas y descargas
RUN mkdir -p /var/www/html/uploads/temp \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# Exponer el puerto 80 del servidor HTTP
EXPOSE 80

# Iniciar servidor Apache
CMD ["apache2-foreground"]
