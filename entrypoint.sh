#!/bin/sh
set -e
# Project Dir

if [ -z "${PHP_XDEBUG_IP}" ]; then
  phpdismod xdebug
else
  cat <<EOF >/etc/php/7.4/mods-available/xdebug.ini
zend_extension=xdebug.so
[xdebug]
xdebug.mode=debug
xdebug.client_port=9003
xdebug.client_host="${PHP_XDEBUG_IP}"
xdebug.start_with_request=yes
xdebug.connect_timeout_ms = 1000
xdebug.force_display_errors = 1
xdebug.force_error_reporting = 1
xdebug.log_level = 7
xdebug.log = /tmp/xdebug.log
xdebug.output_dir = /tmp
xdebug.idekey=kaya-seed
EOF
  phpenmod xdebug
fi
projectDir="/etc/nerd4ever/kaya-seed"

cd "${projectDir}" || exit 1
composer install

if [ -n "${SSH_PASSWORD}" ]; then
  echo "nerd4ever:${SSH_PASSWORD}" | chpasswd
  echo "${SSH_PASSWORD}" >/etc/nerd4ever/extras/password.txt
  /etc/init.d/ssh start
fi

php -S 0.0.0.0:80 -t ${projectDir}/sample
