#!/bin/bash

# To install (download to a file, then run it):
#   cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)" && sudo bash setup.sh
#
# Do NOT pipe it (curl ... | bash): the interactive prompts would read the
# script's own text from stdin and corrupt the run. The script detects a piped
# invocation and refuses to start.
#
# Options:
#   --database=<path>, --db=<path>
#       Import the given SQL dump into the 'vlsm' database instead of the
#       bundled sql/init.sql seed. Accepts absolute or relative paths.
#       Supported formats: .sql, .sql.gz, .sql.zst
#       Equivalent long forms also work: --database <path> | --db <path>
#
#   --db-strategy=<drop|rename|use>
#       What to do if a 'vlsm' database already exists:
#         drop   - delete the existing database and create a fresh one
#         rename - back it up to vlsm_YYYYMMDD_HHMMSS, then create fresh (default)
#         use    - keep it as-is and skip the import entirely
#       May also be supplied via the INTELIS_DB_STRATEGY env var.
#       If omitted and a non-empty 'vlsm' DB is found, the script will prompt.
#
#   --php=<version>
#       PHP major.minor version to install via lamp-setup.sh (e.g. 8.4, 8.5).
#       Defaults to the OS baseline: 8.4 on Ubuntu <=24.04, 8.5 on 26.04+
#       (which no longer ships 8.4). Equivalent long form: --php <version>
#
#   --server-name=<name>
#       Serve this installation under the given hostname instead of the default
#       'intelis'. Setup only asks for a web address on a central server (STS);
#       use this for the uncommon lab machine that has an address of its own.
#
#   --resume
#       Skip the database setup/import step (only allowed after a previous
#       successful import; requires the setup-db-complete.checkpoint file).
#
# Examples:
#   sudo bash setup.sh --database=/root/backup.sql.gz
#   sudo bash setup.sh --db ./dump.sql --db-strategy=drop
#   sudo bash setup.sh --php=8.5 --database=/root/backup.sql.gz
#   sudo INTELIS_DB_STRATEGY=use bash setup.sh

# Refuse to run when piped into a shell (curl ... | bash). With a pipe the
# shell's stdin IS the script, so the interactive prompts below would consume the
# script's own lines instead of the operator's input — corrupting the hostname,
# the STS URL, etc., and eventually failing with a confusing "syntax error".
# Download the script to a file first, then run it. (A real file argument makes
# BASH_SOURCE[0] point at an existing file; a piped run leaves it empty.)
if [ ! -f "${BASH_SOURCE[0]:-/dev/null}" ]; then
    echo "ERROR: Do not pipe this script into bash — the prompts will read the script itself."
    echo "Download it first, then run it:"
    echo "  wget -O setup.sh \"https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=\$(date +%s)\""
    echo "  sudo bash setup.sh"
    exit 1
fi

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print error "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo"
    exit 1
fi

# Download and update shared-functions.sh
SHARED_FN_PATH="/usr/local/lib/intelis/shared-functions.sh"
SHARED_FN_URL="https://raw.githubusercontent.com/deforay/intelis/master/scripts/shared-functions.sh"

mkdir -p "$(dirname "$SHARED_FN_PATH")"

# wget where it exists, curl otherwise — a minimal Ubuntu image has curl and no
# wget, and this is line one of an install, so failing here means failing before
# anything has told the operator what is wrong.
if command -v wget >/dev/null 2>&1; then
    download_to() { wget -q -O "$1" "$2"; }
elif command -v curl >/dev/null 2>&1; then
    download_to() { curl -fsSL -o "$1" "$2"; }
else
    download_to() { return 1; }
fi

# Stage the download and swap it in only once it looks like the real thing:
# `wget -O` and `curl -o` both truncate the destination before transferring, so
# a network hiccup leaves a zero-byte file that exists, sources cleanly, and
# defines nothing. See the same guard in upgrade.sh, where that reached a lab.
fetch_shared_fn() {
    local dest="$1" url="$2" tmp
    tmp="$(mktemp "${dest}.XXXXXX" 2>/dev/null)" || return 1
    if download_to "$tmp" "$url" && [ -s "$tmp" ] && grep -q '^prepare_system()' "$tmp"; then
        mv -f "$tmp" "$dest"
        return 0
    fi
    rm -f "$tmp"
    return 1
}

if fetch_shared_fn "$SHARED_FN_PATH" "$SHARED_FN_URL"; then
    chmod +x "$SHARED_FN_PATH"
    echo "Downloaded shared-functions.sh."
else
    echo "Failed to download shared-functions.sh."
    if [ ! -f "$SHARED_FN_PATH" ]; then
        echo "shared-functions.sh missing. Cannot proceed."
        exit 1
    fi
    echo "Continuing with the copy already on this machine."
fi

# Source the shared functions
source "$SHARED_FN_PATH"

# Existing is not the same as usable — check for a function it must define
# before prepare_system runs anything as root.
if ! declare -F prepare_system >/dev/null 2>&1; then
    echo "shared-functions.sh at $SHARED_FN_PATH is unusable (truncated or corrupt)."
    echo "Delete it and run this again: sudo rm -f $SHARED_FN_PATH"
    exit 1
fi

prepare_system

log_file="/tmp/intelis-setup-$(date +'%Y%m%d-%H%M%S').log"

# Error trap
trap 'error_handling "${BASH_COMMAND}" "$LINENO" "$?"' ERR

# Capture the directory we were launched from so the EXIT cleanup can still find
# transient downloads after we cd into the install path mid-run.
SETUP_CWD="$(pwd)"

# Best-effort cleanup of transient artifacts so an aborted run doesn't leave
# half-downloaded tarballs or temp dirs behind for the next attempt.
cleanup_on_exit() {
    [ -n "${temp_dir:-}" ] && rm -rf "${temp_dir}" 2>/dev/null || true
    rm -f "${SETUP_CWD}/master.tar.gz" "${SETUP_CWD}/lamp-setup.sh" 2>/dev/null || true
    [ -n "${lis_path:-}" ] && rm -f "${lis_path}/vendor.tar.gz" "${lis_path}/vendor.tar.gz.md5" 2>/dev/null || true
    # Plaintext decrypted from an encrypted backup must never be left on disk.
    [ -n "${_GPG_DECRYPT_TMP:-}" ] && rm -rf "${_GPG_DECRYPT_TMP}" 2>/dev/null || true
}
trap cleanup_on_exit EXIT

# --- DB strategy resolution: env/flag/prompt ---
resolve_db_strategy() {
    local strategy="$1"        # from flag (optional)
    local env_strategy="${INTELIS_DB_STRATEGY:-}"
    local resolved=""

    # explicit CLI flag wins
    if [[ -n "$strategy" ]]; then
        resolved="$strategy"
    elif [[ -n "$env_strategy" ]]; then
        resolved="$env_strategy"
    fi

    # normalize
    case "$resolved" in
        drop|DROP)   resolved="drop"   ;;
        rename|RENAME) resolved="rename" ;;
        use|USE|keep|KEEP) resolved="use" ;;
        "") resolved="" ;;
        *)  echo "Unknown db strategy: $resolved"; resolved="";;
    esac

    echo "$resolved"
}

prompt_db_strategy() {
    local tty="/dev/tty"
    # Same three answers as the upfront question, worded the same way. This is
    # the copy most operators will actually see, because the upfront one is only
    # asked when a database was already found before setup started.
    #
    # Written straight to the tty because the caller captures stdout.
    {
        echo
        echo "An existing 'vlsm' database with data in it was found."
        echo "  1) Keep a copy, then start fresh — renamed to vlsm_YYYYMMDD_HHMMSS, nothing is lost (default)"
        echo "  2) Leave it alone and use it   — no import; choose this when reinstalling over live data"
        echo "  3) Delete it                   — the existing data is destroyed permanently"
    } >"$tty"

    # The order matches the list above, safest first, and anything unrecognised
    # falls to the same safe answer. Nothing here may map to "drop" by accident:
    # that branch deletes a lab's data with no copy kept.
    read -r -p "Enter choice [1=keep a copy (default), 2=use it, 3=delete]: " choice <"$tty"
    case "${choice:-1}" in
        1) echo "rename" ;;
        2) echo "use"    ;;
        3) echo "drop"   ;;
        *) echo "rename" ;;
    esac
}



mysql_exec() { mysql -e "$*"; }


