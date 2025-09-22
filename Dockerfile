ARG PHP_VERSION="8.3"

FROM php:${PHP_VERSION}-fpm AS php

RUN apt-get update -y && \
    apt-get install -y --no-install-recommends \
    acl \
    autoconf \
    bash \
    ca-certificates \
    cron \
    curl \
    dialog \
    gcc \
    git \
    imagemagick \
    libgcrypt20-dev \
    libffi-dev \
    libgsasl7-dev \
    libmagickwand-dev \
    libmcrypt-dev \
    libpq-dev \
    libpng-dev \
    librabbitmq-dev \
    libssl-dev \
    libxml2-dev \
    libxslt1-dev \
    libzip-dev \
    make \
    netcat-traditional \
    openssh-client \
    patch \
    procps \
    ssmtp \
    supervisor \
    vim \
    zip \
    unzip && \
    apt-get purge --autoremove -y gnupg && \
    apt-get clean -y && \
    rm -rf \
    /tmp/* \
    /usr/share/doc/* \
    /usr/share/man/* \
    /var/lib/apt/lists/* \
    /var/cache/* \
    /var/log/*  \
    /var/tmp/*

RUN pecl channel-update pecl.php.net && \
    pecl install -o -f amqp apcu imagick && \
    docker-php-ext-configure gd \
    --prefix=/usr \
    --with-jpeg \
    --with-webp \
    --with-freetype; \
    docker-php-ext-configure zip && \
    docker-php-ext-install \
    ctype \
    intl \
    gd \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pgsql \
    zip && \
    docker-php-ext-enable amqp apcu imagick opcache zip && \
    docker-php-source delete

RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer && \
    curl -sS -L -o /usr/local/bin/phing https://www.phing.info/get/phing-latest.phar && \
    chmod +x /usr/local/bin/phing

WORKDIR /var/www/




FROM php AS base

ARG USER=$USER
ARG UID=$UID

RUN if ! id -u $USER >/dev/null 2>&1 ; \
    then \
    adduser --uid $UID --home /var/www --no-create-home  --gecos \'\' --add_extra_groups --disabled-password --disabled-login --quiet $USER; \
    fi

COPY ./docker/php/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY ./docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

COPY ./docker/php/php.ini-development /usr/local/etc/php/php.ini

USER root:root

COPY ./docker/php/cron /etc/cron.d/

RUN chmod gu+rw /var/run && \
    chmod gu+s /usr/sbin/cron && \
    crontab -u $USER /etc/cron.d/appcrontab

COPY ./docker/php/supervisor /etc/supervisor/

RUN sed -i 's/user=root/user='$USER'/' /etc/supervisor/supervisord.conf && \
    mkdir /var/run/supervisor && \
    chown $USER:$USER /var/run/supervisor

USER $USER:$USER




FROM base AS dev

ENV PHP_CS_FIXER_IGNORE_ENV=1

USER root:root

RUN pecl install xdebug && docker-php-ext-enable xdebug && \
    echo "xdebug.mode=debug,profile" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.output_dir=/var/www/var/log/xdebug/" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.start_with_request=trigger" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.profiler_output_name=cachegrind.out.%R" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    docker-php-source delete

COPY ./docker/php/php.ini-development /usr/local/etc/php/php.ini

USER $USER:$USER

EXPOSE 8000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]



FROM base AS staging

RUN sed -i 's/allrpg-app/'${USER}'-allrpg-app/' /usr/local/etc/php-fpm.d/zz-docker.conf

EXPOSE 8000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]



FROM base as production

ENV APP_ENV=prod APP_DEBUG=false

COPY ./docker/php/prod-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY ./docker/php/php.ini-production /usr/local/etc/php/php.ini

USER $USER:$USER

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]