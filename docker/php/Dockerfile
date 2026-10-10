# Pinned by digest (php 8.2.34-fpm on Debian 13 trixie, Python 3.13): the floating `php:8.2-fpm` tag moved under this file once already and changed
# the Python version. To upgrade deliberately: pull the new tag, copy its digest here, rebuild and run the tests.
FROM php:8.2-fpm@sha256:faf32167826a68cb7c595793e2e508164a24cac187be60ed8de9c81b17236048

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
    default-mysql-client \
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
# vnstock and vnai left PyPI: they are installed FIRST from local wheels (docker/python/wheelhouse, fetched by `sh docker/python/fetch-wheels.sh`,
# git-ignored because of their personal-use / proprietary licences) with --no-index, so a build never depends on the vnstock index being up.
# They must go first: vnstock_ezchart (on PyPI) requires vnstock, which PyPI no longer serves.
COPY docker/python/wheelhouse /tmp/py/wheelhouse
RUN ls /tmp/py/wheelhouse/vnstock-*.whl /tmp/py/wheelhouse/vnai-*.whl >/dev/null 2>&1 \
    || { echo 'ERROR: the vnstock / vnai wheels are missing. Run: sh docker/python/fetch-wheels.sh' >&2; exit 1; }
RUN pip install --no-cache-dir --no-deps --no-index --find-links /tmp/py/wheelhouse vnstock vnai

# Everything else comes from PyPI at the exact versions of docker/python/requirements.txt (vnstock / vnai above already satisfy their requirements).
COPY docker/python/requirements.txt /tmp/py/requirements.txt
RUN pip install --no-cache-dir -r /tmp/py/requirements.txt \
    && pip check \
    && rm -rf /tmp/py

# ── Environment ───────────────────────────────────────────────────────────────
ENV PYTHONIOENCODING=utf-8
ENV PYTHONDONTWRITEBYTECODE=1

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