handle_database_setup_and_import() {
    local sql_file="${1:-${lis_path}/sql/init.sql}"
    local default_sql_file="${lis_path}/sql/init.sql"
    local is_user_supplied_dump=false

    # Clear the checkpoint before starting so interrupted imports cannot be resumed as if they succeeded.
    rm -f "${db_setup_checkpoint_file}"

    if [[ -n "$1" ]]; then
        is_user_supplied_dump=true
    fi

    # Detect DB status
    local db_exists db_not_empty
    db_exists=$(mysql -sse "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='vlsm';")
    db_not_empty=$(mysql -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='vlsm';")

    # Helper: rename + reset vlsm
    perform_backup_rename() {
        echo "Backing up and resetting 'vlsm'..."
        log_action "Renaming existing 'vlsm' database to backup and recreating..."
        ts="$(date +%Y%m%d_%H%M%S)"
        new_db_name="vlsm_${ts}"
        mysql_exec "CREATE DATABASE ${new_db_name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

        # Collect all base tables
        mapfile -t _tables < <(mysql -Nse "SELECT TABLE_NAME FROM information_schema.tables
                                        WHERE table_schema='vlsm' AND TABLE_TYPE='BASE TABLE';")

        if ((${#_tables[@]})); then
            # Build one atomic RENAME TABLE statement: RENAME TABLE vlsm.`t1` TO vlsm_ts.`t1`, ...
            rename_sql="RENAME TABLE "
            sep=""
            for t in "${_tables[@]}"; do
                rename_sql+="${sep}vlsm.\`${t}\` TO ${new_db_name}.\`${t}\`"
                sep=", "
            done
            mysql_exec "SET FOREIGN_KEY_CHECKS=0; ${rename_sql}; SET FOREIGN_KEY_CHECKS=1;"
        fi

        # Recreate views in backup (strip DEFINER)
        while read -r view; do
            [[ -z "$view" ]] && continue
            def=$(mysql -Nse "SHOW CREATE VIEW vlsm.\`${view}\`\G" | sed -n 's/^ *Create View: \(.*\)$/\1/p' | sed -E 's/DEFINER=`[^`]+`@`[^`]+` //')
            [[ -n "$def" ]] && mysql -D "${new_db_name}" -e "$def"
        done < <(mysql -Nse "SELECT TABLE_NAME FROM information_schema.views WHERE table_schema='vlsm';")

        # Remove the now-empty schema and recreate fresh to avoid leftover routines/events
        mysql_exec "DROP DATABASE vlsm;"
        mysql_exec "CREATE DATABASE vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        echo "Backup complete: ${new_db_name}"

        # Prune old renamed backups (keep the most recent BACKUP_KEEP) so repeated
        # rename-strategy runs don't accumulate full DB copies and fill the disk.
        mapfile -t _old_db_backups < <(mysql -Nse "SELECT schema_name FROM information_schema.schemata WHERE schema_name REGEXP '^vlsm_[0-9]{8}_[0-9]{6}$' ORDER BY schema_name DESC;" | tail -n +$((BACKUP_KEEP + 1)))
        for _old_db in "${_old_db_backups[@]}"; do
            [ -z "$_old_db" ] && continue
            print info "Pruning old database backup: ${_old_db}"
            mysql_exec "DROP DATABASE \`${_old_db}\`;"
            log_action "Pruned old database backup: ${_old_db}"
        done
    }

    recreate_vlsm_database() {
        mysql_exec "SET FOREIGN_KEY_CHECKS=0; DROP DATABASE IF EXISTS vlsm; SET FOREIGN_KEY_CHECKS=1;"
        mysql_exec "CREATE DATABASE vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    }

    # Inspect magic bytes (and tar header at offset 257) to figure out what
    # the file actually is, rather than trusting the extension. This catches
    # tar.gz archives renamed to .sql.gz, truncated dumps, and other lies.
    detect_dump_format() {
        local file="$1"
        local hex2 hex4

        hex2=$(head -c 2 "$file" 2>/dev/null | od -An -tx1 | tr -d ' \n')
        hex4=$(head -c 4 "$file" 2>/dev/null | od -An -tx1 | tr -d ' \n')

        # gzip: 1f 8b — could be a gzipped SQL dump, or a tar.gz archive.
        if [[ "$hex2" == "1f8b" ]]; then
            local inner_magic
            inner_magic=$(gunzip -c "$file" 2>/dev/null | dd bs=1 skip=257 count=5 2>/dev/null)
            if [[ "$inner_magic" == "ustar" ]]; then
                echo "tar.gz"
            else
                echo "gzip"
            fi
            return
        fi

        # zstd: 28 b5 2f fd
        if [[ "$hex4" == "28b52ffd" ]]; then
            echo "zstd"
            return
        fi

        # Uncompressed tar: "ustar" at offset 257
        if [[ "$(dd if="$file" bs=1 skip=257 count=5 2>/dev/null)" == "ustar" ]]; then
            echo "tar"
            return
        fi

        # Plain SQL heuristic: look for typical mysqldump tokens in the head.
        if head -c 2048 "$file" 2>/dev/null \
            | grep -aqiE '(^|[[:space:]])(--[[:space:]]|/\*|CREATE[[:space:]]|INSERT[[:space:]]|DROP[[:space:]]|SET[[:space:]]|USE[[:space:]]|LOCK[[:space:]]|START[[:space:]]+TRANSACTION)'; then
            echo "sql"
            return
        fi

        echo "unknown"
    }

    import_sql_dump_into_vlsm() {
        local import_file="$1"
        local import_pid import_status detected

        # Encrypted (.gpg) backup: decrypt to a temp file with its inner name, then
        # fall through to the normal detect/validate/import path unchanged. db-tools
        # encrypts with gpg --symmetric AES256. The passphrase is resolved in order:
        #   1) --encryption-password  (offline recovery code / fixed key),
        #   2) --recovery-token       (exchanged with the STS for the key),
        #   3) legacy backups         (this machine's DB password + the 32-char
        #      filename token — works for a same-machine reinstall).
        if [[ "$import_file" == *.gpg ]]; then
            if ! command -v gpg >/dev/null 2>&1; then
                print error "Backup ${import_file} is encrypted but gpg is not installed."
                log_action "gpg missing for encrypted import: ${import_file}"
                return 1
            fi

            local _pp="" _base
            _base="$(basename "$import_file")"
            if [[ -n "$intelis_enc_password" ]]; then
                _pp="$intelis_enc_password"
            elif [[ -n "$intelis_recovery_token" ]]; then
                if [[ -z "$remote_sts_url" ]]; then
                    print error "--recovery-token needs an STS URL, but none is configured."
                    log_action "recovery-token given without STS URL"
                    return 1
                fi
                print info "Retrieving backup key from STS using the recovery token..."
                _pp="$(curl -fsS -X POST "${remote_sts_url%/}/remote/v2/backup-key-release.php" \
                        -H 'Content-Type: application/json' \
                        --data "{\"recoveryToken\":\"${intelis_recovery_token}\"}" 2>/dev/null \
                        | sed -n 's/.*"key"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p')"
                if [[ -z "$_pp" ]]; then
                    print error "Could not retrieve the backup key from the STS."
                    print info  "Check the STS URL, and that the token is approved and not expired or already used."
                    log_action "STS backup key release failed for ${import_file}"
                    return 1
                fi
            elif [[ "$_base" =~ -[0-9]{8}-[0-9]{6}-([A-Za-z0-9]{32})\. ]]; then
                _pp="${mysql_root_password}${BASH_REMATCH[1]}"
            else
                print error "Encrypted backup needs a key: ${import_file}"
                print info  "Re-run with --recovery-token=<token from your STS admin> or --encryption-password=<recovery code>."
                log_action "Encrypted backup without key material: ${import_file}"
                return 1
            fi

            _GPG_DECRYPT_TMP="$(mktemp -d)"
            local _decrypted="${_GPG_DECRYPT_TMP}/$(basename "${import_file%.gpg}")"
            if ! printf '%s' "$_pp" | gpg --batch --yes --pinentry-mode loopback \
                    --passphrase-fd 0 -o "$_decrypted" --decrypt "$import_file" 2>/dev/null; then
                print error "Failed to decrypt ${import_file} (wrong key, or corrupt file)."
                if [[ -z "${intelis_enc_password}${intelis_recovery_token}" ]]; then
                    print info "This may be a legacy encrypted backup from another machine."
                    print info "Pass that machine's key as --encryption-password=<code>, or use --recovery-token."
                fi
                log_action "gpg decryption failed for ${import_file}"
                return 1
            fi
            print info "Decrypted ${_base} for import."
            log_action "Decrypted encrypted backup ${_base}"
            import_file="$_decrypted"
        fi

        detected="$(detect_dump_format "$import_file")"

        # Reject archives outright — these are not SQL dumps and would feed
        # mysql binary garbage.
        case "$detected" in
            tar|tar.gz)
                print error "Refusing to import ${detected} archive: ${import_file}"
                print info  "Expected a mysqldump-style file (.sql, .sql.gz, or .sql.zst), not a tar archive."
                log_action "Refused archive (${detected}) presented as SQL dump: ${import_file}"
                return 1
                ;;
            unknown)
                print error "Could not identify ${import_file} as SQL, gzip, or zstd."
                print info  "File may be truncated, encrypted, or in an unsupported format."
                log_action "Unrecognized dump format: ${import_file}"
                return 1
                ;;
        esac

        # Cross-check the declared extension against the detected content so
        # mislabeled files fail loudly before we touch the database.
        case "$import_file" in
            *.sql.gz|*.gz)
                if [[ "$detected" != "gzip" ]]; then
                    print error "${import_file} has a .gz extension but is not gzip-compressed (detected: ${detected})."
                    log_action "Extension/content mismatch (.gz vs ${detected}): ${import_file}"
                    return 1
                fi
                ;;
            *.sql.zst|*.zst)
                if [[ "$detected" != "zstd" ]]; then
                    print error "${import_file} has a .zst extension but is not zstd-compressed (detected: ${detected})."
                    log_action "Extension/content mismatch (.zst vs ${detected}): ${import_file}"
                    return 1
                fi
                ;;
            *.sql)
                if [[ "$detected" != "sql" ]]; then
                    print error "${import_file} has a .sql extension but content looks like ${detected}."
                    log_action "Extension/content mismatch (.sql vs ${detected}): ${import_file}"
                    return 1
                fi
                ;;
        esac

        # Ensure required decompressor is available before kicking off the import.
        if [[ "$detected" == "zstd" ]] && ! command -v zstd >/dev/null 2>&1; then
            print error "zstd is not installed but ${import_file} is zstd-compressed."
            print info  "Install zstd (e.g. 'apt-get install -y zstd') and retry."
            log_action "Missing zstd binary for import of ${import_file}"
            return 1
        fi

        # Run the import in a child process so we can show progress for large dumps.
        (
            set -o pipefail

            case "$detected" in
                gzip) gunzip -c "$import_file" | mysql vlsm ;;
                zstd) zstd -dc  "$import_file" | mysql vlsm ;;
                sql)  mysql vlsm < "$import_file" ;;
            esac
        ) &
        import_pid=$!

        spinner "${import_pid}" "Importing database dump (${detected})..."
        wait "${import_pid}"
        import_status=$?

        return "${import_status}"
    }

    prompt_failed_import_fallback() {
        local failed_file="$1"
        local tty="/dev/tty"

        {
            echo
            echo "Import failed for: ${failed_file}"
            echo "Choose how to continue:"
            echo "  1) SEED  – reset 'vlsm' and import the default init.sql"
            echo "  2) BLANK – reset 'vlsm' and continue with an empty database"
            echo "  3) ABORT – stop setup"
        } >"$tty"

        read -r -p "Enter choice [1=SEED(default), 2=BLANK, 3=ABORT]: " choice <"$tty"
        case "${choice:-1}" in
            1) echo "seed"  ;;
            2) echo "blank" ;;
            3) echo "abort" ;;
            *) echo "seed"  ;;
        esac
    }

    local strategy
    strategy="$(resolve_db_strategy "$DB_STRATEGY_FLAG")"
    if [[ -z "$strategy" && "$db_exists" -eq 1 && "$db_not_empty" -gt 0 ]]; then
    strategy="$(prompt_db_strategy)"
    fi
    echo "→ Selected strategy: ${strategy:-rename}"

    if [[ "$db_exists" -eq 1 && "$db_not_empty" -gt 0 ]]; then
        case "$strategy" in
            drop)
                echo "Dropping existing 'vlsm' database..."
                log_action "Dropping existing 'vlsm' database..."
                mysql_exec "SET FOREIGN_KEY_CHECKS=0; DROP DATABASE IF EXISTS vlsm; SET FOREIGN_KEY_CHECKS=1;"
                mysql_exec "CREATE DATABASE vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
                ;;
            rename)
                perform_backup_rename
                ;;
            use)
                echo "Using existing 'vlsm' database as-is. Skipping schema import."
                log_action "Using existing vlsm database; skipping import."
                mysql -e "CREATE DATABASE IF NOT EXISTS interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
                [[ -f "${lis_path}/sql/interface-init.sql" ]] && mysql interfacing < "${lis_path}/sql/interface-init.sql" 2>/dev/null || true
                return 0
                ;;
            *)
                echo "No valid db strategy supplied; defaulting to RENAME."
                perform_backup_rename
                ;;
        esac
    else
        # Ensure DBs exist if we got here with empty/non-existent db
        mysql -e "CREATE DATABASE IF NOT EXISTS vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    fi

    mysql -e "CREATE DATABASE IF NOT EXISTS interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    echo "Importing base schema into 'vlsm' from: ${sql_file}"
    if ! import_sql_dump_into_vlsm "$sql_file"; then
        if [[ "$is_user_supplied_dump" == true ]]; then
            print warning "Import failed for user-provided dump: ${sql_file}"
            log_action "User-provided database import failed: ${sql_file}"

            fallback_choice="$(prompt_failed_import_fallback "$sql_file")"
            case "$fallback_choice" in
                seed)
                    # Recreate the database first so we do not keep a partially imported schema.
                    recreate_vlsm_database
                    echo "Falling back to default seed: ${default_sql_file}"
                    log_action "Falling back to default seed after failed import: ${sql_file}"
                    import_sql_dump_into_vlsm "$default_sql_file"
                    ;;
                blank)
                    # Recreate the database first so we leave the instance in a known empty state.
                    recreate_vlsm_database
                    echo "Continuing with a blank 'vlsm' database."
                    log_action "Continuing with blank database after failed import: ${sql_file}"
                    ;;
                *)
                    echo "Aborting setup because the provided database import failed."
                    log_action "Setup aborted after failed database import: ${sql_file}"
                    return 1
                    ;;
            esac
        else
            return 1
        fi
    fi

    # Audit Trail v2 triggers are created later (after `composer post-install`
    # runs migrations so audit_log exists). The legacy sql/audit-triggers.sql
    # was retired with v2.
    [[ -f "${lis_path}/sql/interface-init.sql"   ]] && mysql interfacing  < "${lis_path}/sql/interface-init.sql"

    echo "Database setup/import completed."
    log_action "Database setup/import completed (strategy: ${strategy:-create})."

    mkdir -p "$(dirname "${db_setup_checkpoint_file}")"
    echo "completed" >"${db_setup_checkpoint_file}"
    log_action "Database setup checkpoint written to ${db_setup_checkpoint_file}."
}


