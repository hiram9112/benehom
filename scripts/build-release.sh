#!/usr/bin/env bash

set -Eeuo pipefail

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

pass() {
    printf '[OK] %s\n' "$*"
}

for required_command in git composer php tar gzip sha256sum mktemp find chmod stat; do
    command -v "$required_command" >/dev/null 2>&1 \
        || die "No se encontro el comando requerido: ${required_command}"
done

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
if ! REPO_ROOT="$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null)"; then
    die 'No se pudo resolver la raiz del repositorio.'
fi
REPO_ROOT="$(cd -- "$REPO_ROOT" && pwd -P)"

if ! FULL_SHA="$(git -C "$REPO_ROOT" rev-parse --verify 'HEAD^{commit}' 2>/dev/null)"; then
    die 'No se pudo resolver el commit HEAD.'
fi
SHORT_SHA="$(git -C "$REPO_ROOT" rev-parse --short=7 "$FULL_SHA")"

DIST_DIR="${REPO_ROOT}/dist"
RELEASE_NAME="benehom-${SHORT_SHA}"
RELEASE_DIR="${DIST_DIR}/${RELEASE_NAME}"
ARCHIVE_PATH="${DIST_DIR}/${RELEASE_NAME}.tar.gz"
CHECKSUM_PATH="${ARCHIVE_PATH}.sha256"

[[ "$DIST_DIR" == "${REPO_ROOT}/dist" ]] \
    || die 'La ruta de salida no coincide con dist/ dentro del repositorio.'
[[ ! -L "$DIST_DIR" ]] \
    || die 'dist/ no puede ser un enlace simbolico.'
if [[ -e "$DIST_DIR" && ! -d "$DIST_DIR" ]]; then
    die 'dist/ existe pero no es un directorio.'
fi
mkdir -p -- "$DIST_DIR"

assert_dist_child() {
    local path="$1"

    [[ "$path" == "${DIST_DIR}/"* && "$path" != "$DIST_DIR" ]] \
        || die "Operacion destructiva rechazada fuera de dist/: ${path}"
}

safe_remove() {
    local path

    for path in "$@"; do
        [[ -n "$path" ]] || continue
        assert_dist_child "$path"
        rm -rf -- "$path"
    done
}

BUILD_DIR=''
TEMP_ARCHIVE=''
TEMP_CHECKSUM=''

cleanup() {
    local exit_code=$?

    trap - EXIT
    [[ -z "$BUILD_DIR" || (! -e "$BUILD_DIR" && ! -L "$BUILD_DIR") ]] \
        || safe_remove "$BUILD_DIR"
    [[ -z "$TEMP_ARCHIVE" || (! -e "$TEMP_ARCHIVE" && ! -L "$TEMP_ARCHIVE") ]] \
        || safe_remove "$TEMP_ARCHIVE"
    [[ -z "$TEMP_CHECKSUM" || (! -e "$TEMP_CHECKSUM" && ! -L "$TEMP_CHECKSUM") ]] \
        || safe_remove "$TEMP_CHECKSUM"

    if (( exit_code != 0 )); then
        printf 'ERROR: La construccion de la release ha fallado.\n' >&2
    fi
    exit "$exit_code"
}
trap cleanup EXIT

printf 'Construyendo release del commit:\n  %s\n' "$FULL_SHA"
printf 'Salida:\n  %s\n' "$RELEASE_DIR"

allowlist=(
    app
    config
    public
    knowledge/numa
    resources/numa/prompts/base.md
    bin/indexar-numa.php
    database/schema.sql
    composer.json
    composer.lock
)

required_assets=(
    public/css/app.min.css
    public/js/vendor/gsap/gsap.min.js
    public/js/vendor/gsap/ScrollTrigger.min.js
    public/js/vendor/gsap/SplitText.min.js
    public/js/vendor/lenis/lenis.min.js
)

for required_path in "${allowlist[@]}" "${required_assets[@]}"; do
    git -C "$REPO_ROOT" cat-file -e "${FULL_SHA}:${required_path}" 2>/dev/null \
        || die "Falta en el commit ${FULL_SHA}: ${required_path}"
done
pass 'Todos los paths requeridos existen en el commit.'

safe_remove "$RELEASE_DIR" "$ARCHIVE_PATH" "$CHECKSUM_PATH"
BUILD_DIR="$(mktemp -d "${DIST_DIR}/.${RELEASE_NAME}.build.XXXXXX")"

git -C "$REPO_ROOT" archive --format=tar "$FULL_SHA" -- "${allowlist[@]}" \
    | tar -xf - -C "$BUILD_DIR"

printf '%s\n' "$FULL_SHA" > "${BUILD_DIR}/RELEASE_SHA"

printf 'Instalando dependencias PHP de produccion...\n'
(
    cd -- "$BUILD_DIR"
    composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --optimize-autoloader \
        --no-scripts
)

[[ -d "${BUILD_DIR}/vendor" ]] \
    || die 'composer install no genero vendor/.'
pass 'vendor/ de produccion existe.'

if composer --working-dir="$BUILD_DIR" show phpunit/phpunit --no-interaction >/dev/null 2>&1; then
    die 'PHPUnit esta instalado en la release.'
fi
pass 'PHPUnit no esta instalado.'

