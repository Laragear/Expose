#!/bin/bash

if [ -f ./composer.json ]; then
    docker run --rm -it -v ./:/app -v ../:/package laragear-local/php:latest composer install
fi

docker run --rm -it -v ./:/app -v ../:/package laragear-local/php:latest composer expose

