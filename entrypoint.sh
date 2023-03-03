#!/bin/sh
set -e
# Project Dir

if [ -n "${PHP_XDEBUG_IP}" ]; then
cat <<EOF > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
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
fi
projectDir="/etc/nerd4ever/kaya-seed"

if [ -n "${SSH_PASSWORD}" ]; then
  echo "root:${SSH_PASSWORD}" | chpasswd
  echo "${SSH_PASSWORD}" > /etc/nerd4ever/password.txt
  /etc/init.d/ssh start
fi

php -S 0.0.0.0:80 -t ${projectDir}/sample
