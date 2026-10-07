#!/usr/bin/env bash

set -Eeuo pipefail

DOMAIN_ROOT='/home/u124104782/domains/benehom.es'
RELEASES_DIR="${DOMAIN_ROOT}/releases"
PERSISTENT_ENV="${DOMAIN_ROOT}/.env.v2"
PUBLIC_LINK="${DOMAIN_ROOT}/public_html"
SITE_URL='https://benehom.es'

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || die "Required command is unavailable: $1"
}

validate_release_short() {
    [[ "$1" =~ ^[0-9a-f]{7,40}$ ]] || die 'Release short SHA is invalid.'
}

validate_full_sha() {
    [[ "$1" =~ ^[0-9a-f]{40}$ ]] || die 'Release full SHA is invalid.'
}

validate_staging_dir() {
    local staging_dir="$1"
    local release_short="$2"

    local prefix="${RELEASES_DIR}/.prepare-${release_short}."
    local suffix="${staging_dir#"$prefix"}"

    [[ "$staging_dir" == "$prefix"* && "$suffix" =~ ^[a-zA-Z0-9]{6}$ ]] \
        || die 'Staging directory is outside the expected releases path.'
}

remove_staging_dir() {
    local staging_dir="$1"
    local release_short="$2"

    validate_staging_dir "$staging_dir" "$release_short"
    [[ -d "$RELEASES_DIR" && ! -L "$RELEASES_DIR" ]] \
        || die 'Releases directory is unavailable.'
    [[ -e "$staging_dir" || -L "$staging_dir" ]] || return 0
    [[ -d "$staging_dir" && ! -L "$staging_dir" ]] \
        || die 'Staging path is not a directory.'
    rm -rf -- "$staging_dir"
}

create_staging_dir() {
    local release_short="$1"

    validate_release_short "$release_short"
    [[ -d "$RELEASES_DIR" && ! -L "$RELEASES_DIR" ]] \
        || die 'Releases directory is unavailable.'
    [[ ! -e "${RELEASES_DIR}/${release_short}" && ! -L "${RELEASES_DIR}/${release_short}" ]] \
        || die 'Release directory already exists.'

    umask 077
    mktemp -d "${RELEASES_DIR}/.prepare-${release_short}.XXXXXX"
}

prepare_release() (
    local staging_dir="$1"
    local release_short="$2"
    local expected_sha="$3"
    local release_dir="${RELEASES_DIR}/${release_short}"
    local archive_name="benehom-${release_short}.tar.gz"
    local archive_path="${staging_dir}/${archive_name}"
    local checksum_path="${archive_path}.sha256"
    local build_dir=''
    local active_public_target=''

    validate_release_short "$release_short"
    validate_full_sha "$expected_sha"
    validate_staging_dir "$staging_dir" "$release_short"
    [[ -d "$RELEASES_DIR" && ! -L "$RELEASES_DIR" ]] \
        || die 'Releases directory is unavailable.'
    [[ -d "$staging_dir" && ! -L "$staging_dir" ]] \
        || die 'Staging directory is unavailable.'

    # Run EXIT before this subshell function's local variables go out of scope.
    cleanup() {
        local exit_code=$?

        trap - EXIT
        if [[ -n "$build_dir" && -d "$build_dir" ]]; then
            rm -rf -- "$build_dir" || { (( exit_code != 0 )) || exit_code=1; }
        fi
        # Isolate cleanup errors so both cleanups run and retain the original failure.
        (remove_staging_dir "$staging_dir" "$release_short") \
            || { (( exit_code != 0 )) || exit_code=1; }
        exit "$exit_code"
    }
    trap cleanup EXIT

    [[ "$expected_sha" == "$release_short"* ]] \
        || die 'Short SHA does not match the expected commit.'
    [[ ! -e "$release_dir" && ! -L "$release_dir" ]] \
        || die 'Release directory already exists.'
    [[ -f "$archive_path" && ! -L "$archive_path" ]] \
        || die 'Release archive is missing.'
    [[ -f "$checksum_path" && ! -L "$checksum_path" ]] \
        || die 'Release checksum is missing.'
    [[ -f "$PERSISTENT_ENV" && ! -L "$PERSISTENT_ENV" ]] \
        || die 'Persistent environment file is unavailable.'
    [[ -L "$PUBLIC_LINK" ]] || die 'public_html is not a symbolic link.'

    active_public_target="$(readlink -f -- "$PUBLIC_LINK")"
    [[ -d "$active_public_target" ]] || die 'Active public directory is unavailable.'
    [[ "$active_public_target" == "${RELEASES_DIR}/"*/public ]] \
        || die 'public_html does not point to a release public directory.'
    [[ "$active_public_target" != "${release_dir}/public" ]] \
        || die 'New release is already active.'

    (
        cd -- "$staging_dir"
        sha256sum --check "$(basename -- "$checksum_path")"
    )

    build_dir="$(mktemp -d "${RELEASES_DIR}/.${release_short}.build.XXXXXX")"
    tar -xzf "$archive_path" -C "$build_dir"

    [[ "$(<"${build_dir}/RELEASE_SHA")" == "$expected_sha" ]] \
        || die 'Release SHA does not match the expected commit.'
    [[ "$(stat -c '%a' "$build_dir")" == '755' ]] \
        || die 'Release directory permissions are not 755.'

    for required_path in app config public vendor composer.json composer.lock; do
        [[ -e "${build_dir}/${required_path}" && ! -L "${build_dir}/${required_path}" ]] \
            || die "Required release path is missing: ${required_path}"
    done
    [[ ! -e "${build_dir}/.env" && ! -L "${build_dir}/.env" ]] \
        || die 'Release archive must not include .env.'
    [[ ! -e "${build_dir}/.git" && ! -L "${build_dir}/.git" ]] \
        || die 'Release archive must not include .git.'

    ln -s ../../.env.v2 "${build_dir}/.env"
    [[ -L "${build_dir}/.env" && "$(readlink -- "${build_dir}/.env")" == '../../.env.v2' ]] \
        || die 'Release environment link is invalid.'
    [[ -f "${build_dir}/.env" ]] || die 'Release environment link does not resolve.'

    # Never merge into an existing directory or follow a destination symlink.
    mv -T -n -- "$build_dir" "$release_dir"
    [[ ! -d "$build_dir" ]] || die 'Release destination appeared during preparation.'
    build_dir=''

    [[ "$(readlink -f -- "$PUBLIC_LINK")" == "$active_public_target" ]] \
        || die 'public_html changed while preparing the release.'
    printf 'Prepared release: %s\n' "$release_short"
    printf 'Active release remains: %s\n' "$(basename -- "$(dirname -- "$active_public_target")")"
)

