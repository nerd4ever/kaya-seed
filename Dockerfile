FROM php:7.4-fpm

ENV SSH_PASSWORD=""
ENV PHP_XDEBUG_IP=""

# Instala os pacotes necessários para o SSH e Xdebug
RUN apt-get update && apt-get install -y openssh-server dos2unix wget && pecl install xdebug

# Configura o SSH
RUN mkdir -p /etc/nerd4ever/kaya-seed

RUN wget https://getcomposer.org/download/2.0.8/composer.phar \
    && mv  composer.phar /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer

COPY . /etc/nerd4ever/kaya-seed/
RUN mv /etc/nerd4ever/kaya-seed/entrypoint.sh /entrypoint \
    && dos2unix /entrypoint \
    && chmod +x /entrypoint \
    && rm -fr /etc/nerd4ever/kaya-seed/samples/*.log || true \
    && rm -fr /etc/nerd4ever/kaya-seed/samples/*.metadata || true \
    && rm -fr /etc/nerd4ever/kaya-seed/Dockerfile \
    && rm -fr /etc/nerd4ever/kaya-seed/docker-composer

WORKDIR /etc/nerd4ever/kaya-seed
CMD ["/entrypoint"]



# Expõe as portas para o SSH e o servidor web
EXPOSE 22 80