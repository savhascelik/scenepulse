#!/bin/sh
set -e

cd /var/www/html

if [ ! -f ".env" ]; then
  cp .env.example .env
fi

# Sync container environment variables into .env so php artisan serve workers always read them
for VAR_NAME in APP_ENV APP_KEY APP_DEBUG APP_URL FRONTEND_URL CORS_ALLOWED_ORIGINS AI_DEFAULT_PROVIDER DAILY_LIVE_LLM_CAP QLOO_SERVER_URL QLOO_BASE_URL QLOO_API_KEY QLOO_CACHE_TTL_HOURS NEBIUS_BASE_URL NEBIUS_API_KEY NEBIUS_DEFAULT_MODEL NEBIUS_FAST_MODEL NEBIUS_VISION_MODEL OPENROUTER_BASE_URL OPENROUTER_API_KEY OPENROUTER_DEFAULT_MODEL OPENROUTER_FAST_MODEL OPENROUTER_VISION_MODEL GEMINI_API_KEY GEMINI_MODEL OPENAI_API_KEY; do
  VAR_VAL=$(printenv "$VAR_NAME" 2>/dev/null | tr -d '\r\n' || true)
  if [ -n "$VAR_VAL" ]; then
    grep -v "^${VAR_NAME}=" .env > .env.tmp 2>/dev/null || true
    mv .env.tmp .env
    printf "%s=%s\n" "$VAR_NAME" "$VAR_VAL" >> .env
  fi
done

mkdir -p database storage/database storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
rm -f bootstrap/cache/*.php

# Persist APP_KEY inside the mounted storage/database volume so sessions/cookies never break on container rebuild
PERSISTED_KEY_FILE="/var/www/html/storage/database/.app_key"
if [ -z "$APP_KEY" ]; then
  if [ -f "$PERSISTED_KEY_FILE" ] && [ -s "$PERSISTED_KEY_FILE" ]; then
    SAVED_KEY=$(cat "$PERSISTED_KEY_FILE")
    if grep -q "^APP_KEY=" .env 2>/dev/null; then
      sed -i "s|^APP_KEY=.*|APP_KEY=${SAVED_KEY}|" .env
    else
      echo "APP_KEY=${SAVED_KEY}" >> .env
    fi
  elif ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    php artisan key:generate --force --no-interaction || true
    GENERATED_KEY=$(grep "^APP_KEY=" .env | cut -d'=' -f2-)
    if [ -n "$GENERATED_KEY" ]; then
      echo "$GENERATED_KEY" > "$PERSISTED_KEY_FILE"
    fi
  else
    EXISTING_KEY=$(grep "^APP_KEY=" .env | cut -d'=' -f2-)
    if [ -n "$EXISTING_KEY" ]; then
      echo "$EXISTING_KEY" > "$PERSISTED_KEY_FILE"
    fi
  fi
fi

if [ ! -f "storage/database/database.sqlite" ] || [ ! -s "storage/database/database.sqlite" ]; then
  if [ -f "database/database.sqlite" ] && [ -s "database/database.sqlite" ]; then
    cp database/database.sqlite storage/database/database.sqlite
  else
    touch storage/database/database.sqlite
  fi
fi

rm -f database/database.sqlite
ln -s /var/www/html/storage/database/database.sqlite database/database.sqlite

php artisan package:discover --ansi || true
php artisan filament:assets --no-interaction || true
php artisan livewire:publish --assets --no-interaction || true
php artisan migrate --seed --force --no-interaction
(php artisan scenepulse:sync-cache >/dev/null 2>&1 && php artisan scenepulse:warm >/dev/null 2>&1 || true) &

exec php artisan serve --host=0.0.0.0 --port=8000
