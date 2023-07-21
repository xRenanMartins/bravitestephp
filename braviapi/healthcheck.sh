#/usr/bin/env sh
set -e
#controle para executar somente uma vez
FIRST_READY_STATUS_FLAG='/tmp/.FIRST_READY_STATUS_FLAG'
#Pegando o nome do ambiente
NEWRELIC="$(/tmp/env | grep ENV_NAME | cut -d'=' -f2)"
#Validando para executar somente uma vez
if [ ! -f "${FIRST_READY_STATUS_FLAG}" ]; then
  #Verificando se existe nome na variavel
  if [ -n ${NEWRELIC} ]; then
    touch "${FIRST_READY_STATUS_FLAG}"
    #Executando script
    /tmp/entrypoint.sh
  fi
fi
