#!/bin/sh

echo "⏳ Waiting for MySQL to be ready..."

while ! mysqladmin ping -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent; do
  sleep 1
done

echo "✅ MySQL is up!"
