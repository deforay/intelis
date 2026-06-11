#!/bin/bash

# To use this script:
# cd ~;
# wget -O intelis-setup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh
# sudo chmod u+x intelis-setup.sh;
# sudo ./intelis-setup.sh;
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
#       Defaults to 8.4. Equivalent long form: --php <version>
#
#   --resume
#       Skip the database setup/import step (only allowed after a previous
#       successful import; requires the setup-db-complete.checkpoint file).
#
# Examples:
#   sudo ./intelis-setup.sh --database=/root/backup.sql.gz
#   sudo ./intelis-setup.sh --db ./dump.sql --db-strategy=drop
#   sudo ./intelis-setup.sh --php=8.5 --database=/root/backup.sql.gz
#   sudo INTELIS_DB_STRATEGY=use ./intelis-setup.sh

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print error "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo"
    exit 1
fi

# Download and update shared-functions.sh
SHARED_FN_PATH="/usr/local/lib/intelis/shared-functions.sh"
SHARED_FN_URL="https://raw.githubusercontent.com/deforay/intelis/master/scripts/shared-functions.sh"

mkdir -p "$(dirname "$SHARED_FN_PATH")"

if wget -q -O "$SHARED_FN_PATH" "$SHARED_FN_URL"; then
    chmod +x "$SHARED_FN_PATH"
    echo "Downloaded shared-functions.sh."
else
    echo "Failed to download shared-functions.sh."
    if [ ! -f "$SHARED_FN_PATH" ]; then
        echo "shared-functions.sh missing. Cannot proceed."
        exit 1
    fi
fi

