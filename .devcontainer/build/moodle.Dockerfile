# =============================================================================
# LDG Courses - Moodle 5.2
# Multi-stage build: composer → base → production | development
#
# Build production:
#   docker build --target production -f .devcontainer/build/moodle.Dockerfile .
#
# Build development:
#   docker build --target development -f .devcontainer/build/moodle.Dockerfile .
# =============================================================================

# =============================================================================
#            STAGE COMPOSER - resolve dependencies PHP isolated
# =============================================================================
FROM composer:lts AS composer-base

    RUN apk add --no-cache \
            libpng-dev libjpeg-turbo-dev freetype-dev \
            icu-dev \
            libzip-dev \
            zlib-dev

    RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
        && docker-php-ext-install -j$(nproc) gd intl zip

    WORKDIR /app

    COPY composer.json composer.lock ./

# =============================================================================
#     STAGE COMPOSER-PROD - dependencies production sem dev packages
# =============================================================================
FROM composer-base AS composer-prod

    RUN --mount=type=cache,target=/tmp/composer-cache \
        composer install \
            --no-dev \
            --no-interaction \
            --no-progress \
            --prefer-dist \
            --no-scripts

# =============================================================================
#     STAGE COMPOSER-DEV - dependencies development COM dev packages
# =============================================================================
FROM composer-base AS composer-dev

    ENV COMPOSER_ALLOW_PLUGINS=dealerdirect/phpcodesniffer-composer-installer:true

    RUN --mount=type=cache,target=/tmp/composer-cache \
        composer install \
            --no-interaction \
            --no-progress \
            --prefer-dist \
            --no-scripts

# =============================================================================
#         STAGE BASE - SETUP SHARED BETWEEN DEVELOPMENT AND PRODUCTION
# =============================================================================
# Moodle 5.2 exige PHP >= 8.3.0 (admin/environment.xml, bloco MOODLE version="5.2").
# 8.4 e a maior tag publicada pela moodlehq; nao existe 8.5.
FROM moodlehq/moodle-php-apache:8.4 AS base

# Metadata
LABEL maintainer="LeoDG <callme@leodg.dev>" \
      org.opencontainers.image.title="LDG Courses - Moodle 5.2" \
      org.opencontainers.image.source="https://github.com/leonardodg/ldg-courses" \
      org.opencontainers.image.version="5.2"

ENV MOODLE_DBTYPE=mariadb \
    MOODLE_DBLIB=native \
    MOODLE_DBPFX=mdl_ \
    MOODLE_DBCOLL=utf8mb4_bin \
    MOODLE_URL=https://develop.local \
    MOODLE_DATA=/var/www/moodledata \
    MOODLE_ADMIN=admin \
    MOODLE_DBHOST=db \
    MOODLE_DBNAME=moodle \
    ENVIRONMENT=development \
    TZ=America/Sao_Paulo

WORKDIR /var/www/html

# A tag da base e movel, e nada garante que a publicacao dela trouxe a correcao.
# O build falha em vez de publicar imagem abaixo do minimo de seguranca. O mesmo
# arquivo e conferido de novo no deploy, ja no container que subiu.
COPY .devcontainer/build/min-php-version /etc/ldg-min-php-version
RUN php -r 'exit(version_compare(PHP_VERSION, trim(file_get_contents("/etc/ldg-min-php-version")), ">=") ? 0 : 1);' \
    || { echo "PHP $(php -r 'echo PHP_VERSION;') abaixo do minimo $(cat /etc/ldg-min-php-version)"; exit 1; }

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       gettext-base  \
       cron \
       libpng-dev \
    && apt-get install -y --no-install-recommends --only-upgrade \
       openssl \
       libssl-dev \
       libssl3 \
       apache2 \
       apache2-bin \
       apache2-data \
       libpam-runtime \
       libpam0g \
       gnutls-bin \
       libgnutls30 \
       libxml2 \
       libtiff6 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN mkdir -p /etc/ssl/certs \
    && mkdir -p /docker-entrypoint.d/ \
    && chown -R www-data:www-data /etc/ssl/certs \
    && chmod 755 /etc/ssl/certs \
    && mkdir -p /var/www/moodledata && chown -R www-data:www-data /var/www/moodledata

