#!/bin/bash

set -e

echo "PHP 7.4 START"
docker build -q -f tests/docker-php/Dockerfile -t testing74 --build-arg PHP_VERSION=7.4 .
docker run --rm testing74
echo "PHP 7.4 END"

echo "PHP 8.0 START"
docker build -q -f tests/docker-php/Dockerfile -t testing80 --build-arg PHP_VERSION=8.0 .
docker run --rm testing80
echo "PHP 8.0 END"

echo "PHP 8.1 START"
docker build -q -f tests/docker-php/Dockerfile -t testing81 --build-arg PHP_VERSION=8.1 .
docker run --rm testing81
echo "PHP 8.1 END"

echo "PHP 8.2 START"
docker build -q -f tests/docker-php/Dockerfile -t testing82 --build-arg PHP_VERSION=8.2 .
docker run --rm testing82
echo "PHP 8.2 END"

echo "PHP 8.3 START"
docker build -q -f tests/docker-php/Dockerfile -t testing83 --build-arg PHP_VERSION=8.3 .
docker run --rm testing83
echo "PHP 8.3 END"

echo "PHP 8.4 START"
docker build -q -f tests/docker-php/Dockerfile -t testing84 --build-arg PHP_VERSION=8.4 .
docker run --rm testing84
echo "PHP 8.4 END"

echo "ALL OK!"
