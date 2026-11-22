# Deployscript voor de site mebranding.nl in Laravel Forge (zero-downtime).
# Bron van waarheid: dit bestand. Forge krijgt dezelfde inhoud.
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci --no-audit --no-fund
npm run build

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan db:seed --force
$FORGE_PHP artisan mebranding:accounts
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache
$FORGE_PHP artisan event:cache

$ACTIVATE_RELEASE()

$FORGE_PHP artisan horizon:terminate