COPY --from=composer-base /usr/bin/composer /usr/bin/composer
COPY .devcontainer/php/opcache.ini \
     .devcontainer/php/uploads.ini \
     /usr/local/etc/php/conf.d/

# Apache: módulos e configuração via template (URL vem em runtime)
COPY .devcontainer/apache/*.template /etc/apache2/sites-available/

# Add Script to CRONTAB
COPY --chown=www-data:www-data .devcontainer/bin/moodle-cron /var/www/html/moodle-cron

# Add Script Entrypoint
COPY --chown=www-data:www-data .devcontainer/bin/moodle-entrypoint /usr/local/bin/moodle-entrypoint

# Configure Moodle after installation
COPY --chown=www-data:www-data .devcontainer/config/config-docker.php /var/www/html/config.php

RUN chmod +x /var/www/html/moodle-cron \
    && chmod +x /usr/local/bin/moodle-entrypoint


EXPOSE 80 443
USER root
ENTRYPOINT ["moodle-entrypoint"]
CMD ["apache2-foreground"]


# =============================================================================
#               STAGE PRODUCTION - Optimized for performance
# =============================================================================
FROM base AS production

ENV ENVIRONMENT=production \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

# REMOVE git/gnupg
RUN apt-get remove --purge -y \
       git git-man \
       gnupg gnupg2 gpg gpg-agent \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY .devcontainer/php/php.ini-production /usr/local/etc/php/php.ini
COPY .devcontainer/php/opcache-prod.ini /usr/local/etc/php/conf.d/moodle-opcache.ini

# Copia vendor otimizado do stage composer
COPY --from=composer-prod --chown=www-data:www-data /app/vendor/ /var/www/html/vendor/

# Copia o código da aplicação Moodle
COPY --chown=www-data:www-data . .

# Otimiza autoloader para produção
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction

# Limpeza final
RUN rm /usr/bin/composer

# =============================================================================
#          STAGE DEVELOPMENT - Tools (hot-reload, xdebug, phpcs, node, grunt)
# =============================================================================
FROM base AS development

# Desabilita opcache validate timestamps para dev (melhor hot-reload)
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 \
    ENVIRONMENT=development \
    CRON_ENABLED=TRUE

# -----------------------------------------------------------------------
# Ferramentas de sistema: git, curl, mysql-client, xdebug
# + Node.js LTS via NodeSource (necessario para Grunt/JS build do Moodle)
# -----------------------------------------------------------------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       openssl \
       nano \
       curl \
       ca-certificates \
       default-mysql-client \
       git \
       git-man \
       gnupg \
       gnupg2 \
       gpg \
    # Node.js LTS oficial (requerido pelo Grunt, que e o bundler JS do Moodle):
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    # Xdebug ja embutido na imagem moodlehq/moodle-php-apache:
    && docker-php-ext-enable xdebug \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# O grunt-cli saiu daqui: a versao dele agora e declarada em
# .devcontainer/devtools/package.json, junto das demais ferramentas de
# desenvolvimento, e instalada mais abaixo. Um "npm install -g <pacote>" solto
# nao tem versao, nao aparece em code review e muda sozinho a cada rebuild.

# -----------------------------------------------------------------------
# Historico de shell persistente.
#
# Isto substitui a feature "stuartleeks/dev-container-features/shell-history"
# que estava no devcontainer.json. O motivo nao e economia: TODA feature
# declarada faz a CLI do Dev Containers construir uma IMAGEM DERIVADA para
# injeta-la, e ela refaz esse build para cada pasta nova. Com worktrees, isso
# significa um rebuild a cada feature que se comeca - o incomodo que este
# trabalho existe para eliminar.
#
# As outras duas features sairam sem substituto:
#   git:1          - redundante, o apt-get acima ja instala git e git-man.
#   common-utils:2 - traz zsh/oh-my-zsh e mexe em usuario. O devcontainer.json
#                    fixa remoteUser www-data e terminal bash; nao agregava.
#
# O diretorio vira ponto de montagem de um volume nomeado (ver devcontainer.json),
# entao o historico sobrevive a recriacao do container. Antes era um bind em
# ../../moodle-academy-bashhistory, pasta do projeto anterior.
# -----------------------------------------------------------------------
ENV HISTFILE=/commandhistory/.bash_history \
    PROMPT_COMMAND="history -a"
RUN mkdir -p /commandhistory \
    && touch /commandhistory/.bash_history \
    && chown -R www-data:www-data /commandhistory

COPY .devcontainer/php/opcache-dev.ini /usr/local/etc/php/conf.d/moodle-opcache.ini

# -----------------------------------------------------------------------
# Vendor com pacotes de DEV (phpunit e behat vem daqui)
#
# O phpcs NAO vem: o comentario que estava aqui pedia para acrescentar
# moodlehq/moodle-cs ao composer.json da raiz, e isso e uma armadilha. Aquele
# composer.json e do UPSTREAM do Moodle, byte a byte - conferido em 04/09/2026,
# os ultimos commits nele sao MDL-86462 e MDL-86460. Mexer ali criaria conflito
# em TODA sincronizacao com o upstream, que e exatamente o que ja acontece com o
# .gitattributes da raiz.
#
# As ferramentas do projeto vao para /opt/devtools, logo abaixo.
# -----------------------------------------------------------------------
COPY --from=composer-dev --chown=www-data:www-data /app/vendor/ /var/www/html/vendor/

# -----------------------------------------------------------------------
# Ferramentas de desenvolvimento, fora do vendor do Moodle.
#
# Ficam na IMAGEM, e nao instaladas a mao no container: container recriado
# perdia tudo, e cada worktree nova comecava sem phpcs. A imagem e a mesma para
# todos os stacks, entao instalar uma vez atende todo mundo.
#
# /opt/devtools tem composer.json PROPRIO, sem relacao com o do Moodle - e o que
# permite ter phpcs sem tocar em arquivo do upstream.
# -----------------------------------------------------------------------
# A LISTA NAO FICA AQUI. Ela vive em .devcontainer/devtools/, em manifesto de
# verdade - versao de ferramenta e dependencia, e dependencia se declara onde da
# para revisar e fixar faixa. O porque de cada escolha esta no README de la.
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY .devcontainer/devtools/ /opt/devtools/

RUN composer install -n --no-dev -d /opt/devtools \
    && ln -sf /opt/devtools/vendor/bin/phpcs   /usr/local/bin/phpcs \
    && ln -sf /opt/devtools/vendor/bin/phpcbf  /usr/local/bin/phpcbf \
    # Falha o build se o padrao moodle nao registrar. Sem isto a imagem sai com
    # um phpcs que roda e nao conhece o padrao do projeto - e o erro so aparece
    # na primeira vez que alguem tenta conferir codigo.
    && phpcs -i | grep -q moodle \
    && npm install --prefix /opt/devtools \
    && ln -sf /opt/devtools/node_modules/.bin/grunt /usr/local/bin/grunt

# moosh e clonado, e nao requerido por composer. Nao e preguica: ele declara
# TREZE repositories inline para dependencias que so existem no GitHub, e o
# composer ignora "repositories" de pacote transitivo - requere-lo daqui exigiria
# copiar as treze para o nosso manifesto e ve-las apodrecer. O projeto dele se
# trata como raiz, e clonar e a forma suportada.
#
# A versao NAO esta hardcoded nesta linha: sai de devtools/moosh.version.
RUN git clone -q --branch "$(cat /opt/devtools/moosh.version)" --depth 1 \
        https://github.com/tmuras/moosh.git /opt/moosh \
    && composer install -n --no-dev -d /opt/moosh \
    && ln -sf /opt/moosh/moosh.php /usr/local/bin/moosh \
    && chmod +x /opt/moosh/moosh.php

# Copia todo o codigo (em dev geralmente sera sobrescrito por bind-mount do dev.yml)
COPY --chown=www-data:www-data . .

# Em desenvolvimento: autoloader normal (permite hot-reload sem rebuild)
RUN composer dump-autoload --no-interaction
