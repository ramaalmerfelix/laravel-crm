FROM mcr.microsoft.com/devcontainers/php:8.3-bookworm

# Matikan xdebug di CLI
RUN printf "xdebug.mode=off\n" > /usr/local/etc/php/conf.d/99-xdebug.ini

# Ekstensi yang dibutuhkan (pdo_mysql = driver MySQL)
RUN install-php-extensions \
    pdo_mysql gd zip calendar intl imap exif opcache

# (opsional) Node untuk Vite
RUN bash -lc "if ! command -v node >/dev/null; then \
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && apt-get install -y nodejs; fi"
