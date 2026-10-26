#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
if [ -n "${NUVIX_PHP_BIN:-}" ]; then
    nuvix_php="$NUVIX_PHP_BIN"
elif command -v php >/dev/null 2>&1; then
    nuvix_php="$(command -v php)"
elif [ -x "$HOME/.cache/estadia-php/php" ]; then
    nuvix_php="$HOME/.cache/estadia-php/php"
else
    echo 'Instale PHP com PDO SQLite ou informe NUVIX_PHP_BIN.' >&2
    exit 1
fi
if [ ! -f vendor/autoload.php ]; then
    echo 'Instale as dependências com composer install antes de iniciar.' >&2
    exit 1
fi
nuvix_sessions="${NUVIX_SESSION_DIR:-$PWD/api/database/sessions}"
mkdir -p "$nuvix_sessions"
chmod 700 "$nuvix_sessions"
exec "$nuvix_php" -d "session.save_path=$nuvix_sessions" -S "127.0.0.1:${NUVIX_PORT:-8081}" -t app/public