smoke_release() (
    local release_short="$1"
    local expected_sha="$2"
    local release_dir="${RELEASES_DIR}/${release_short}"
    local work_dir path marker attempt metadata status content_type passed nonce

    validate_release_short "$release_short"
    validate_full_sha "$expected_sha"
    for command in curl cmp grep sleep; do
        require_command "$command"
    done
    [[ -s "${release_dir}/public/css/app.min.css" ]] || die 'Expected CSS is unavailable.'
    work_dir="$(mktemp -d)" || return 1
    trap 'exit_code=$?; trap - EXIT; rm -rf -- "$work_dir" || { (( exit_code != 0 )) || exit_code=1; }; exit "$exit_code"' EXIT
    trap 'exit 129' HUP
    trap 'exit 130' INT
    trap 'exit 143' TERM
    nonce="${expected_sha}-${work_dir##*/}-${RANDOM}"

    # Routes from public/.htaccess, view IDs, and bh_css_tags() in production.
    for path in / /blog /css/app.min.css; do
        passed=false
        for attempt in 1 2 3; do
            if metadata="$(curl --silent --show-error --proto '=https' \
                --connect-timeout 5 --max-time 15 \
                --header 'Cache-Control: no-cache, no-store, max-age=0' \
                --header 'Pragma: no-cache' \
                --output "${work_dir}/body" --dump-header "${work_dir}/headers" \
                --write-out '%{http_code} %{content_type}' \
                "${SITE_URL}${path}?__cd=${nonce}-${attempt}")"; then
                read -r status content_type <<< "$metadata"
                content_type="${content_type,,}"
                # Reject explicit cache hits, stale responses, and positive Age.
                if [[ "$status" == '200' && -s "${work_dir}/body" ]] \
                    && ! grep -Eiq '^(Age:[[:space:]]*0*[1-9][0-9]*|X-(Cache|LiteSpeed-Cache):.*hit|CF-Cache-Status:[[:space:]]*(HIT|STALE|UPDATING)|Warning:[[:space:]]*11[01])' "${work_dir}/headers"; then
                    if [[ "$path" == '/css/app.min.css' ]]; then
                        if [[ "${content_type%%;*}" == 'text/css' ]] \
                            && cmp -s "${work_dir}/body" "${release_dir}/public/css/app.min.css"; then
                            passed=true
                        fi
                    else
                        marker='id="hero-title"'
                        [[ "$path" != '/blog' ]] || marker='id="blog-title"'
                        if [[ "${content_type%%;*}" == 'text/html' ]] \
                            && grep -Eiq '<html([[:space:]>])' "${work_dir}/body" \
                            && grep -Fq "$marker" "${work_dir}/body"; then
                            passed=true
                        fi
                    fi
                fi
            fi
            if [[ "$passed" == true ]]; then
                printf '[OK] HTTPS GET %s\n' "$path"
                break
            fi
            printf 'Smoke check failed: %s (attempt %s/3).\n' "$path" "$attempt" >&2
            if (( attempt < 3 )); then sleep 2 || return 1; fi
        done
        [[ "$passed" == true ]] || return 1
    done

    # Releases anteriores a MCP no deben fallar al verificarse tras un rollback.
    if [[ -f "${release_dir}/app/controllers/McpController.php" ]]; then
        passed=false
        for attempt in 1 2 3; do
            if metadata="$(curl --silent --show-error --proto '=https' \
                --connect-timeout 5 --max-time 15 \
                --request POST \
                --header 'Cache-Control: no-cache, no-store, max-age=0' \
                --header 'Pragma: no-cache' \
                --header 'Content-Type: application/json' \
                --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"benehom-deployment-smoke","version":"1.0.0"}}}' \
                --output /dev/null --dump-header "${work_dir}/headers" \
                --write-out '%{http_code} %{content_type}' \
                "${SITE_URL}/mcp?__cd=${nonce}-${attempt}")"; then
                read -r status content_type <<< "$metadata"
                if [[ "$status" == '401' ]] \
                    && grep -Eiq '^WWW-Authenticate:[[:space:]]*Bearer([[:space:]]|$)' "${work_dir}/headers"; then
                    passed=true
                fi
            fi
            if [[ "$passed" == true ]]; then
                printf '[OK] HTTPS POST /mcp rejects missing Bearer credentials\n'
                break
            fi
            printf 'Smoke check failed: /mcp (attempt %s/3).\n' "$attempt" >&2
            if (( attempt < 3 )); then sleep 2 || return 1; fi
        done
        [[ "$passed" == true ]] || return 1
    fi
)

