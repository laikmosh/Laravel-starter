#--------------------------------------------------------------------------
# Core Application Settings
#--------------------------------------------------------------------------
#
# These settings define the fundamental behavior of your Laravel application.
# APP_ENV determines the running environment (local, production, etc.)
# APP_DEBUG controls detailed error reporting (true, false)
# APP_URL is your application's base URL
#
# Usage: These values affect error reporting, logging, and URL generation
#--------------------------------------------------------------------------
APP_ENV=production
APP_NAME="${PROJECT_NAME}-backend"
APP_URL="${SCHEME}${SUBDOMAIN}.${DOMAIN}"
APP_DEBUG=false
APP_KEY=<your_generated_app_key>

#--------------------------------------------------------------------------
# SuperUser Configuration
#--------------------------------------------------------------------------
SUPERUSER_NAME=user
SUPERUSER_EMAIL=user@example.com
SUPERUSER_PASSWORD=password

#--------------------------------------------------------------------------
# Localization and Time Settings
#--------------------------------------------------------------------------
#
# Configure your application's language and time handling:
# - APP_LOCALE: Primary application language
# - APP_FALLBACK_LOCALE: Backup language if primary fails
# - APP_FAKER_LOCALE: Language for generating fake data
# - APP_TIMEZONE: Server-side time zone setting
#
# Usage: Affects date/time display, translations, and test data generation
#--------------------------------------------------------------------------
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_TIMEZONE=UTC

#--------------------------------------------------------------------------
# Session and Authentication
#--------------------------------------------------------------------------
#
# Configure session handling and authentication domains:
# - SESSION_DOMAIN: Domain for session cookies
# - SANCTUM_STATEFUL_DOMAINS: Allowed domains for Sanctum
#
# Usage: Critical for proper session management and API authentication
#--------------------------------------------------------------------------
SESSION_DOMAIN=".${DOMAIN}"
SANCTUM_STATEFUL_DOMAINS="${SUBDOMAIN}.${DOMAIN}"
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
# CACHE_PREFIX=

PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12
VITE_APP_NAME="${APP_NAME}"
