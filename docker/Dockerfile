FROM php:7.4-apache

USER root
WORKDIR /var/www/html

ENV TZ=America/Sao_Paulo
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update && apt-get install -y \
    libpng-dev \
    zlib1g-dev \
    libxml2-dev \
    libzip-dev \
    libonig-dev \
    zip \
    curl \
    unzip \
    supervisor \
    wget \
    gnupg \
    build-essential \
    libssl-dev \
    git \
    && docker-php-ext-configure gd \
    && docker-php-ext-install -j$(nproc) gd zip pcntl pdo_mysql mysqli mbstring exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
    
RUN chown -R www-data:www-data /var/www/html