public_link_matches() {
    [[ -L "$PUBLIC_LINK" \
        && "$(readlink -- "$PUBLIC_LINK")" == "$1" \
        && "$(stat -c '%d:%i:%y' -- "$PUBLIC_LINK")" == "$2" ]]
}

activate_release() (
    local release_short="$1"
    local expected_sha="$2"
    local release_dir="${RELEASES_DIR}/${release_short}"
    local new_target="releases/${release_short}/public"
    local previous_target previous_identity previous_public previous_dir previous_short previous_sha
    local work_dir new_identity rollback_identity
    local activation_attempted=false

    validate_release_short "$release_short"
    validate_full_sha "$expected_sha"
    [[ "$expected_sha" == "$release_short"* ]] || die 'Short SHA does not match the expected commit.'
    for command in flock curl cmp grep sleep; do
        require_command "$command"
    done
    [[ -d "$RELEASES_DIR" && ! -L "$RELEASES_DIR" ]] || die 'Releases directory is unavailable.'
    # Hold this lock through verification and rollback. Manual activations must
    # use the same lock; identity checks also detect out-of-band symlink changes.
    exec 9<"$RELEASES_DIR"
    flock -n 9 || die 'Another activation holds the releases lock.'

    # The final directory only exists after preparation. Recheck its identity
    # and persistent link, without repeating archive/build validation.
    [[ -d "$release_dir" && ! -L "$release_dir" \
        && -f "${release_dir}/RELEASE_SHA" \
        && "$(<"${release_dir}/RELEASE_SHA")" == "$expected_sha" \
        && -f "${release_dir}/public/index.php" \
        && -s "${release_dir}/public/css/app.min.css" \
        && -L "${release_dir}/.env" \
        && "$(readlink -- "${release_dir}/.env")" == '../../.env.v2' \
        && -f "${release_dir}/.env" ]] || die 'Release is not prepared for activation.'

    [[ -L "$PUBLIC_LINK" ]] || die 'public_html is not a symbolic link.'
    previous_target="$(readlink -- "$PUBLIC_LINK")"
    previous_identity="$(stat -c '%d:%i:%y' -- "$PUBLIC_LINK")"
    previous_public="$(readlink -f -- "$PUBLIC_LINK")"
    previous_dir="$(dirname -- "$previous_public")"
    previous_short="$(basename -- "$previous_dir")"
    validate_release_short "$previous_short"
    [[ "$previous_public" == "${RELEASES_DIR}/${previous_short}/public" \
        && "$previous_dir" != "$release_dir" \
        && -d "$previous_public" && ! -L "$previous_dir" \
        && -f "${previous_public}/index.php" \
        && -s "${previous_public}/css/app.min.css" \
        && -f "${previous_dir}/RELEASE_SHA" \
        && -L "${previous_dir}/.env" \
        && "$(readlink -- "${previous_dir}/.env")" == '../../.env.v2' \
        && -f "${previous_dir}/.env" ]] || die 'Previous release cannot be used for rollback.'
    previous_sha="$(<"${previous_dir}/RELEASE_SHA")"
    validate_full_sha "$previous_sha"
    [[ "$previous_sha" == "$previous_short"* ]] || die 'Previous release SHA is inconsistent.'

    work_dir="$(mktemp -d "${DOMAIN_ROOT}/.activation.XXXXXX")"
    finish_activation() {
        local exit_code=$?
        local rollback_ok=false

        trap - EXIT
        # Logging or cleanup failures must never interrupt recovery or replace
        # the deployment's original exit code (including a disconnected client).
        set +e
        # Finish recovery even if the SSH client disconnects or sends another signal.
        trap '' HUP INT TERM PIPE
        if (( exit_code != 0 )) && [[ "$activation_attempted" == true ]]; then
            printf 'Deployment failed; previous target: %s\n' "$previous_target" >&2
            if public_link_matches "$new_target" "$new_identity"; then
                # The saved link preserves the exact previous target (relative or absolute).
                if mv -Tf -- "${work_dir}/previous" "$PUBLIC_LINK" \
                    && public_link_matches "$previous_target" "$rollback_identity" \
                    && [[ "$(<"${previous_dir}/RELEASE_SHA")" == "$previous_sha" ]] \
                    && smoke_release "$previous_short" "$previous_sha" \
                    && public_link_matches "$previous_target" "$rollback_identity"; then
                    rollback_ok=true
                    printf 'Rollback verified; stable release restored: %s\n' "$previous_short" >&2
                fi
            elif public_link_matches "$previous_target" "$previous_identity"; then
                # The atomic activation failed before changing public_html.
                rollback_ok=true
                printf 'Activation did not change public_html; previous release retained.\n' >&2
            fi
            if [[ "$rollback_ok" != true ]]; then
                printf 'ERROR: Rollback failed or public_html was changed externally. Manual intervention required.\n' >&2
            fi
        fi
        rm -rf -- "$work_dir" || { (( exit_code != 0 )) || exit_code=1; }
        exit "$exit_code"
    }
    trap finish_activation EXIT
    trap 'exit 129' HUP
    trap 'exit 130' INT
    trap 'exit 143' TERM
    trap 'exit 141' PIPE

    ln -s -- "$previous_target" "${work_dir}/previous"
    rollback_identity="$(stat -c '%d:%i:%y' -- "${work_dir}/previous")"
    ln -s -- "$new_target" "${work_dir}/next"
    new_identity="$(stat -c '%d:%i:%y' -- "${work_dir}/next")"
    public_link_matches "$previous_target" "$previous_identity" \
        || die 'public_html changed before activation.'
    activation_attempted=true
    # Same-filesystem rename replaces the symlink atomically, never its target.
    mv -Tf -- "${work_dir}/next" "$PUBLIC_LINK"
    public_link_matches "$new_target" "$new_identity" || die 'Activated link does not match this deployment.'
    [[ "$(<"${release_dir}/RELEASE_SHA")" == "$expected_sha" ]] || die 'Active SHA is incorrect.'
    smoke_release "$release_short" "$expected_sha"
    public_link_matches "$new_target" "$new_identity" || die 'public_html changed during smoke tests.'
    [[ "$(<"${release_dir}/RELEASE_SHA")" == "$expected_sha" ]] || die 'Active SHA changed during smoke tests.'
    printf '[OK] Deployment verified: %s\n' "$expected_sha"
)

for required_command in basename dirname ln mktemp mv readlink rm sha256sum stat tar; do
    require_command "$required_command"
done

case "${1:-}" in
    create-staging)
        [[ $# -eq 2 ]] || die 'Usage: create-staging <short-sha>'
        create_staging_dir "$2"
        ;;
    prepare)
        [[ $# -eq 4 ]] || die 'Usage: prepare <staging-dir> <short-sha> <full-sha>'
        prepare_release "$2" "$3" "$4"
        ;;
    cleanup)
        [[ $# -eq 3 ]] || die 'Usage: cleanup <staging-dir> <short-sha>'
        validate_release_short "$3"
        remove_staging_dir "$2" "$3"
        ;;
    activate)
        [[ $# -eq 3 ]] || die 'Usage: activate <short-sha> <full-sha>'
        activate_release "$2" "$3"
        ;;
    smoke)
        [[ $# -eq 3 ]] || die 'Usage: smoke <short-sha> <full-sha>'
        smoke_release "$2" "$3"
        ;;
    *)
        die 'Expected mode: create-staging, prepare, cleanup, activate, or smoke.'
        ;;
esac