# Source the shared functions
source "$SHARED_FN_PATH"

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
    {
        echo
        echo "Existing InteLIS database detected. Choose what to do:"
        echo "  1) DROP   – delete current database and create a fresh one"
        echo "  2) RENAME – back up to vlsm_YYYYMMDD_HHMMSS and create fresh (default)"
        echo "  3) USE    – keep existing 'vlsm' as-is and skip import"
    } >"$tty"

    read -r -p "Enter choice [1=DROP, 2=RENAME(default), 3=USE]: " choice <"$tty"
    case "${choice:-2}" in
        1) echo "drop"   ;;
        2) echo "rename" ;;
        3) echo "use"    ;;
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

    # --- 1. LIS installation path ---
    if ! $reuse_saved_answers; then
        echo "Enter the LIS installation path [press enter to select /var/www/intelis]: "
        read -t 60 lis_path
        if [ $? -ne 0 ] || [ -z "$lis_path" ]; then
            lis_path="/var/www/intelis"
            echo "Using default path: $lis_path"
        else
            echo "LIS installation path is set to ${lis_path}."
        fi
    else
        print info "Reusing saved LIS path: ${lis_path}"
    fi
    log_action "LIS installation path is set to ${lis_path}."
    db_setup_checkpoint_file="${lis_path}/var/run/setup-db-complete.checkpoint"

    # --- 2. Resume-mode preflight (needs the path) ---
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

    # --- 3. Existing-install confirmation (skipped when reusing saved answers) ---
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

    # --- 4. SQL dump file validation (path came from --database/--db) ---
    if [[ -n "$intelis_sql_file" ]]; then
        if [[ "$intelis_sql_file" != /* ]]; then
            intelis_sql_file="$(pwd)/$intelis_sql_file"
        fi
        if [[ ! -f "$intelis_sql_file" ]]; then
            echo "SQL file not found: $intelis_sql_file. Please check the path."
            log_action "SQL file not found: $intelis_sql_file. Please check the path."
            exit 1
        fi
        if [[ ! "$intelis_sql_file" =~ \.(sql|sql\.gz|sql\.zst)$ ]]; then
            echo "Unsupported SQL file format: $intelis_sql_file. Use .sql, .sql.gz, or .sql.zst"
            log_action "Unsupported SQL file format: $intelis_sql_file"
            exit 1
        fi
    fi

    # --- 5. Hostname ---
    if ! $reuse_saved_answers; then
        read -p "Enter domain name (press enter to use 'intelis'): " hostname
        if [[ -n "$hostname" ]]; then
            hostname=$(echo "$hostname" | sed -E 's|^https?://||i')
            hostname=$(echo "$hostname" | sed -E 's|/*$||')
            hostname=$(echo "$hostname" | sed -E 's|:[0-9]+$||')
            hostname=$(echo "$hostname" | cut -d'/' -f1)
            if [[ -z "$hostname" ]]; then
                hostname="intelis"
                print info "Using default hostname: $hostname"
            else
                print info "Using cleaned hostname: $hostname"
            fi
        else
            hostname="intelis"
            print info "Using default hostname: $hostname"
        fi
    else
        print info "Reusing saved hostname: ${hostname}"
    fi
    log_action "Hostname: $hostname"

    # --- 6. Installation type (LIS vs STS) ---
    if ! $reuse_saved_answers; then
        read -p "Is this an LIS or STS installation? [LIS/STS] (press enter for default: LIS): " installation_type
        installation_type="${installation_type:-LIS}"
        local first_char
        first_char=$(echo "$installation_type" | cut -c1 | tr '[:upper:]' '[:lower:]')
        is_lis=false
        is_sts=false
        if [[ "$first_char" == "l" ]]; then
            is_lis=true
            log_action "Will install InteLIS as the default host"
        elif [[ "$first_char" == "s" ]]; then
            is_sts=true
            log_action "Will install InteLIS alongside other apps"
        else
            is_lis=true
            log_action "Invalid installation type '$installation_type'; defaulting to LIS"
        fi
    else
        print info "Reusing saved installation type: $([ "$is_lis" = true ] && echo LIS || echo STS)"
    fi

    # --- 7. MySQL root password (collect only; verify+persist after lamp-setup) ---
    mysql_password_needs_persisting=false
    if [ -f ~/.my.cnf ]; then
        mysql_root_password=$(awk -F= '/password/ {print $2}' ~/.my.cnf | xargs)
        echo "MySQL root password extracted from ~/.my.cnf"
    else
        echo "MySQL password not yet configured. Please provide a root password to set."
        while true; do
            read -sp "Enter MySQL root password: " mysql_root_password
            echo
            read -sp "Confirm MySQL root password: " mysql_root_password_confirm
            echo
            if [ "$mysql_root_password" != "$mysql_root_password_confirm" ]; then
                print error "Passwords do not match. Please try again."
            elif [ -z "$mysql_root_password" ]; then
                print error "Password cannot be empty. Please try again."
            else
                break
            fi
        done
        mysql_password_needs_persisting=true
    fi

    # --- 8. DB-collision strategy (skip if --db-strategy / env supplied or resuming) ---
    if ! $resume_setup && [[ -z "$DB_STRATEGY_FLAG" ]]; then
        echo
        echo "If an existing 'vlsm' database is detected, what should setup do?"
        echo "  1) DROP   – delete current database and create a fresh one"
        echo "  2) RENAME – back up to vlsm_YYYYMMDD_HHMMSS and create fresh (default)"
        echo "  3) USE    – keep existing 'vlsm' as-is and skip import"
        read -r -p "Enter choice [1=DROP, 2=RENAME(default), 3=USE]: " _dbchoice
        case "${_dbchoice:-2}" in
            1) DB_STRATEGY_FLAG="drop"   ;;
            2) DB_STRATEGY_FLAG="rename" ;;
            3) DB_STRATEGY_FLAG="use"    ;;
            *) DB_STRATEGY_FLAG="rename" ;;
        esac
        log_action "DB strategy chosen upfront: ${DB_STRATEGY_FLAG}"
    fi

    # --- 9. Remote STS URL (LIS only; validated with curl, no local setup needed) ---
    if $reuse_saved_answers; then
        [ -n "${remote_sts_url:-}" ] && print info "Reusing saved Remote STS URL: ${remote_sts_url}"
    elif $is_lis; then
        remote_sts_url=""
        local max_sts_url_attempts=3
        local sts_url_attempts=0
        while true; do
            read -p "Please enter the Remote STS URL (or press Enter to skip): " remote_sts_url
            log_action "Remote STS URL entered: $remote_sts_url"
            if [ -z "$remote_sts_url" ]; then
                echo "No STS URL provided. Skipping validation."
                log_action "No STS URL provided. Skipping validation."
                break
            fi
            remote_sts_url="${remote_sts_url%/}"
            echo "Validating the provided STS URL..."
            local response_code
            response_code=$(curl -s -o /dev/null -w "%{http_code}" "$remote_sts_url/api/version.php" || true)
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

    # --- 10. Maintenance scripts policy ---
    # The full file list isn't known until the codebase is extracted, so the
    # "pick individual scripts" mode is the only one that still has to prompt
    # at the end. "all" and "none" run unattended.
    if ! $reuse_saved_answers; then
        run_maintenance_scripts=false
        maintenance_scripts_mode="none"
        if ask_yes_no "Do you want to run maintenance scripts after setup completes?" "no"; then
            run_maintenance_scripts=true
            echo "  1) ALL  – run every maintenance script automatically (default, unattended)"
            echo "  2) PICK – list the scripts at the end and let me choose (interactive)"
            read -r -p "Enter choice [1=ALL(default), 2=PICK]: " _mchoice
            case "${_mchoice:-1}" in
                2) maintenance_scripts_mode="pick" ;;
                *) maintenance_scripts_mode="all"  ;;
            esac
            log_action "Maintenance scripts policy: ${maintenance_scripts_mode}"
        fi
    else
        print info "Reusing saved maintenance policy: ${maintenance_scripts_mode:-none}"
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
PHP_VERSION="8.4"

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

collect_user_inputs

# Download and install lamp-setup script
download_file  "lamp-setup.sh" "https://raw.githubusercontent.com/deforay/utility-scripts/master/lamp/lamp-setup.sh" "Downloading lamp-setup.sh..." || {
    print error "LAMP Setup file download failed - cannot continue with update"
    log_action "LAMP Setup file download failed - update aborted"
    exit 1
}

chmod u+x ./lamp-setup.sh

./lamp-setup.sh $PHP_VERSION

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

download_file "master.tar.gz" "https://codeload.github.com/deforay/intelis/tar.gz/refs/heads/master" "Downloading LIS package..." || {
    print error "LIS download failed - cannot continue with setup"
    log_action "LIS download failed - setup aborted"
    exit 1
}

# Extract the tar.gz file into temporary directory
temp_dir=$(mktemp -d)
print info "Extracting files from master.tar.gz..."

tar -xzf master.tar.gz -C "$temp_dir" &
tar_pid=$!           # Save tar PID
spinner "${tar_pid}" # Spinner tracks extraction
wait ${tar_pid}      # Wait for extraction to finish

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

# Remove the empty directory and the downloaded zip file
rm -rf "$temp_dir/intelis-master/"
rm master.tar.gz

log_action "LIS copied to ${lis_path}."

# Set proper permissions
set_permissions "${lis_path}" "quick" "sync"
find "${lis_path}" -exec chown www-data:www-data {} \; 2>/dev/null || true

# Run Composer Install as www-data
print header "Running composer operations"
cd "${lis_path}"

# Ensure composer files are writable by www-data before running composer commands
chown www-data:www-data "${lis_path}/composer.json" "${lis_path}/composer.lock" 2>/dev/null || true

# Configure composer timeout regardless of installation path
sudo -u www-data composer config process-timeout 30000
sudo -u www-data composer clear-cache

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
        find "${lis_path}/vendor" -exec chown www-data:www-data {} \; 2>/dev/null || true
        chmod -R 755 "${lis_path}/vendor" 2>/dev/null || true

        print success "Vendor files successfully installed"

        # Update the composer.lock file to match the current state
        print info "Finalizing composer installation..."
        sudo -u www-data composer install --no-scripts --no-autoloader --prefer-dist --no-dev
    else
        print warning "Vendor package not found in GitHub releases. Proceeding with regular composer install."

        # Perform full install if vendor.tar.gz isn't available
        print info "Running full composer install (this may take a while)..."
        sudo -u www-data composer install --prefer-dist --no-dev
    fi
else
    print info "Dependencies are up to date. Skipping vendor download."
fi

# Always generate the optimized autoloader, regardless of install path
sudo -u www-data composer dump-autoload -o

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
# Check if the hostname entry is already in /etc/hosts
if ! grep -q "127.0.0.1 ${hostname}" /etc/hosts; then
    print info "Adding ${hostname} to hosts file..."
    echo "127.0.0.1 ${hostname}" | tee -a /etc/hosts
    log_action "${hostname} entry added to hosts file."
else
    print info "${hostname} entry is already in the hosts file."
    log_action "${hostname} entry is already in the hosts file."
fi

if ! grep -q "127.0.0.1 intelis" /etc/hosts; then
    print info "Adding intelis to hosts file..."
    echo "127.0.0.1 intelis" | tee -a /etc/hosts
    log_action "intelis entry added to hosts file."
else
    print info "intelis entry is already in the hosts file."
    log_action "intelis entry is already in the hosts file."
fi


if ! grep -q "127.0.0.1 vlsm" /etc/hosts; then
    print info "Adding vlsm to hosts file..."
    echo "127.0.0.1 vlsm" | tee -a /etc/hosts
    log_action "vlsm entry added to hosts file."
else
    print info "vlsm entry is already in the hosts file."
    log_action "vlsm entry is already in the hosts file."
fi


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

changes_needed=false

# --- dry-run check first ---
for setting in "${!mysql_settings[@]}"; do
    if ! grep -qE "^[[:space:]]*$setting[[:space:]]*=[[:space:]]*${mysql_settings[$setting]}" "$mysql_cnf"; then
        changes_needed=true
        break
    fi
done

if [ "$changes_needed" = false ]; then
    for obsolete in "${mysql_obsolete_keys[@]}"; do
        if grep -qE "^[[:space:]]*$obsolete[[:space:]]*=" "$mysql_cnf"; then
            changes_needed=true
            break
        fi
    done
fi

if [ "$changes_needed" = true ]; then
    print info "Changes needed. Backing up and updating MySQL config..."
    print info "Detected MySQL flavor: $([ "$mysql_is_mariadb" = true ] && echo MariaDB || echo MySQL) ${mysql_major_minor:-unknown}"
    cp "$mysql_cnf" "${mysql_cnf}.bak.${backup_timestamp}"

    # Comment out any obsolete-on-this-version keys first.
    for obsolete in "${mysql_obsolete_keys[@]}"; do
        if grep -qE "^[[:space:]]*$obsolete[[:space:]]*=" "$mysql_cnf"; then
            print info "Disabling obsolete option for this server: $obsolete"
            sed -i "/^[[:space:]]*$obsolete[[:space:]]*=.*/s/^/#/" "$mysql_cnf"
        fi
    done

    for setting in "${!mysql_settings[@]}"; do
        if ! grep -qE "^[[:space:]]*$setting[[:space:]]*=[[:space:]]*${mysql_settings[$setting]}" "$mysql_cnf"; then
            # Comment existing wrong setting if found
            if grep -qE "^[[:space:]]*$setting[[:space:]]*=" "$mysql_cnf"; then
                sed -i "/^[[:space:]]*$setting[[:space:]]*=.*/s/^/#/" "$mysql_cnf"
            fi
            echo "$setting = ${mysql_settings[$setting]}" >>"$mysql_cnf"
        fi
    done

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

php "${lis_path}/vendor/bin/db-tools" db:test --all

print header "Running database migrations and other post-install tasks"
cd "${lis_path}"
# Audit Trail v2 triggers are generated inside `composer post-install`
# (right after the migrate step), so no separate invocation is needed here.
sudo -u www-data composer post-install

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

# Set proper permissions
download_file "/usr/local/bin/intelis-refresh" https://raw.githubusercontent.com/deforay/intelis/master/scripts/refresh.sh
chmod +x /usr/local/bin/intelis-refresh
(print success "Setting final permissions in the background..." &&
    intelis-refresh -p "${lis_path}" -m full >/dev/null 2>&1 &&
    find "${lis_path}" -exec chown www-data:www-data {} \; 2>/dev/null || true) &
disown

restart_service apache

print success "Setup complete. Proceed to LIS setup."
log_action "Setup complete. Proceed to LIS setup."
