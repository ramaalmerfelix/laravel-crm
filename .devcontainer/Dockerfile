# Base devcontainers PHP (Debian Bookworm)
FROM mcr.microsoft.com/devcontainers/php:8.3-bookworm

# Matikan Xdebug di CLI biar Composer/Artisan ngebut
RUN printf "xdebug.mode=off\n" > /usr/local/etc/php/conf.d/99-xdebug.ini

# Sistem & dev headers yang dibutuhkan untuk build ekstensi
# Tambah libicu-dev agar bisa build ekstensi intl (NumberFormatter)
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl ca-certificates gnupg \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev zlib1g-dev \
    libicu-dev \
 && rm -rf /var/lib/apt/lists/*

# Build ekstensi PHP
# - gd (dengan freetype & jpeg)
# - pdo_mysql (driver MySQL)
# - zip, calendar
# - intl (BUTUH libicu-dev)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" \
    pdo_mysql gd zip calendar intl

# (Opsional) Node.js buat Vite/asset build
# Script NodeSource akan setup repo dan apt-get update sendiri
RUN bash -lc "if ! command -v node >/dev/null 2>&1; then \
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y --no-install-recommends nodejs && \
    rm -rf /var/lib/apt/lists/*; \
  fi"
