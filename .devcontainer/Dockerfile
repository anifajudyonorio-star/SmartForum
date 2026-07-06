FROM mcr.microsoft.com/devcontainers/php:8.4

RUN sudo apt-get update && \
    sudo apt-get install -y default-mysql-client && \
    docker-php-ext-install pdo_mysql mysqli