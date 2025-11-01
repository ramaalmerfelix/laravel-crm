# Base devcontainers PHP resmi (Debian Bookworm)
FROM mcr.microsoft.com/devcontainers/php:8.3-bookworm

# Matikan xdebug di CLI biar Composer/Artisan nggak lambat
RUN printf "xdebug.mode=off\n" > /usr/local/etc/php/conf.d/99-xdebug.ini

# Dependensi build untuk ekstensi
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

# GD perlu configure; yang lain langsung install
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip calendar

# (opsional) Node.js buat Vite
RUN bash -lc "if ! command -v node >/dev/null; then \
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && apt-get install -y nodejs; fi"
