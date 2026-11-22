# Deployscript voor de site mebranding.nl in Laravel Forge (zero-downtime).
# Bron van waarheid: dit bestand. Forge krijgt dezelfde inhoud.
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

# Gedeelde opslag: video's, uploads, logs en sessies moeten elke deploy overleven.
# Eén vaste map naast de releases; elke release verwijst ernaar.
GEDEELD="$(dirname "$(dirname "$FORGE_RELEASE_DIRECTORY")")/storage"  # sitemap/storage (FORGE_SITE_PATH wijst naar current)
mkdir -p "$GEDEELD/app/private" "$GEDEELD/app/public" "$GEDEELD/framework/cache/data" "$GEDEELD/framework/sessions" "$GEDEELD/framework/views" "$GEDEELD/logs"
rm -rf storage
ln -s "$GEDEELD" storage

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci --no-audit --no-fund
npm run build

$FORGE_PHP artisan storage:link --force
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan db:seed --force
$FORGE_PHP artisan mebranding:accounts
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache
$FORGE_PHP artisan event:cache

$ACTIVATE_RELEASE()

$FORGE_PHP artisan horizon:terminate