# Gather every interactive answer up front so the rest of the run is
# unattended. Anything that needs MySQL or extracted files (password
# verification, ~/.my.cnf write, vhost config, picking individual maintenance
# scripts) is deferred to its original place but uses the values collected here.
# Validator for the local-address answer, passed to ask_text.
#
# It rejects, in order: anything that is not a hostname, the STS address, and
# (after a confirmation) a name that already belongs to some other server.
#
# The middle check is the one that matters. A lab machine is no longer asked for
# a name at all, which removes the path that actually broke a lab, but a name
# can still arrive here through --server-name or a saved answers file — and the
# operator supplying it is often holding the STS URL at the time.
#
# The cleaned-up name is published in _validated_hostname rather than returned,
# because ask_text hands back what was typed and the caller wants what it meant.
_validate_local_hostname() {
    local candidate="$1" normalized sts_host resolved
    normalized="$(normalize_hostname_input "$candidate")"

    if ! is_valid_hostname "$normalized"; then
        print error "'${candidate}' is not a valid hostname."
        print error "Use letters, digits, dots and hyphens — for example: lab.health.gov.zm"
        return 1
    fi

    if [ -n "${remote_sts_url:-}" ]; then
        sts_host="$(normalize_hostname_input "$remote_sts_url")"
        if [ -n "$sts_host" ] && [ "$normalized" = "$sts_host" ]; then
            print error "That is the STS address you gave a moment ago (${remote_sts_url})."
            print error "This question is asking for the address of THIS machine."
            print error "Pointing it at the STS name would make this server answer for the STS, and syncing would stop completely."
            log_action "Rejected hostname '${normalized}': same host as the STS URL"
            return 1
        fi
    fi

    resolved="$(dns_resolve_host "$normalized")"
    if [ -n "$resolved" ] && ! ip_is_local "$resolved"; then
        print warning "${normalized} already resolves to ${resolved}, which is not this machine."
        print warning "Using it here would make this server answer for a name that belongs to another one."
        if ! ask_yes_no "Use ${normalized} anyway?" "no"; then
            return 1
        fi
        log_action "Operator confirmed hostname '${normalized}' despite resolving to ${resolved}"
    fi

    if [ "$normalized" != "$candidate" ]; then
        print info "Using: ${normalized}"
    fi

    _validated_hostname="$normalized"
    return 0
}

# Is there an existing, non-empty 'vlsm' database on this machine?
#
# Prints "yes", "no", or "unknown". Only "no" is acted on, and only to skip a
# question: everything else falls through to asking, because being wrong about
# this in the cautious direction costs one prompt and being wrong in the other
# direction costs a database.
#
# On a clean server there is no mysql binary at all, which is the common case
# and the cheapest possible answer.
_vlsm_database_state() {
    command -v mysql >/dev/null 2>&1 || { printf 'no'; return; }

    local tables
    tables=$(mysql -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='vlsm';" 2>/dev/null) || {
        printf 'unknown'
        return
    }

    [[ "$tables" =~ ^[0-9]+$ ]] || { printf 'unknown'; return; }
    [ "$tables" -gt 0 ] && printf 'yes' || printf 'no'
}