if composer --working-dir="$BUILD_DIR" show phpstan/phpstan --no-interaction >/dev/null 2>&1; then
    die 'PHPStan esta instalado en la release.'
fi
pass 'PHPStan no esta instalado.'

php -r '
    $path = $argv[1];
    $sha = $argv[2];
    exit(file_get_contents($path) === $sha . PHP_EOL ? 0 : 1);
' "${BUILD_DIR}/RELEASE_SHA" "$FULL_SHA" \
    || die 'RELEASE_SHA no contiene exclusivamente el SHA esperado.'
pass 'RELEASE_SHA contiene el SHA completo esperado.'

php -r '
    $composer = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    exit(($composer["scripts"]["numa:index"] ?? null) === "php bin/indexar-numa.php" ? 0 : 1);
' "${BUILD_DIR}/composer.json" \
    || die 'composer numa:index no esta registrado como php bin/indexar-numa.php.'
pass 'composer numa:index sigue registrado como php bin/indexar-numa.php.'
pass 'Los scripts de Composer estuvieron deshabilitados; el indexador no se ejecuto.'

for asset_path in "${required_assets[@]}"; do
    [[ -f "${BUILD_DIR}/${asset_path}" ]] \
        || die "Falta un asset generado requerido: ${asset_path}"
done
pass 'Los cinco assets generados requeridos existen.'

forbidden_paths=(
    .env
    .env.example
    .git
    .github
    tests
    node_modules
    test-results
    playwright-report
    .phpunit.result.cache
    phpunit.xml
    phpunit.xml.dist
    phpstan.dist.neon
    playwright.config.js
    package.json
    package-lock.json
    database/seed.sql
    resources/deployments
    bin/evaluar-analitica-numa.php
    bin/evaluar-e2e-numa.php
    bin/evaluar-precedencia-temporal-numa.php
    bin/evaluar-rag-numa.php
)

for forbidden_path in "${forbidden_paths[@]}"; do
    if [[ -e "${BUILD_DIR}/${forbidden_path}" || -L "${BUILD_DIR}/${forbidden_path}" ]]; then
        die "Path prohibido presente en la release: ${forbidden_path}"
    fi
done

unexpected_file="$(find "$BUILD_DIR" \
    \( -name backups -o -name dumps \
        -o -name '*.log' -o -name '*.tmp' -o -name '*.cache' \
        -o -name '*.zip' -o -name '*.tar' -o -name '*.tar.gz' \
        -o -name '*.sql.gz' \) \
    -print -quit)"
[[ -z "$unexpected_file" ]] \
    || die "Archivo o directorio prohibido presente en la release: ${unexpected_file#${BUILD_DIR}/}"
pass 'No existen paths ni patrones prohibidos en la release.'

chmod 755 -- "$BUILD_DIR"
mv -- "$BUILD_DIR" "$RELEASE_DIR"
BUILD_DIR=''

[[ "$(stat -c '%a' "$RELEASE_DIR")" == '755' ]] \
    || die 'La raiz del directorio de release no tiene permisos 755.'
pass 'La raiz del directorio de release tiene permisos 755.'

COMMIT_TIMESTAMP="$(git -C "$REPO_ROOT" show -s --format=%ct "$FULL_SHA")"
TEMP_ARCHIVE="${ARCHIVE_PATH}.tmp.$$"
assert_dist_child "$TEMP_ARCHIVE"
tar \
    --sort=name \
    --mtime="@${COMMIT_TIMESTAMP}" \
    --owner=0 \
    --group=0 \
    --numeric-owner \
    -C "$RELEASE_DIR" \
    -cf - . \
    | gzip -n > "$TEMP_ARCHIVE"
mv -- "$TEMP_ARCHIVE" "$ARCHIVE_PATH"
TEMP_ARCHIVE=''
[[ -s "$ARCHIVE_PATH" ]] || die 'No se genero el archivo tar.gz.'
pass 'El archivo tar.gz existe.'

archive_root_entry="$(tar --no-recursion -tzvf "$ARCHIVE_PATH" -- ./)" \
    || die 'No se pudo inspeccionar la entrada raiz del archivo tar.gz.'
[[ "$archive_root_entry" == drwxr-xr-x* ]] \
    || die 'La entrada raiz del archivo tar.gz no tiene permisos 755.'
pass 'La entrada raiz del archivo tar.gz tiene permisos 755.'

TEMP_CHECKSUM="${CHECKSUM_PATH}.tmp.$$"
assert_dist_child "$TEMP_CHECKSUM"
(
    cd -- "$DIST_DIR"
    sha256sum "$(basename -- "$ARCHIVE_PATH")" > "$(basename -- "$TEMP_CHECKSUM")"
)
mv -- "$TEMP_CHECKSUM" "$CHECKSUM_PATH"
TEMP_CHECKSUM=''
[[ -s "$CHECKSUM_PATH" ]] || die 'No se genero el checksum SHA-256.'
(
    cd -- "$DIST_DIR"
    sha256sum --check "$(basename -- "$CHECKSUM_PATH")"
)
pass 'El checksum SHA-256 existe y valida correctamente.'

printf '\nRelease construida correctamente:\n'
printf '  Directorio: %s\n' "$RELEASE_DIR"
printf '  Archivo:    %s\n' "$ARCHIVE_PATH"
printf '  Checksum:   %s\n' "$CHECKSUM_PATH"
