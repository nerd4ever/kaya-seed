FROM php:8.1-fpm

ENV SSH_PASSWORD=""
ENV PHP_XDEBUG_IP=""

# Instala os pacotes necessários para o SSH e Xdebug
RUN apt-get update && apt-get install -y openssh-server dos2unix wget zip unzip

RUN set -e ; \
    pecl install xdebug-3.1.6; \
    docker-php-ext-enable xdebug;

RUN wget https://getcomposer.org/download/2.5.4/composer.phar \
    && mv  composer.phar /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer

# Permitir acesso root
RUN sed -i 's/#PermitRootLogin prohibit-password/PermitRootLogin yes/' /etc/ssh/sshd_config

RUN mkdir -p /etc/nerd4ever/kaya-seed
COPY . /etc/nerd4ever/kaya-seed
RUN mv /etc/nerd4ever/kaya-seed/entrypoint.sh /entrypoint \
    && dos2unix /entrypoint \
    && chmod +x /entrypoint \
    && rm -fr /etc/nerd4ever/kaya-seed/samples/*.log || true \
    && rm -fr /etc/nerd4ever/kaya-seed/samples/*.metadata || true \
    && rm -fr /etc/nerd4ever/kaya-seed/Dockerfile \
    && rm -fr /etc/nerd4ever/kaya-seed/docker-composer \
    && cd /etc/nerd4ever/kaya-seed  || exit 1 \
    && composer install

WORKDIR /etc/nerd4ever/kaya-seed
CMD ["/entrypoint"]

# Expõe as portas para o SSH e o servidor web
EXPOSE 22 80