FROM php:8.2-fpm

# ── System dependencies ───────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    supervisor \
    procps \
    python3 \
    python3-pip \
    python3-venv \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ────────────────────────────────────────────────────────────
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        xml \
        intl \
        opcache

# ── Redis PHP extension ───────────────────────────────────────────────────────
RUN pecl install redis && docker-php-ext-enable redis

# ── Composer ─────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Python virtual environment + vnstock ──────────────────────────────────────
# Use venv to avoid Debian "externally managed environment" restriction
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"
RUN pip install --no-cache-dir vnstock pandas

# ── Environment ───────────────────────────────────────────────────────────────
ENV PYTHONIOENCODING=utf-8
ENV PYTHONDONTWRITEBYTECODE=1

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
