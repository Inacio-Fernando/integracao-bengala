#!/bin/bash

APP="**** INTEGRAÇÃO BENGALA ****"
echo "$APP"

echo "Insira seu email (github):"
read email

echo "Insira seu username (github):"
read username

echo "Insira seu nome (github):"
read name

echo "Insira seu token (github):"
read token

git clone https://$username:$token@github.com/iran-cartazfacil/integracao-bengala.git
cd integracao-bengala

git config --local user.email $email
git config --local user.name $name

#se diretório não existir
if ! [ -d "dumps" ]; then
    mkdir dumps
    touch dumps/produtos.json
fi

#se diretório não existir
if ! [ -d "logs" ]; then
    mkdir logs
    touch logs/diary-status.txt logs/diary-error.txt logs/diary-log.txt logs/diary-update-error.txt logs/diary-update-log.txt
fi

php composer.phar install
cd ..
sudo chmod 777 -R integracao-bengala

