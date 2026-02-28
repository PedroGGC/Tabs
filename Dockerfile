FROM dunglas/frankenphp:php8.3

# Instalar extensões necessárias do PHP
RUN install-php-extensions pdo_mysql

# Mover php.ini de produção
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configurar o Caddy para servir HTTP puro na porta 80, o que desabilita o auto_https
ENV SERVER_NAME="http://:80"

EXPOSE 80

# Copiar os arquivos do projeto para o diretório raiz do web server
COPY . /app/public/

# Como config/database.php ignora o Git, usamos o example para a execucao na nuvem
RUN cp /app/public/config/database.example.php /app/public/config/database.php
