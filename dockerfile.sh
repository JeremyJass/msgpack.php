#!/usr/bin/env bash

if [[ -z "$PHP_RUNTIME" ]]; then
    PHP_RUNTIME='php:7.3-cli'
fi

RUN_CMDS='echo memory_limit=256M > $PHP_INI_DIR/conf.d/zz-custom.ini'
if [[ $PHPUNIT_OPTS =~ (^|[[:space:]])--coverage-[[:alpha:]] ]]; then
    RUN_CMDS="$RUN_CMDS && \\\\\n    pecl install pcov && docker-php-ext-enable pcov"
fi

if [[ "1" != "$CHECK_CS" ]]; then
    COMPOSER_REMOVE='composer remove --dev --no-update friendsofphp/php-cs-fixer'
fi

echo -e "
FROM $PHP_RUNTIME

RUN apt-get update && apt-get install -y git curl libzip-dev libgmp-dev libonig-dev && \\
    ln -s /usr/include/x86_64-linux-gnu/gmp.h /usr/include/gmp.h && \\
    docker-php-ext-configure zip --with-libzip && \\
    docker-php-ext-install zip mbstring gmp${RUN_CMDS}

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV PATH=~/.composer/vendor/bin:\$PATH

CMD if [ ! -f composer.lock ]; then $COMPOSER_REMOVE${COMPOSER_REMOVE:+ && }composer install; fi && \\
    vendor/bin/phpunit\${PHPUNIT_OPTS:+ }\$PHPUNIT_OPTS
"
