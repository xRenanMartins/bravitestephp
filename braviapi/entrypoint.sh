#!/bin/sh

NEWRELIC=$(/tmp/env | grep ENV_NAME | cut -d'=' -f2)
curl -L https://packkbucket.s3.sa-east-1.amazonaws.com/general/executables/newrelic-php5-9.18.1.303-linux.tar.gz | tar -C /tmp -zx &&
  export NR_INSTALL_USE_CP_NOT_LN=1 &&
  export NR_INSTALL_SILENT=1 &&
  /tmp/newrelic-php5-*/newrelic-install install &&
  rm -rf /tmp/newrelic-php5-* /tmp/nrinstall* &&
  sed -i \
    -e 's/"REPLACE_WITH_REAL_KEY"/"6b0aeeb8968c03de87464ed9ffb1db32be7ea277"/' \
    -e 's/newrelic.appname = "PHP Application"/newrelic.appname = "'"${NEWRELIC}"'"/' \
    -e 's/;newrelic.daemon.app_connect_timeout =.*/newrelic.daemon.app_connect_timeout=15s/' \
    -e 's/;newrelic.daemon.start_timeout =.*/newrelic.daemon.start_timeout=5s/' \
    /etc/php/8.0/cli/conf.d/newrelic.ini
curl -Ls https://download.newrelic.com/install/newrelic-cli/scripts/install.sh | bash && sudo NEW_RELIC_API_KEY=NRAK-56DKIKSD1RV8VGGE1PBZIQSUY38 NEW_RELIC_ACCOUNT_ID=2355808 /usr/local/bin/newrelic install -n nginx-open-source-integration
cp /etc/php/8.0/cli/conf.d/newrelic.ini /etc/php/8.0/fpm/conf.d/newrelic.ini
rm -rf /etc/php/8.0/cli/conf.d/newrelic.ini
service php8.0-fpm reload
