FROM dunglas/frankenphp:php8.3

# Instalar extensões necessárias do PHP
RUN install-php-extensions pdo_mysql

# Mover php.ini de produção
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configurar o Caddy para servir HTTP puro na porta 80, o que desabilita o auto_https
ENV SERVER_NAME="http://:80"

# O FrankenPHP tem um fallback interno para /app/public
# O projeto do usuário no entanto tem os scripts principais (index, login) diretamente na raiz
# Então copiamos tudo para /app (raiz de execucao)
COPY . /app/

# Como esse não tem diretório `public`, instruimos o FrankenPHP que o public dir do Caddy é a prórpia pasta do PHP!
ENV FRANKENPHP_CONFIG="root * /app/"

EXPOSE 80