collect_user_inputs() {
    # The ERR trap is fatal on any non-zero exit. read can legitimately return
    # non-zero (timeout, EOF, etc.), so disable the trap across the whole
    # collection phase and restore it before returning.
    local saved_trap
    saved_trap=$(trap -p ERR)
    trap - ERR

    # If a previous run saved its answers, offer to reuse them so a retry after
    # a mid-run failure doesn't re-ask everything (and can run unattended).
    local answers_file="/usr/local/lib/intelis/setup-answers.env"
    local _cli_db_strategy="$DB_STRATEGY_FLAG"
    if [ -f "$answers_file" ]; then
        print info "Found saved answers from a previous run: ${answers_file}"
        if ask_yes_no "Reuse your previous setup answers and skip the prompts?" "yes"; then
            # shellcheck disable=SC1090
            source "$answers_file"
            reuse_saved_answers=true
            log_action "Reusing saved setup answers from ${answers_file}"
        else
            rm -f "$answers_file"
            reuse_saved_answers=false
        fi
    fi
    # An explicit --db-strategy on the command line always wins over a saved one.
    [ -n "$_cli_db_strategy" ] && DB_STRATEGY_FLAG="$_cli_db_strategy"

    print header "Setup configuration — please answer the following prompts"
    echo "After this, setup will run unattended. (lamp-setup.sh may still"
    echo "prompt internally; that sub-script is out of our control.)"
    echo

    # Count the questions this particular machine will be asked, so the step
    # numbers describe the run in front of the operator rather than a general
    # case. Where to install and what this machine is are always asked; an
    # address question is always asked too, but which one depends on the role,
    # so a supplied flag can only be counted out when it covers both.
    vlsm_db_state="$(_vlsm_database_state)"
    local _total=3
    if [ -n "$intelis_sts_url_flag" ] && [ -n "$intelis_server_name_flag" ]; then
        _total=2
    fi
    [ -f ~/.my.cnf ] || _total=$((_total + 1))
    if ! $resume_setup && [ -z "$DB_STRATEGY_FLAG" ] && [ "$vlsm_db_state" != "no" ]; then
        _total=$((_total + 1))
    fi
    _total=$((_total + 1))
    ui_steps_total "$_total"

    # --- 1. Where to install ---
    # The old prompt timed out after 60 seconds and chose the default silently,
    # so an operator who stopped to check something came back to a decision
    # already made for them. Nothing here is on a clock.
    if ! $reuse_saved_answers; then
        ui_step_next "Where to install"
        ask_text lis_path "/var/www/intelis" "Installation directory" "" "/var/www/intelis"
        print info "Installing into ${lis_path}"
    else
        print info "Reusing saved LIS path: ${lis_path}"
    fi
    log_action "LIS installation path is set to ${lis_path}."
    db_setup_checkpoint_file="${lis_path}/var/run/setup-db-complete.checkpoint"

    # --- Preflight: resume mode (needs the path; asks nothing) ---
    if $resume_setup; then
        intelis_sql_file=""
        DB_STRATEGY_FLAG=""
        if [ ! -f "${db_setup_checkpoint_file}" ]; then
            print error "Resume mode is only available after a successful database setup/import. No checkpoint was found at ${db_setup_checkpoint_file}."
            log_action "Resume mode rejected because the database setup checkpoint was missing."
            exit 1
        fi
        print info "Resume mode enabled. Database setup/import will be skipped."
        log_action "Resume mode enabled. Skipping database setup/import."
    fi

    # --- Preflight: existing installation ---
    if ! $reuse_saved_answers && [ -d "${lis_path}" ] && [ -n "$(ls -A ${lis_path} 2>/dev/null)" ]; then
        if [ -f "${lis_path}/composer.json" ] || [ -f "${lis_path}/bootstrap.php" ]; then
            print warning "InteLIS installation detected at ${lis_path}"
            if ask_yes_no "An existing InteLIS installation was found. Do you want to proceed with setup (this will update/overwrite the installation)?" "yes"; then
                print info "Proceeding with setup. Existing installation will be backed up."
                log_action "User chose to proceed with setup over existing installation"
            else
                print info "Setup cancelled by user."
                log_action "Setup cancelled - existing installation found"
                exit 0
            fi
        fi
    fi

    # --- Preflight: SQL dump file (path came from --database/--db; asks nothing) ---
    # Resolve the "latest" keyword to the newest db-tools backup so you can feed a
    # routine backup straight into a (re)install without hunting for a timestamp:
    #   --db latest            -> newest backup in <lis_path>/backups/db
    #   --db latest:/some/dir  -> newest backup in /some/dir (e.g. a mounted disk)
    if [[ "$intelis_sql_file" == "latest" || "$intelis_sql_file" == latest:* ]]; then
        local _bkdir="${lis_path}/backups/db"
        [[ "$intelis_sql_file" == latest:* ]] && _bkdir="${intelis_sql_file#latest:}"
        local _newest
        # Include encrypted (.gpg) backups — db-tools encrypts by default — so a
        # routine encrypted backup is picked up like any other.
        _newest="$(ls -1t "${_bkdir}"/intelis-*.sql.zst "${_bkdir}"/intelis-*.sql.gz "${_bkdir}"/intelis-*.sql.zst.gpg "${_bkdir}"/intelis-*.sql.gz.gpg 2>/dev/null | head -1)"
        [[ -z "$_newest" ]] && _newest="$(ls -1t "${_bkdir}"/*.sql.zst "${_bkdir}"/*.sql.gz "${_bkdir}"/*.sql "${_bkdir}"/*.sql.zst.gpg "${_bkdir}"/*.sql.gz.gpg "${_bkdir}"/*.sql.gpg 2>/dev/null | head -1)"
        if [[ -z "$_newest" ]]; then
            echo "No db-tools backup found in ${_bkdir}. Pass an explicit file with --db <path>."
            log_action "--db latest: no backup found in ${_bkdir}"
            exit 1
        fi
        intelis_sql_file="$_newest"
        print info "Using latest backup: ${intelis_sql_file}"
        log_action "Resolved --db latest to ${intelis_sql_file}"
    fi

    if [[ -n "$intelis_sql_file" ]]; then
        if [[ "$intelis_sql_file" != /* ]]; then
            intelis_sql_file="$(pwd)/$intelis_sql_file"
        fi
        if [[ ! -f "$intelis_sql_file" ]]; then
            echo "SQL file not found: $intelis_sql_file. Please check the path."
            log_action "SQL file not found: $intelis_sql_file. Please check the path."
            exit 1
        fi
        # Accept an optional .gpg suffix — encrypted backups are decrypted at import
        # time (see import_sql_dump_into_vlsm) before the normal restore runs.
        if [[ ! "$intelis_sql_file" =~ \.(sql|sql\.gz|sql\.zst)(\.gpg)?$ ]]; then
            echo "Unsupported SQL file format: $intelis_sql_file. Use .sql, .sql.gz, or .sql.zst (optionally .gpg)"
            log_action "Unsupported SQL file format: $intelis_sql_file"
            exit 1
        fi
    fi

    # --- 2. What this machine is ---
    # A menu, not free text. The old prompt read the first character of whatever
    # was typed and defaulted to LIS on anything unrecognised, so a mistyped
    # answer chose for you and said so only in the log.
    #
    # Asked before the addresses because it decides which address question gets
    # asked at all: a lab machine is asked where its STS is, a central server is
    # asked what its own web address should be, and neither is ever asked both.
    if ! $reuse_saved_answers; then
        ui_step_next "What this machine is"
        local _role=""
        ask_choice _role "lis" "What is this machine?" \
            "lis:Lab machine (LIS):Runs in a laboratory. Results are entered here and sent up to the STS." \
            "sts:Central server (STS):Receives data from the labs. Normally one per country."
        if [ "$_role" = "sts" ]; then
            is_lis=false
            is_sts=true
            log_action "Will install InteLIS alongside other apps (STS)"
        else
            is_lis=true
            is_sts=false
            log_action "Will install InteLIS as the default host (LIS)"
        fi
    else
        print info "Reusing saved installation type: $([ "$is_lis" = true ] && echo LIS || echo STS)"
    fi

    # --- 3. Remote STS URL (LIS only; validated with curl, no local setup needed) ---
    # Collected before anything that could be confused with it, so that a name
    # arriving later — from --server-name, or from a saved answers file — can be
    # checked against it rather than silently accepted.
    if [ -n "$intelis_sts_url_flag" ]; then
        remote_sts_url="${intelis_sts_url_flag%/}"
        print info "Using STS URL from --sts-url: ${remote_sts_url}"
        log_action "STS URL from --sts-url flag: ${remote_sts_url}"
    elif $reuse_saved_answers; then
        [ -n "${remote_sts_url:-}" ] && print info "Reusing saved Remote STS URL: ${remote_sts_url}"
    elif $is_lis; then
        remote_sts_url=""
        local max_sts_url_attempts=3
        local sts_url_attempts=0
        ui_step_next "Address of the STS"
        ui_note "The central server this lab sends its results to." \
            "Ask the national programme if you do not know it." \
            "Leave it blank to configure syncing later."
        while true; do
            ask_text remote_sts_url "" "Remote STS URL (leave blank to skip)" "" "https://sts.example.org"
            log_action "Remote STS URL entered: $remote_sts_url"
            if [ -z "$remote_sts_url" ]; then
                echo "No STS URL provided. Skipping validation."
                log_action "No STS URL provided. Skipping validation."
                break
            fi
            remote_sts_url="${remote_sts_url%/}"
            echo "Validating the provided STS URL..."
            local response_code
            if command -v curl >/dev/null 2>&1; then
                response_code=$(curl -s -o /dev/null -w "%{http_code}" "$remote_sts_url/api/version.php" || true)
            else
                # curl should be installed by prepare_system; fall back to wget
                # (always present) so a missing curl can't dead-end the install.
                response_code=$(wget -q -S -O /dev/null "$remote_sts_url/api/version.php" 2>&1 \
                    | awk '/^  HTTP\//{code=$2} END{print code}' || true)
            fi
            if [[ "$response_code" =~ ^[0-9]+$ ]] && [ "$response_code" -eq 200 ]; then
                print success "STS URL validation successful."
                log_action "STS URL validation successful."
                break
            fi
            sts_url_attempts=$((sts_url_attempts + 1))
            log_action "STS URL validation failed with response code $response_code."
            if [ "$sts_url_attempts" -ge "$max_sts_url_attempts" ]; then
                print warning "Failed to validate the provided STS URL ${max_sts_url_attempts} times (last HTTP response code: $response_code). Skipping STS configuration."
                log_action "Skipping STS configuration after ${max_sts_url_attempts} failed validation attempts."
                remote_sts_url=""
                break
            fi
            local remaining_sts_url_attempts=$((max_sts_url_attempts - sts_url_attempts))
            print error "Failed to validate the provided STS URL (HTTP response code: $response_code). Attempts remaining: $remaining_sts_url_attempts."
        done
    fi

    # --- 3b. Web address (STS only) ---
    #
    # A lab machine is never asked. It is reached from the bench by IP address
    # or by http://intelis/, it has no public name, and the question that used
    # to be put to it — "Enter domain name" — is jargon to the person doing the
    # install. Worse, an operator with the STS URL in hand answered it with that,
    # setup wrote "127.0.0.1 sts.example.org" into /etc/hosts, and the lab spent
    # the following weeks syncing to itself.
    #
    # A central server does need a name, because the labs have to reach it, so
    # it is asked there — where it cannot be confused with "the address of the
    # STS", because on an STS this machine IS the STS.
    #
    # The uncommon lab machine that really does have an address of its own is
    # served by --server-name, which skips the prompt entirely.
    if ! $reuse_saved_answers; then
        hostname="intelis"
        if [ -n "$intelis_server_name_flag" ]; then
            _validated_hostname=""
            if _validate_local_hostname "$intelis_server_name_flag"; then
                hostname="$_validated_hostname"
                print info "Using server name from --server-name: ${hostname}"
            else
                print error "Ignoring --server-name; falling back to ${hostname}"
            fi
        elif $is_sts; then
            ui_step_next "Web address of this server"
            ui_note "What the labs will type in their browser to reach this server." \
                "It must already point at this machine in DNS." \
                "" \
                "Press enter to skip if the labs will use this machine's IP address."
            _validated_hostname=""
            local _hostname_answer=""
            ask_text _hostname_answer "intelis" \
                "Web address of this server" \
                _validate_local_hostname \
                "e.g. sts.health.gov.zm"
            hostname="${_validated_hostname:-intelis}"
        fi
        if [ "$hostname" = "intelis" ]; then
            print info "This machine will be reached at http://intelis/ or by its IP address."
        else
            print info "This machine will be served as: http://${hostname}/"
        fi
    else
        print info "Reusing saved hostname: ${hostname}"
    fi
    log_action "Hostname: $hostname"

    # --- 4. MySQL root password (collect only; verify+persist after lamp-setup) ---
    mysql_password_needs_persisting=false
    if [ -f ~/.my.cnf ]; then
        mysql_root_password=$(awk -F= '/password/ {print $2}' ~/.my.cnf | xargs)
        echo "MySQL root password extracted from ~/.my.cnf"
    else
        ui_step_next "MySQL root password"
        ui_note "MySQL is not installed on this machine yet, so this password is" \
            "being CHOSEN now, not recalled. Write it down before continuing:" \
            "database backups and restores need it."
        ask_password mysql_root_password "New MySQL root password" "Confirm password"
        mysql_password_needs_persisting=true
    fi

    # --- 5. DB-collision strategy (skip if --db-strategy / env supplied or resuming) ---
    # Asked only when there is actually a database to decide about — which is
    # rare, and means someone is re-running setup after a failure. Putting it to
    # every operator on every clean install asked them to rule on a hypothetical,
    # and the three answers only make sense once you know something is there.
    #
    # Nothing is lost by staying quiet when the check says there is none. If one
    # turns up anyway, the import step already prompts at the point it finds it,
    # where the question is concrete.
    if ! $resume_setup && [[ -z "$DB_STRATEGY_FLAG" ]] && [ "$vlsm_db_state" != "no" ]; then
        ui_step_next "An existing database was found"
        if [ "$vlsm_db_state" = "unknown" ]; then
            ui_note "MySQL is installed but could not be queried from here, so" \
                "whether a 'vlsm' database exists is not known yet."
        else
            ui_note "This machine already has a 'vlsm' database with data in it." \
                "That usually means setup is being run again after a problem."
        fi
        ask_choice DB_STRATEGY_FLAG "rename" "What should setup do with it?" \
            "rename:Keep a copy, then start fresh:Renamed to vlsm_YYYYMMDD_HHMMSS. Nothing is lost. Safest." \
            "use:Leave it alone and use it:No import. Choose this when reinstalling over live data." \
            "drop:Delete it:The existing data is destroyed permanently."
        log_action "DB strategy chosen upfront: ${DB_STRATEGY_FLAG}"
    fi

    # --- 6. Maintenance scripts policy ---
    # The full file list isn't known until the codebase is extracted, so the
    # "pick individual scripts" mode is the only one that still has to prompt
    # at the end. "all" and "none" run unattended.
    if ! $reuse_saved_answers; then
        run_maintenance_scripts=false
        maintenance_scripts_mode="none"
        ui_step_next "Maintenance scripts"
        if ask_yes_no "Run the one-off maintenance scripts after setup finishes?" "no"; then
            run_maintenance_scripts=true
            ask_choice maintenance_scripts_mode "all" "Which ones?" \
                "all:Run all of them:Unattended. Setup finishes without asking again." \
                "pick:Let me choose at the end:Lists them once the code is in place."
            log_action "Maintenance scripts policy: ${maintenance_scripts_mode}"
        fi
    else
        print info "Reusing saved maintenance policy: ${maintenance_scripts_mode:-none}"
    fi

    # Last chance to catch a wrong answer, and the only screen on which the
    # answers appear next to each other — which is what makes a domain name that
    # is really the STS address look as wrong as it is. Nothing has been
    # installed at this point beyond the base packages prepare_system pulls in,
    # so declining is free.
    if ! $reuse_saved_answers && ui_interactive; then
        local _role_label
        _role_label=$($is_sts && echo "Central server (STS)" || echo "Lab machine (LIS)")
        ui_recap \
            "Role: ${_role_label}" \
            "Install path: ${lis_path}" \
            "Reached at: http://${hostname}/" \
            "Sends data to: ${remote_sts_url:-nowhere — syncing not configured}" \
            "Existing vlsm database: ${DB_STRATEGY_FLAG:-none found}" \
            "Maintenance scripts: ${maintenance_scripts_mode:-none}"
        if ! ask_yes_no "Is this correct?" "yes"; then
            print info "Stopped. Nothing has been installed."
            print info "Run the same command again to start over."
            log_action "Operator rejected the configuration recap; setup aborted before install."
            exit 0
        fi
    fi

    # Persist the answers so a re-run after a mid-setup failure can skip the
    # prompts. The MySQL password is deliberately NOT stored here (it lives in
    # ~/.my.cnf at 0600); this file holds only non-secret choices.
    mkdir -p "$(dirname "$answers_file")"
    cat >"$answers_file" <<EOF
lis_path='${lis_path}'
hostname='${hostname}'
is_lis=${is_lis}
is_sts=${is_sts}
DB_STRATEGY_FLAG='${DB_STRATEGY_FLAG}'
remote_sts_url='${remote_sts_url}'
run_maintenance_scripts=${run_maintenance_scripts}
maintenance_scripts_mode='${maintenance_scripts_mode}'
EOF
    chmod 600 "$answers_file"
    log_action "Saved setup answers to ${answers_file}"

    print success "All inputs collected. Setup will now run unattended."
    print info "Log file: ${log_file}"
    echo

    eval "$saved_trap"
}


# --- Parse CLI flags before any prompts so collect_user_inputs sees them ---
intelis_sql_file=""
DB_STRATEGY_FLAG=""
resume_setup=false
reuse_saved_answers=false
remote_sts_url=""
# OS-aware default: 8.4 on Ubuntu <=24.04, 8.5 on 26.04+ (which dropped 8.4).
# Overridable with --php. See default_php_version_for_os in shared-functions.sh.
PHP_VERSION="$(default_php_version_for_os)"

# Decryption inputs for encrypted (.gpg) backups. Both optional and never
# persisted to the saved-answers file. --encryption-password is the full
# passphrase (e.g. an offline recovery code); --recovery-token is a one-time
# token the new machine exchanges with the STS for the key.
intelis_enc_password=""
intelis_recovery_token=""

# Optional STS URL for non-interactive (re)installs and migrations. When set it is
# used verbatim and the STS-URL prompt is skipped. Needed by --recovery-token to
# reach the key-release endpoint without an operator at the keyboard.
intelis_sts_url_flag=""
intelis_server_name_flag=""

# How many timestamped code/DB backups to retain when re-running setup.
BACKUP_KEEP="${BACKUP_KEEP:-3}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --database=*|--db=*)
        intelis_sql_file="${1#*=}"
        shift
        ;;
        --database|--db)
        intelis_sql_file="$2"
        shift 2
        ;;
        --db-strategy=*)
        DB_STRATEGY_FLAG="${1#*=}"
        shift
        ;;
        --db-strategy)
        DB_STRATEGY_FLAG="$2"
        shift 2
        ;;
        --php=*)
        PHP_VERSION="${1#*=}"
        shift
        ;;
        --php)
        PHP_VERSION="$2"
        shift 2
        ;;
        --encryption-password=*)
        intelis_enc_password="${1#*=}"
        shift
        ;;
        --encryption-password)
        intelis_enc_password="$2"
        shift 2
        ;;
        --recovery-token=*)
        intelis_recovery_token="${1#*=}"
        shift
        ;;
        --recovery-token)
        intelis_recovery_token="$2"
        shift 2
        ;;
        --server-name=*)
        intelis_server_name_flag="${1#*=}"
        shift
        ;;
        --server-name)
        intelis_server_name_flag="$2"
        shift 2
        ;;
        --sts-url=*)
        intelis_sts_url_flag="${1#*=}"
        shift
        ;;
        --sts-url)
        intelis_sts_url_flag="$2"
        shift 2
        ;;
        --resume)
        resume_setup=true
        shift
        ;;
        *)
        # unrecognized -> discard
        shift
        ;;
    esac
done

# Validate PHP_VERSION format (e.g. 8.4, 8.5) — must be major.minor digits.
if [[ ! "$PHP_VERSION" =~ ^[0-9]+\.[0-9]+$ ]]; then
    echo "Invalid --php value: '$PHP_VERSION'. Expected format like 8.4 or 8.5."
    exit 1
fi

# Prompts render in plain text everywhere; this only makes them nicer when it
# works. It is deliberately best-effort and silent about failing — an install
# must never be blocked by a cosmetic dependency.
ensure_gum || true

collect_user_inputs

# Download and install lamp-setup script
download_file  "lamp-setup.sh" "https://raw.githubusercontent.com/deforay/utility-scripts/master/lamp/lamp-setup.sh" "Downloading lamp-setup.sh..." || {
    print error "LAMP Setup file download failed - cannot continue with update"
    log_action "LAMP Setup file download failed - update aborted"
    exit 1
}

chmod u+x ./lamp-setup.sh

# Hand lamp-setup the MySQL root password we already collected so it doesn't
# prompt a second time. lamp-setup falls back to its own prompt if this is empty
# or an older lamp-setup that ignores the variable.
MYSQL_ROOT_PASSWORD="${mysql_root_password}" ./lamp-setup.sh $PHP_VERSION

rm -f ./lamp-setup.sh

# Configure PHP INI settings (session timeout, opcache, security, etc.)
configure_php_ini "${PHP_VERSION}"

echo "Calculating checksums of current composer files..."
CURRENT_COMPOSER_JSON_CHECKSUM="none"
CURRENT_COMPOSER_LOCK_CHECKSUM="none"

if [ -f "${lis_path}/composer.json" ]; then
    CURRENT_COMPOSER_JSON_CHECKSUM=$(md5sum "${lis_path}/composer.json" | awk '{print $1}')
    echo "Current composer.json checksum: ${CURRENT_COMPOSER_JSON_CHECKSUM}"
fi

if [ -f "${lis_path}/composer.lock" ]; then
    CURRENT_COMPOSER_LOCK_CHECKSUM=$(md5sum "${lis_path}/composer.lock" | awk '{print $1}')
    echo "Current composer.lock checksum: ${CURRENT_COMPOSER_LOCK_CHECKSUM}"
fi

# LIS Setup
print header "Downloading LIS"

# Acquire the master tree via the shared helper: a persistent shallow git mirror
# (so a re-run delta-fetches only changed objects, the mirror primes the first
# `intelis upgrade`, and we stamp the exact commit SHA into VERSION.txt), with a
# shallow-clone and codeload-tarball fallback. Progress goes to the terminal.
temp_dir=$(mktemp -d)
if ! fetch_master_tree "${temp_dir}/intelis-master"; then
    print error "Failed to acquire the LIS source tree - cannot continue with setup."
    log_action "fetch_master_tree failed - setup aborted"
    exit 1
fi

log_action "LIS downloaded."

# Create installation directory or backup existing installation
if [ ! -d "${lis_path}" ]; then
    mkdir -p "${lis_path}"
    log_action "Created fresh installation directory: ${lis_path}"
elif [ -n "$(ls -A ${lis_path} 2>/dev/null)" ]; then
    # Only backup if directory exists AND has content
    print info "Existing installation detected. Creating selective backup..."
    backup_dir="${lis_path}-$(date +%Y%m%d-%H%M%S)"
    rsync -a \
        --exclude 'vendor/' \
        --exclude 'var/cache/' \
        --exclude 'var/logs/' \
        --exclude 'var/audit-trail/' \
        --exclude 'public/temporary/' \
        --exclude 'public/uploads/' \
        "${lis_path}/" "${backup_dir}/"
    log_action "Selective backup created: ${backup_dir}"

    # Keep only the most recent code backups so repeated re-runs can't fill the disk.
    ls -dt "${lis_path}"-[0-9]* 2>/dev/null | tail -n +$((BACKUP_KEEP + 1)) | while read -r _old_backup; do
        print info "Pruning old code backup: ${_old_backup}"
        rm -rf "$_old_backup"
        log_action "Pruned old code backup: ${_old_backup}"
    done
fi

# Copy the unzipped content to the LIS PATH, overwriting any existing files
# cp -R "$temp_dir/intelis-master/"* "${lis_path}"
rsync -a --info=progress2 "$temp_dir/intelis-master/" "$lis_path/"

# Remove the staged tree (the temp dir itself is swept by the EXIT cleanup trap,
# along with any tarball-fallback master.tar.gz that lived inside it).
rm -rf "$temp_dir/intelis-master/"

log_action "LIS copied to ${lis_path}."

# Set proper permissions
set_permissions "${lis_path}" "quick" "sync"
# Batch the ownership pass (one chown call per ~argmax files) instead of forking
# chown once per file — orders of magnitude faster on a full tree.
find "${lis_path}" -exec chown www-data:www-data {} + 2>/dev/null || true

# Run Composer Install as www-data
print header "Running composer operations"
cd "${lis_path}"

# Ensure composer files are writable by www-data before running composer commands
chown www-data:www-data "${lis_path}/composer.json" "${lis_path}/composer.lock" 2>/dev/null || true

# Make sure the CLI PHP Composer uses has phar (and friends) before we call it —
# a phar blacklist here otherwise aborts Composer with a cryptic error.
if ! ensure_php_cli_extensions "${PHP_VERSION}"; then
    print error "Aborting: required PHP CLI extensions are unavailable for Composer."
    exit 1
fi

# Configure composer timeout regardless of installation path
wwwdata_composer config process-timeout 30000
wwwdata_composer clear-cache

echo "Checking if composer dependencies need updating..."
NEED_FULL_INSTALL=false

# Check if the vendor directory exists
if [ ! -d "${lis_path}/vendor" ]; then
    echo "Vendor directory doesn't exist. Full installation needed."
    NEED_FULL_INSTALL=true
else
    # Calculate new checksums
    NEW_COMPOSER_JSON_CHECKSUM="none"
    NEW_COMPOSER_LOCK_CHECKSUM="none"

    if [ -f "${lis_path}/composer.json" ]; then
        NEW_COMPOSER_JSON_CHECKSUM=$(md5sum "${lis_path}/composer.json" 2>/dev/null | awk '{print $1}')
        echo "New composer.json checksum: ${NEW_COMPOSER_JSON_CHECKSUM}"
    else
        echo "Warning: composer.json is missing after extraction. Full installation needed."
        NEED_FULL_INSTALL=true
    fi

    if [ -f "${lis_path}/composer.lock" ] && [ "$NEED_FULL_INSTALL" = false ]; then
        NEW_COMPOSER_LOCK_CHECKSUM=$(md5sum "${lis_path}/composer.lock" 2>/dev/null | awk '{print $1}')
        echo "New composer.lock checksum: ${NEW_COMPOSER_LOCK_CHECKSUM}"
    else
        echo "Warning: composer.lock is missing after extraction. Full installation needed."
        NEED_FULL_INSTALL=true
    fi

    # Only do checksum comparison if we haven't already determined we need a full install
    if [ "$NEED_FULL_INSTALL" = false ]; then
        # Compare checksums - only if both files existed before and after
        if [ "$CURRENT_COMPOSER_JSON_CHECKSUM" = "none" ] || [ "$CURRENT_COMPOSER_LOCK_CHECKSUM" = "none" ] ||
            [ "$NEW_COMPOSER_JSON_CHECKSUM" = "none" ] || [ "$NEW_COMPOSER_LOCK_CHECKSUM" = "none" ] ||
            [ "$CURRENT_COMPOSER_JSON_CHECKSUM" != "$NEW_COMPOSER_JSON_CHECKSUM" ] ||
            [ "$CURRENT_COMPOSER_LOCK_CHECKSUM" != "$NEW_COMPOSER_LOCK_CHECKSUM" ]; then
            echo "Composer files have changed or were missing. Full installation needed."
            NEED_FULL_INSTALL=true
        else
            echo "Composer files haven't changed. Skipping full installation."
            NEED_FULL_INSTALL=false
        fi
    fi
fi

# Download vendor.tar.gz if needed
if [ "$NEED_FULL_INSTALL" = true ]; then
    print info "Dependency update needed. Checking for vendor packages..."

    # Check if the vendor package exists
    if curl --output /dev/null --silent --head --fail "https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz"; then
        # Download the vendor archive
        download_file "vendor.tar.gz" "https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz" "Downloading vendor packages..."
        if [ $? -ne 0 ]; then
            print error "Failed to download vendor.tar.gz"
            exit 1
        fi

        # Download the checksum file
        download_file "vendor.tar.gz.md5" "https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz.md5" "Downloading checksum file..."
        if [ $? -ne 0 ]; then
            print error "Failed to download vendor.tar.gz.md5"
            exit 1
        fi

        print info "Verifying checksum..."
        if ! md5sum -c vendor.tar.gz.md5; then
            print error "Checksum verification failed"
            exit 1
        fi
        print success "Checksum verification passed"

        print info "Extracting files from vendor.tar.gz..."
        tar -xzf vendor.tar.gz -C "${lis_path}" &
        vendor_tar_pid=$!
        spinner "${vendor_tar_pid}" "Extracting vendor files..."
        wait ${vendor_tar_pid}
        vendor_tar_status=$?

        if [ $vendor_tar_status -ne 0 ]; then
            print error "Failed to extract vendor.tar.gz"
            exit 1
        fi

        # Clean up downloaded files
        rm vendor.tar.gz
        rm vendor.tar.gz.md5

        # Fix permissions on the vendor directory
        print info "Setting permissions on vendor directory..."
        find "${lis_path}/vendor" -exec chown www-data:www-data {} + 2>/dev/null || true
        chmod -R 755 "${lis_path}/vendor" 2>/dev/null || true

        print success "Vendor files successfully installed"

        # Update the composer.lock file to match the current state
        print info "Finalizing composer installation..."
        wwwdata_composer install --no-scripts --no-autoloader --prefer-dist --no-dev
    else
        print warning "Vendor package not found in GitHub releases. Proceeding with regular composer install."

        # Perform full install if vendor.tar.gz isn't available. --no-scripts:
        # the only composer event script is install-hooks, a git-clone-only
        # convenience that has no business printing in prod output.
        print info "Running full composer install (this may take a while)..."
        wwwdata_composer install --no-scripts --prefer-dist --no-dev
    fi
else
    print info "Dependencies are up to date. Skipping vendor download."
fi

# Always generate the optimized autoloader, regardless of install path
wwwdata_composer dump-autoload -o

log_action "Composer operations completed."

# Function to configure Apache Virtual Host
configure_vhost() {
    local vhost_file=$1
    local document_root="${lis_path}/public"
    local directory_block="<Directory ${lis_path}/public>\n\
        AddDefaultCharset UTF-8\n\
        Options -Indexes -MultiViews +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>"

    # Replace the DocumentRoot line
    sed -i "s|DocumentRoot .*|DocumentRoot ${document_root}|" "$vhost_file"

    # Check if any Directory block exists
    if grep -q "<Directory" "$vhost_file"; then
        # Replace existing Directory block
        sed -i "/<Directory/,/<\/Directory>/c\\$directory_block" "$vhost_file"
    else
        # Insert Directory block after DocumentRoot line
        sed -i "/DocumentRoot/a\\$directory_block" "$vhost_file"
    fi
}

# Hostname was collected upfront in collect_user_inputs.
#
# These entries let the machine reach itself by name. They go through
# hosts_file_add_local, which refuses any name that already resolves to another
# server — because "127.0.0.1 sts.example.org" written here is the single line
# that silently cut a lab off from its STS, and refusing to write it is the last
# defence after the prompts that used to invite it.
#
# The old guard grepped unanchored too, so a name inside a comment, or one that
# was a substring of another entry, read as already present.
for _hosts_name in "${hostname}" intelis vlsm; do
    case " ${_hosts_written:-} " in
        *" ${_hosts_name} "*) continue ;;
    esac
    _hosts_written="${_hosts_written:-} ${_hosts_name}"
    hosts_file_add_local "$_hosts_name" || true
done
unset _hosts_name _hosts_written

# Installation type (is_lis / is_sts) was collected upfront. Write the vhost.
if $is_lis; then
    echo "Installing InteLIS as the default host..."
    log_action "Installing InteLIS as the default host..."
    apache_vhost_file="/etc/apache2/sites-available/000-default.conf"
    # Only snapshot the pristine original once; a re-run must not overwrite the
    # backup with an already-modified vhost.
    [ -f "${apache_vhost_file}.bak" ] || cp "$apache_vhost_file" "${apache_vhost_file}.bak"
    configure_vhost "$apache_vhost_file"
else
    echo "Installing InteLIS alongside other apps..."
    log_action "Installing InteLIS alongside other apps..."
    vhost_file="/etc/apache2/sites-available/${hostname}.conf"
    echo "<VirtualHost *:80>
    ServerName ${hostname}
    ServerAlias intelis
    ServerAlias vlsm
    DocumentRoot ${lis_path}/public
    <Directory ${lis_path}/public>
        AddDefaultCharset UTF-8
        Options -Indexes -MultiViews +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>" >"$vhost_file"
    a2ensite "${hostname}.conf"
fi

# Restart Apache to apply changes
restart_service apache || {
    print error "Failed to restart Apache. Please check the configuration."
    log_action "Failed to restart Apache. Please check the configuration."
    exit 1
}

# Cron job setup
setup_intelis_cron "${lis_path}"


# Update LIS config.production.php with database credentials
config_file="${lis_path}/configs/config.production.php"
source_file="${lis_path}/configs/config.production.dist.php"

if [ ! -e "${config_file}" ]; then
    print info  "Renaming config.production.dist.php to config.production.php..."
    log_action "Renaming config.production.dist.php to config.production.php..."
    mv "${source_file}" "${config_file}"
else
    echo "File config.production.php already exists. Skipping renaming."
    log_action "File config.production.php already exists. Skipping renaming."
fi

# MySQL root password was collected upfront. If it was prompted (not read
# from a pre-existing ~/.my.cnf), verify it against the now-running MySQL
# instance and persist it to ~/.my.cnf for future logins.
if [ "${mysql_password_needs_persisting:-false}" = true ]; then
    echo "Verifying MySQL root password..."
    if ! mysqladmin ping -u root -p"$mysql_root_password" &>/dev/null; then
        print error "Unable to verify the password. Please check and try again."
        exit 1
    fi

    echo "Storing MySQL password for secure login..."
    cat <<EOF >~/.my.cnf
[client]
user=root
password=${mysql_root_password}
host=localhost
EOF
    chmod 600 ~/.my.cnf
    echo "MySQL credentials saved in secure file."
else
    echo "MySQL root password already configured via ~/.my.cnf."
fi

# Escape password for sed replacement and PHP single-quoted strings
escaped_mysql_root_password=$(escape_php_string_for_sed "${mysql_root_password}")

# Use sed to update database configurations, using | as a delimiter instead of /
sed -i "s|\$systemConfig\['database'\]\['host'\]\s*=.*|\$systemConfig['database']['host'] = 'localhost';|" "${config_file}"
sed -i "s|\$systemConfig\['database'\]\['username'\]\s*=.*|\$systemConfig['database']['username'] = 'root';|" "${config_file}"
sed -i "s|\$systemConfig\['database'\]\['password'\]\s*=.*|\$systemConfig['database']['password'] = '$escaped_mysql_root_password';|" "${config_file}"

sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['host'\]\s*=.*|\$systemConfig['interfacing']['database']['host'] = 'localhost';|" "${config_file}"
sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['username'\]\s*=.*|\$systemConfig['interfacing']['database']['username'] = 'root';|" "${config_file}"
sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['password'\]\s*=.*|\$systemConfig['interfacing']['database']['password'] = '$escaped_mysql_root_password';|" "${config_file}"

# Handle database setup and SQL file import
if $resume_setup; then
    print info "Skipping database setup/import in resume mode."
    log_action "Database setup/import skipped due to resume mode."
elif [ -f "${db_setup_checkpoint_file}" ] && \
     [ "$(mysql -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='vlsm';" 2>/dev/null || echo 0)" -gt 0 ]; then
    # A prior run completed the import (checkpoint is written only on success and
    # cleared at the start of every import). Re-running must NOT re-reset or
    # re-import over an already-populated database.
    print info "Database already imported in a previous run (checkpoint present); skipping DB setup/import."
    print info "Delete ${db_setup_checkpoint_file} to force a fresh import."
    log_action "Auto-skipped DB import: checkpoint present at ${db_setup_checkpoint_file}."
elif [[ -n "$intelis_sql_file" && -f "$intelis_sql_file" ]]; then
    handle_database_setup_and_import "$intelis_sql_file"
elif [[ -n "$intelis_sql_file" ]]; then
    print error "SQL file not found: $intelis_sql_file. Please check the path."
    exit 1
else
    handle_database_setup_and_import # Default to init.sql
fi


mysql_cnf="/etc/mysql/mysql.conf.d/mysqld.cnf"
backup_timestamp=$(date +%Y%m%d%H%M%S)

# Detect server flavor + version so we don't append options that the running
# server has dropped. MySQL 8.4 removed default_authentication_plugin and
# disabled mysql_native_password by default; MariaDB never had that option.
mysql_version_string="$(mysql --version 2>/dev/null || true)"
mysql_is_mariadb=false
if [[ "$mysql_version_string" == *MariaDB* ]]; then
    mysql_is_mariadb=true
    mysql_major_minor="$(echo "$mysql_version_string" | grep -oE 'Distrib [0-9]+\.[0-9]+' | awk '{print $2}')"
else
    mysql_major_minor="$(echo "$mysql_version_string" | grep -oE 'Ver [0-9]+\.[0-9]+' | awk '{print $2}')"
fi

# Returns 0 (true) if $1 is strictly less than $2 (semver-ish).
version_lt() {
    [[ "$1" != "$2" ]] && [[ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | head -1)" == "$1" ]]
}

# --- define what we want ---
declare -A mysql_settings=(
    ["sql_mode"]=""
    ["innodb_strict_mode"]="0"
    ["character-set-server"]="utf8mb4"
    ["collation-server"]="utf8mb4_unicode_ci"
    ["max_connect_errors"]="10000"
)

# Only set default_authentication_plugin on MySQL < 8.4. Skip on MySQL 8.4+
# (removed) and on MariaDB (never existed).
if ! $mysql_is_mariadb && [[ -n "$mysql_major_minor" ]] && version_lt "$mysql_major_minor" "8.4"; then
    mysql_settings["default_authentication_plugin"]="mysql_native_password"
fi

# Settings the script may have written on an older MySQL that the running
# server no longer accepts. We comment these out before restarting so an
# upgrade-in-place (e.g. 8.0 -> 8.4) doesn't leave a broken cnf behind.
declare -a mysql_obsolete_keys=()
if $mysql_is_mariadb || ([[ -n "$mysql_major_minor" ]] && ! version_lt "$mysql_major_minor" "8.4"); then
    mysql_obsolete_keys+=("default_authentication_plugin")
fi

# --- work out what has to change, without touching the file yet ---
#
# Only the [mysqld] section counts. A copy of the setting in another section is
# not the server's value, and reading it as one is how a line stranded under
# [client] would convince this that the work is already done.
mysql_settings_to_write=()
for setting in "${!mysql_settings[@]}"; do
    if current_value="$(mysql_cnf_get_option "$mysql_cnf" mysqld "$setting")"; then
        [ "$current_value" = "${mysql_settings[$setting]}" ] && continue
    fi
    mysql_settings_to_write+=("$setting")
done

mysql_obsolete_present=()
for obsolete in ${mysql_obsolete_keys[@]+"${mysql_obsolete_keys[@]}"}; do
    if grep -qE "^[[:space:]]*${obsolete}[[:space:]]*=" "$mysql_cnf"; then
        mysql_obsolete_present+=("$obsolete")
    fi
done

if [ ${#mysql_settings_to_write[@]} -gt 0 ] || [ ${#mysql_obsolete_present[@]} -gt 0 ]; then
    print info "Changes needed. Backing up and updating MySQL config..."
    print info "Detected MySQL flavor: $([ "$mysql_is_mariadb" = true ] && echo MariaDB || echo MySQL) ${mysql_major_minor:-unknown}"
    cp "$mysql_cnf" "${mysql_cnf}.bak.${backup_timestamp}"

    # Comment out any obsolete-on-this-version keys first.
    for obsolete in ${mysql_obsolete_present[@]+"${mysql_obsolete_present[@]}"}; do
        print info "Disabling obsolete option for this server: $obsolete"
        mysql_cnf_comment_option "$mysql_cnf" "$obsolete"
    done

    # Written into the [mysqld] section rather than appended to the end of the
    # file: appending is only correct while the last heading happens to be
    # [mysqld], and where it is not, the settings land under [client], where the
    # server ignores them and every client refuses to start.
    if [ ${#mysql_settings_to_write[@]} -gt 0 ]; then
        mysql_new_lines="$(mktemp)"
        for setting in "${mysql_settings_to_write[@]}"; do
            mysql_cnf_comment_option "$mysql_cnf" "$setting"
            printf '%s = %s\n' "$setting" "${mysql_settings[$setting]}" >>"$mysql_new_lines"
        done

        if ! mysql_cnf_insert_mysqld_options "$mysql_cnf" "$mysql_new_lines"; then
            print error "Failed to write MySQL settings. Restoring backup and exiting..."
            cp "${mysql_cnf}.bak.${backup_timestamp}" "$mysql_cnf"
            rm -f "$mysql_new_lines"
            exit 1
        fi
        rm -f "$mysql_new_lines"
    fi

    print info "Restarting MySQL service to apply changes..."
    restart_service mysql || {
        print error "Failed to restart MySQL. Restoring backup and exiting..."
        mv "${mysql_cnf}.bak.${backup_timestamp}" "$mysql_cnf"
        restart_service mysql
        exit 1
    }

    print success "MySQL configuration updated successfully."

else
    print success "MySQL configuration already correct. No changes needed."
fi

# --- Always clean up old .bak files ---
find "$(dirname "$mysql_cnf")" -maxdepth 1 -type f -name "$(basename "$mysql_cnf").bak.*" -exec rm -f {} \;
print info "Removed all MySQL backup files matching *.bak.*"


print info "Applying SET PERSIST sql_mode='' to override MySQL defaults..."

# Determine which password to use
if [ -n "$mysql_root_password" ]; then
    mysql_pw="$mysql_root_password"
    print info "Using user-provided MySQL root password"
elif [ -f "${lis_path}/configs/config.production.php" ]; then
    mysql_pw=$(extract_mysql_password_from_config "${lis_path}/configs/config.production.php")
    print info "Extracted MySQL root password from config.production.php"
else
    print error "MySQL root password not provided and config.production.php not found."
    exit 1
fi

if [ -z "$mysql_pw" ]; then
    print warning "Password in config file is empty or missing. Prompting for manual entry..."
    read -sp "Please enter MySQL root password: " mysql_pw
    echo
fi

if persist_result=$(MYSQL_PWD="${mysql_pw}" mysql -u root -e "SET PERSIST sql_mode = '';" 2>&1); then
    persist_status=0
else
    persist_status=$?
fi

if [ $persist_status -eq 0 ]; then
    print success "Successfully persisted sql_mode=''"
    log_action "Applied SET PERSIST sql_mode = '';"
else
    print warning "SET PERSIST failed: $persist_result"
    log_action "SET PERSIST sql_mode failed: $persist_result"
fi

chmod 644 "$mysql_cnf"
restart_service mysql

# Remote STS URL was collected (and validated) upfront for LIS nodes.
if $is_lis && [ -n "$remote_sts_url" ]; then
    desired_sts_url="\$systemConfig['remoteURL'] = '$remote_sts_url';"
    config_file="${lis_path}/configs/config.production.php"

    if ! grep -qF "$desired_sts_url" "${config_file}"; then
        sed -i "s|\$systemConfig\['remoteURL'\]\s*=\s*'.*';|$desired_sts_url|" "${config_file}"
        print info "Remote STS URL updated in the configuration file."
    else
        print info "Remote STS URL is already set as desired in the configuration file."
    fi
fi

if grep -q "\['cache_di'\] => false" "${config_file}"; then
    sed -i "s|\('cache_di' => \)false,|\1true,|" "${config_file}"
fi

# Set ACLs
set_permissions "${lis_path}" "quick"

# Make intelis command globally accessible
print info "Setting up intelis command..."

TARGET="/usr/local/bin/intelis"
SOURCE="${lis_path}/intelis"

if [ -f "${SOURCE}" ]; then
    # Remove any existing version
    rm -f "${TARGET}" /usr/bin/intelis 2>/dev/null || true

    # Create symlink and make source executable
    chmod 755 "${SOURCE}"
    ln -sf "${SOURCE}" "${TARGET}"

    print success "intelis command installed globally at ${TARGET}"
    log_action "intelis command installed at ${TARGET}"
else
    print warning "intelis script not found at ${SOURCE}, skipping setup"
    log_action "intelis setup skipped — source missing"
fi

# Ensure www-data owns the var/ (cache) tree before anything runs as www-data.
# The rsync above excludes var/cache/, so on a re-run an existing cache is
# preserved and may still hold root-owned files from an earlier run; the
# www-data `composer post-install` (purge-cache) then fails to clear them with
# "[ERROR] Could not clear the application cache." The whole-tree chown only
# runs at the very end of setup, which is too late for this step.
mkdir -p "${lis_path}/var" 2>/dev/null || true
chown -R www-data:www-data "${lis_path}/var" 2>/dev/null || true

# Run as www-data (not root): db-tools bootstraps the app and warms var/cache,
# so running it as root leaves root-owned cache files that the subsequent
# www-data `composer post-install` (purge-cache) can't clear.
# Probe the main profile only. --all also walks the optional interfacing
# profile, and with the ERR trap armed an absent secondary database would abort
# the whole setup. On failure, explain it instead of dying on an exit code.
if ! db_probe_out=$(sudo -u www-data php "${lis_path}/vendor/bin/db-tools" db:test 2>&1); then
    print error "Database connectivity probe failed. Its output was:"
    printf '%s\n' "$db_probe_out" | tail -n 20
    if ! mysql_is_up; then
        mysql_diagnostics || true
    fi
    print error "Fix the database connection and re-run the setup."
    exit 1
fi
printf '%s\n' "$db_probe_out"

print header "Running database migrations and other post-install tasks"
cd "${lis_path}"
# Audit Trail v2 triggers are generated inside `composer post-install`
# (right after the migrate step), so no separate invocation is needed here.
# INTELIS_NONINTERACTIVE tells post-install hooks (e.g. sts-setup) not to prompt:
# the STS URL was already collected upfront, so they use the configured value
# instead of re-asking. Set it later from Admin or via `composer sts-setup`.
sudo -u www-data env INTELIS_NONINTERACTIVE=1 composer post-install

# Maintenance scripts policy was decided upfront in collect_user_inputs.
if [ "${run_maintenance_scripts:-false}" = true ]; then
    files=("${lis_path}/maintenance/"*.php)

    if [ "$maintenance_scripts_mode" = "all" ]; then
        echo "Running all maintenance scripts..."
        for file in "${files[@]}"; do
            echo "Running $file..."
            sudo -u www-data php "$file"
        done
    elif [ "$maintenance_scripts_mode" = "pick" ]; then
        echo "Available maintenance scripts:"
        for i in "${!files[@]}"; do
            filename=$(basename "${files[$i]}")
            echo "$((i + 1))) $filename"
        done

        echo "Enter the numbers of the scripts you want to run separated by commas (e.g., 1,2,4) or type 'all' to run them all."
        read -r files_to_run

        if [[ "$files_to_run" == "all" ]]; then
            for file in "${files[@]}"; do
                echo "Running $file..."
                sudo -u www-data php "$file"
            done
        else
            IFS=',' read -ra ADDR <<<"$files_to_run"
            for i in "${ADDR[@]}"; do
                i=$(echo "$i" | xargs)
                file_index=$((i - 1))
                if [[ $file_index -ge 0 ]] && [[ $file_index -lt ${#files[@]} ]]; then
                    file="${files[$file_index]}"
                    echo "Running $file..."
                    sudo -u www-data php "$file"
                else
                    echo "Invalid selection: $i. Please select a number between 1 and ${#files[@]}. Skipping."
                    log_action "Invalid selection: $i. Please select a number between 1 and ${#files[@]}. Skipping."
                fi
            done
        fi
    fi
fi




if [ -f "${lis_path}/var/cache/CompiledContainer.php" ]; then
    rm "${lis_path}/var/cache/CompiledContainer.php"
fi

# The update command, installed here rather than left for the operator to fetch.
# Until now nothing put it on a fresh machine: the first update of a new
# installation began with a two-command wget-and-chmod copied out of a document,
# and a lab that mistyped it had no way to update at all.
#
# Checked before installing, like everything else here: setup is idempotent and
# re-running it on a working machine must not disturb what is already in place.
# Staleness is not a reason to overwrite it either — `intelis update` refreshes
# this copy from master every time it runs.
if [ ! -x /usr/local/bin/intelis-update ]; then
    download_file "/usr/local/bin/intelis-update" https://raw.githubusercontent.com/deforay/intelis/master/scripts/upgrade.sh
    chmod +x /usr/local/bin/intelis-update
    print success "Update command installed — run 'intelis update' to update this machine"
fi

# Set proper permissions
download_file "/usr/local/bin/intelis-refresh" https://raw.githubusercontent.com/deforay/intelis/master/scripts/refresh.sh
chmod +x /usr/local/bin/intelis-refresh
# INTELIS_SHARED_FN_FRESH: this run already fetched the shared functions, so
# intelis-refresh has no reason to fetch them again.
#
# chown_app_tree rather than a bare `find -exec chown`: the flat walk covered
# .git and node_modules as well, which nothing serving the application ever
# reads, and on a re-run over an existing install it covered backups and
# var/audit-trail too — the trees set_permissions prunes precisely because they
# are the largest thing there.
(print success "Setting final permissions in the background..." &&
    INTELIS_SHARED_FN_FRESH=1 intelis-refresh -p "${lis_path}" -m full >/dev/null 2>&1 &&
    chown_app_tree "${lis_path}" || true) &
permissions_pid=$!

restart_service apache

# Wait for the permission pass rather than disowning it. The preflight below
# reports on what www-data can write, and a half-chowned tree would have it
# reporting failures that fix themselves seconds later — which is worse than not
# reporting at all, because the installer learns to ignore it. Nothing follows
# this point, so the wait costs the install nothing.
wait_with_progress "Setting final permissions" "${permissions_pid}"

# ---------------------------------------------------------------------------
# Install preflight — advisory, never fatal.
#
# Everything above installs and configures; this is the only step that stands
# back and asks whether the result actually works. A fresh install is where the
# answer is most often "not quite": a MySQL user without rights on the new
# database, an Apache php.ini the distro shipped its own defaults into, a
# directory the app cannot write to.
#
# `|| true` because setup has genuinely completed by now — a finding here is
# something to fix on this machine, not a reason to report the install as failed.
# ---------------------------------------------------------------------------
if [ -f "${lis_path}/bin/preflight.php" ]; then
    print header "Install Check"
    sudo -u www-data php "${lis_path}/bin/preflight.php" || true
    print info "Re-run any time: intelis check"
fi

print success "Setup complete. Proceed to LIS setup."
log_action "Setup complete. Proceed to LIS setup."
