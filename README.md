
Teste Bravi

-Suporte Balanceados
-API Laravel
Para instalar e rodar a api execute os seguintes comandos:

    cd braviapi
    docker run --rm --interactive --tty \
  --volume $PWD:/app \
  composer install --ignore-platform-reqs
    ./vendor/bin/sail up -d

-Front Angular
Para instalar e rodar o front basta executar os seguintes comandos:

    cd bravifront
    npm install
    ng serve
