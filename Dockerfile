FROM dunglas/frankenphp:php8.3

# Instalar extensões necessárias do PHP
RUN install-php-extensions \
    pdo_mysql

# Configurar a porta que será dinamicamente injetada pelo Railway via $PORT
ENV SERVER_NAME=":\$PORT"

# Mover php.ini de produção
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copiar os arquivos do projeto para o diretório raiz do web server
COPY . /app/public/
