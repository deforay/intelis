#!/bin/bash

# To use this script:
# cd ~;
# wget -O intelis-setup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh
# sudo chmod u+x intelis-setup.sh;
# sudo ./intelis-setup.sh;

set -Eeuo pipefail
umask 022


# Check if running as root
if (( EUID != 0 )); then
    echo "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo" >&2
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
export log_file

# Error trap
trap 'error_handling "${BASH_COMMAND}" "$LINENO" "$?"' ERR

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
    if [[ "$sql_file" == *".gz" ]]; then
        gunzip -c "$sql_file" | mysql vlsm
    elif [[ "$sql_file" == *".zip" ]]; then
        unzip -p "$sql_file" '*.sql' | mysql vlsm
    else
        mysql vlsm < "$sql_file"
    fi

    [[ -f "${lis_path}/sql/audit-triggers.sql"   ]] && mysql vlsm        < "${lis_path}/sql/audit-triggers.sql"
    [[ -f "${lis_path}/sql/interface-init.sql"   ]] && mysql interfacing  < "${lis_path}/sql/interface-init.sql"

    echo "Database setup/import completed."
    log_action "Database setup/import completed (strategy: ${strategy:-create})."
}


# Save the current trap settings
current_trap=$(trap -p ERR)

# Disable the error trap temporarily
trap - ERR

echo "Enter the LIS installation path [press enter to select /var/www/intelis]: "
read -t 60 lis_path

# Check if read command timed out or no input was provided
if [ $? -ne 0 ] || [ -z "$lis_path" ]; then
    lis_path="/var/www/intelis"
    echo "Using default path: $lis_path"
else
    echo "LIS installation path is set to ${lis_path}."
fi

log_action "LIS installation path is set to ${lis_path}."

# Restore the previous error trap
eval "$current_trap"

# Initialize variable for database file path
intelis_sql_file=""
DB_STRATEGY_FLAG=""

# ---- CLI flags  ----
INSTALL_TYPE_FLAG=""
STS_URL_FLAG=""
LIS_PATH_FLAG=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        # new flags
        --type)           INSTALL_TYPE_FLAG="$2"; shift 2;;
        --type=*)         INSTALL_TYPE_FLAG="${1#*=}"; shift;;
        --sts-url)        STS_URL_FLAG="$2"; shift 2;;
        --sts-url=*)      STS_URL_FLAG="${1#*=}"; shift;;
        --lis-path)       LIS_PATH_FLAG="$2"; shift 2;;
        --lis-path=*)     LIS_PATH_FLAG="${1#*=}"; shift;;

        # existing flags
        --database=*|--db=*)
        intelis_sql_file="${1#*=}"; shift;;
        --database|--db)
        intelis_sql_file="$2"; shift 2;;
        --db-strategy=*)
        DB_STRATEGY_FLAG="${1#*=}"; shift;;
        --db-strategy)
        DB_STRATEGY_FLAG="$2"; shift 2;;

        # unknown
        *)
        shift;;
    esac
done

# prefer flag > env > prompt (this safely overrides the earlier lis_path prompt if provided)
lis_path="${LIS_PATH_FLAG:-${INTELIS_LIS_PATH:-$lis_path}}"

# Check if the specified SQL file exists
if [[ -n "$intelis_sql_file" ]]; then
    # Check if the file path is absolute or relative
    if [[ "$intelis_sql_file" != /* ]]; then
        # File path is relative, check in the current directory
        intelis_sql_file="$(pwd)/$intelis_sql_file"
    fi

    if [[ ! -f "$intelis_sql_file" ]]; then
        echo "SQL file not found: $intelis_sql_file. Please check the path."
        log_action "SQL file not found: $intelis_sql_file. Please check the path."
        exit 1
    fi
fi

PHP_VERSION=8.2

# Download and install lamp-setup script
download_file  "lamp-setup.sh" "https://raw.githubusercontent.com/deforay/utility-scripts/master/lamp/lamp-setup.sh" "Downloading lamp-setup.sh..." || {
    print error "LAMP Setup file download failed - cannot continue with update"
    log_action "LAMP Setup file download failed - update aborted"
    exit 1
}

chmod u+x ./lamp-setup.sh

./lamp-setup.sh $PHP_VERSION

rm -f ./lamp-setup.sh

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
trap 'rm -rf "$temp_dir"' EXIT   # ensures cleanup on any exit
print info "Extracting files from master.tar.gz..."
tar -xzf master.tar.gz -C "$temp_dir" &
tar_pid=$!
spinner "${tar_pid}" # Spinner tracks extraction
wait ${tar_pid}
tar_status=$?
if (( tar_status != 0 )); then
    print error "Extraction failed (status=${tar_status})"
    exit 1
fi
log_action "LIS downloaded."

# backup old code if it exists
if [ -d "${lis_path}" ]; then
    rsync -a --delete --info=progress2 "${lis_path}/" "${lis_path}-$(date +%Y%m%d-%H%M%S)/"
else
    mkdir -p "${lis_path}"
fi

# Copy the unzipped content to the LIS PATH, overwriting any existing files
# cp -R "$temp_dir/intelis-master/"* "${lis_path}"
rsync -a --info=progress2 "$temp_dir/intelis-master/" "$lis_path/"

# Remove the empty directory and the downloaded zip file
rm -rf "$temp_dir/intelis-master/"
rm master.tar.gz


log_action "LIS copied to ${lis_path}."

# Set proper permissions
set_permissions "${lis_path}" "quick"
find "${lis_path}" -exec chown www-data:www-data {} \; 2>/dev/null || true

# Run Composer Install as www-data
print header "Running composer operations"
cd "${lis_path}"

# Configure composer timeout regardless of installation path
sudo -u www-data composer config process-timeout 30000 --no-interaction
sudo -u www-data composer clear-cache --no-interaction

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
        sudo -u www-data composer install --no-scripts --no-autoloader --prefer-dist --no-dev --no-interaction
    else
        print warning "Vendor package not found in GitHub releases. Proceeding with regular composer install."

        # Perform full install if vendor.tar.gz isn't available
        print info "Running full composer install (this may take a while)..."
        sudo -u www-data composer install --prefer-dist --no-dev --no-interaction
    fi
else
    print info "Dependencies are up to date. Skipping vendor download."
fi

# Always generate the optimized autoloader, regardless of install path
sudo -u www-data composer dump-autoload -o --no-interaction

log_action "Composer operations completed."

# Ask user for the hostname
# Ask user for the hostname
if [[ -t 0 ]]; then
  read -p "Enter domain name (press enter to use 'intelis'): " hostname
else
  hostname=""
fi
hostname="$(printf '%s' "$hostname" | xargs)"
: "${hostname:=intelis}"


# Trim leading/trailing whitespace early
hostname="$(printf '%s' "$hostname" | xargs)"

# Clean up the hostname: remove protocol and trailing slashes
if [[ -n "$hostname" ]]; then

    hostname=$(printf '%s' "$hostname" | tr '[:upper:]' '[:lower:]')

    # Remove http:// or https:// if present
    hostname=$(echo "$hostname" | sed -E 's|^https?://||i')

    # Remove trailing slashes
    hostname=$(echo "$hostname" | sed -E 's|/*$||')

    # Remove any port number if present
    hostname=$(echo "$hostname" | sed -E 's|:[0-9]+$||')

    # Remove any path components
    hostname=$(echo "$hostname" | cut -d'/' -f1)

    # If user entered something that became empty after cleanup, use default
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

log_action "Hostname: $hostname"
# Idempotently ensure names are present on a given IP line in /etc/hosts.
# If the IP line exists, append any missing names to that line.
# If it doesn't, create a new line with all names.
ensure_hosts_mapping() {
  local ip="$1"; shift
  local names=("$@")
  local hosts="/etc/hosts"
  local tmp="$(mktemp)"

  awk -v ip="$ip" -v req="$(printf "%s " "${names[@]}")" '
    BEGIN {
      n=split(req, R)
      for (i=1;i<=n;i++) if (R[i]!="") need[R[i]]=1
      updated=0
    }
    {
      line=$0
      sub(/\r$/, "", line)  # strip CR if any

      # Pass through full-line comments unchanged
      if (match(line, /^[[:space:]]*#.*$/)) { print line; next }

      # Tokenize up to any inline comment
      split("", T); nt=0
      for (i=1;i<=NF;i++) { if ($i ~ /^#/) break; T[++nt]=$i }

      if (nt>0 && T[1]==ip) {
        # Reset per-line presence
        delete present
        for (i=2;i<=nt;i++) present[T[i]]=1

        # Rebuild the line once, appending any missing required names
        out=ip
        for (i=2;i<=nt;i++) out=out" "T[i]
        for (n in need) if (!(n in present)) out=out" "n

        # Only print this *first* occurrence; skip later duplicates wholly
        if (!updated) { print out; updated=1 }
        # else: drop duplicate ip lines (we normalize to one line)
        next
      }

      print line
    }
    END {
      # If no existing line had the IP, add a new one with all names
      if (!updated) {
        out=ip
        for (n in need) out=out" "n
        print out
      }
    }
  ' "$hosts" > "$tmp" && cat "$tmp" > "$hosts" && rm -f "$tmp"
}


# Use it once to ensure all three names share the same 127.0.0.1 entry
ensure_hosts_mapping "127.0.0.1" "${hostname}" "intelis" "vlsm"
systemd-resolve --flush-caches 2>/dev/null || resolvectl flush-caches 2>/dev/null || true


print info "Ensured hosts mapping for 127.0.0.1 → ${hostname} intelis vlsm"
log_action "Ensured hosts mapping for 127.0.0.1: ${hostname}, intelis, vlsm"


# --- Installation type prompt (LIS / STS / Standalone) ---
install_type=""
is_lis=false
is_sts=false
is_standalone=false

normalize_type() {
  case "$(echo "$1" | tr '[:upper:]' '[:lower:]')" in
    1|l|lis) echo "LIS" ;;
    2|s|sts) echo "STS" ;;
    3|standalone|sa) echo "Standalone" ;;
    *) echo "" ;;
  esac
}

install_type="$(normalize_type "${INSTALL_TYPE_FLAG:-${INTELIS_TYPE:-}}")"
if [[ -z "$install_type" ]]; then
  if [[ -t 0 ]]; then
    # TTY present → prompt
    while true; do
      echo
      echo "Choose installation type:"
      echo "  1) LIS"
      echo "  2) STS"
      echo "  3) Standalone"
      read -r -p "Enter choice [1-3] (default: 1): " choice
      install_type="$(normalize_type "${choice:-1}")"
      [[ -n "$install_type" ]] && break
      print warning "Invalid choice. Please enter 1, 2, or 3."
    done
  else
    install_type="LIS"  # default in non-interactive
  fi
fi

is_lis=false; is_sts=false; is_standalone=false
[[ "$install_type" == "LIS" ]] && is_lis=true
[[ "$install_type" == "STS" ]] && is_sts=true
[[ "$install_type" == "Standalone" ]] && is_standalone=true

log_action "Installation type selected: ${install_type}"


# --- Apache vhost helpers (idempotent) ---

update_000_default_idempotent() {
  local docroot="$1"
  local file="/etc/apache2/sites-available/000-default.conf"
  local begin="# BEGIN INTELIS"
  local end="# END INTELIS"

  if [[ ! -f "$file" ]]; then
    print error "Missing ${file}; Apache default site not found."
    return 1
  fi

  # strip prior INTELIS block
  awk -v b="$begin" -v e="$end" '$0==b{in=1;next}$0==e{in=0;next}!in{print}' "$file" > "${file}.tmp"

  # inject fresh INTELIS block right after the first <VirtualHost ...>
  awk -v b="$begin" -v e="$end" -v dr="$docroot" '
    BEGIN{ins=0}
    /<VirtualHost[[:space:]][^>]+>/ && !ins {
      print
      print b
      print "    DocumentRoot " dr
      print "    <Directory " dr ">"
      print "        AddDefaultCharset UTF-8"
      print "        Options -Indexes -MultiViews +FollowSymLinks"
      print "        AllowOverride All"
      print "        Require all granted"
      print "    </Directory>"
      print e
      ins=1; next
    }
    {print}
    END {
      if(!ins){
        print b
        print "    DocumentRoot " dr
        print "    <Directory " dr ">"
        print "        AddDefaultCharset UTF-8"
        print "        Options -Indexes -MultiViews +FollowSymLinks"
        print "        AllowOverride All"
        print "        Require all granted"
        print "    </Directory>"
        print e
      }
    }
  ' "${file}.tmp" > "${file}.new"

  mv "${file}.new" "$file" && rm -f "${file}.tmp"
  print success "Updated 000-default.conf → IP → ${docroot}"
}

# Optional: also map https://IP/ → LIS if default-ssl is present/enabled
update_default_ssl_idempotent() {
  local docroot="$1"
  local file="/etc/apache2/sites-available/default-ssl.conf"
  local begin="# BEGIN INTELIS"
  local end="# END INTELIS"

  # enable ssl mods & site if possible (no-op if already enabled)
  a2enmod ssl >/dev/null 2>&1 || true
  [[ -f "$file" ]] && a2ensite default-ssl >/dev/null 2>&1 || true

  [[ ! -f "$file" ]] && { print info "default-ssl.conf not found; skipping https IP mapping"; return 0; }

  awk -v b="$begin" -v e="$end" '$0==b{in=1;next}$0==e{in=0;next}!in{print}' "$file" > "${file}.tmp"

  awk -v b="$begin" -v e="$end" -v dr="$docroot" '
    BEGIN{ins=0}
    /<VirtualHost[[:space:]]+\*:443>/ && !ins {
      print
      print b
      print "    DocumentRoot " dr
      print "    <Directory " dr ">"
      print "        AddDefaultCharset UTF-8"
      print "        Options -Indexes -MultiViews +FollowSymLinks"
      print "        AllowOverride All"
      print "        Require all granted"
      print "    </Directory>"
      print e
      ins=1; next
    }
    {print}
  ' "${file}.tmp" > "${file}.new"

  mv "${file}.new" "$file" && rm -f "${file}.tmp"
  print success "Updated default-ssl.conf → HTTPS IP → ${docroot}"
}

make_vhost_if_absent() {
  local site="$1" docroot="$2"
  local file="/etc/apache2/sites-available/${site}.conf"
  if [[ ! -f "$file" ]]; then
    cat >"$file" <<EOF
<VirtualHost *:80>
    ServerName ${site}
    ServerAlias intelis
    ServerAlias vlsm
    DocumentRoot ${docroot}
    <Directory ${docroot}>
        AddDefaultCharset UTF-8
        Options -Indexes -MultiViews +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/${site}_error.log
    CustomLog \${APACHE_LOG_DIR}/${site}_access.log combined
</VirtualHost>
EOF

    print success "Created vhost ${file}"
  else
    print info "Vhost ${site}.conf exists; leaving as-is."
  fi
  a2ensite "${site}.conf" >/dev/null 2>&1 || true
}


if $is_lis; then
  print info "Configuring default vhost so http(s)://<IP>/ → ${lis_path}/public"
  update_000_default_idempotent "${lis_path}/public"
  a2ensite 000-default >/dev/null 2>&1 || true

  update_default_ssl_idempotent "${lis_path}/public"   # safe no-op if default-ssl absent
else
  print info "Creating dedicated vhost for ${hostname}; leaving 000-default untouched"
  make_vhost_if_absent "${hostname}" "${lis_path}/public"
fi

a2enmod rewrite >/dev/null 2>&1 || true
a2enmod headers >/dev/null 2>&1 || true
a2enmod ssl >/dev/null 2>&1 || true
a2enmod deflate >/dev/null 2>&1 || true


# sanity check & reload
apachectl configtest >/dev/null 2>&1 || { print error "apachectl configtest failed"; exit 1; }
restart_service apache || { print error "Apache restart failed"; exit 1; }

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

# Extract MySQL root password or create ~/.my.cnf if missing

if [ -f ~/.my.cnf ]; then
    # Extract password from .my.cnf
    mysql_root_password=$(awk -F= '/password/ {print $2}' ~/.my.cnf | xargs)
    echo "MySQL root password extracted"
else
    # Prompt user for MySQL root password
    echo "Warning: mysql password not found. Please provide the MySQL root password to create one."
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

    # Verify the password
    echo "Verifying MySQL root password..."
    if ! mysqladmin ping -u root -p"$mysql_root_password" &>/dev/null; then
        print error "Unable to verify the password. Please check and try again."
        exit 1
    fi

    # Create ~/.my.cnf
    echo "Storing MySQL password for secure login..."
    cat <<EOF >~/.my.cnf
[client]
user=root
password=${mysql_root_password}
host=localhost
EOF
    chmod 600 ~/.my.cnf

    echo "MySQL credentials saved in secure file."
fi

# Escape special characters in password for sed
# This uses Perl's quotemeta which is more reliable when dealing with many special characters
escaped_mysql_root_password=$(perl -e 'print quotemeta $ARGV[0]' -- "${mysql_root_password}")

# Use sed to update database configurations, using | as a delimiter instead of /
sed -i "s|\$systemConfig\['database'\]\['host'\]\s*=.*|\$systemConfig['database']['host'] = 'localhost';|" "${config_file}"
sed -i "s|\$systemConfig\['database'\]\['username'\]\s*=.*|\$systemConfig['database']['username'] = 'root';|" "${config_file}"
sed -i "s|\$systemConfig\['database'\]\['password'\]\s*=.*|\$systemConfig['database']['password'] = '$escaped_mysql_root_password';|" "${config_file}"

sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['host'\]\s*=.*|\$systemConfig['interfacing']['database']['host'] = 'localhost';|" "${config_file}"
sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['username'\]\s*=.*|\$systemConfig['interfacing']['database']['username'] = 'root';|" "${config_file}"
sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['password'\]\s*=.*|\$systemConfig['interfacing']['database']['password'] = '$escaped_mysql_root_password';|" "${config_file}"

# Handle database setup and SQL file import
if [[ -n "$intelis_sql_file" && -f "$intelis_sql_file" ]]; then
    handle_database_setup_and_import "$intelis_sql_file"
elif [[ -n "$intelis_sql_file" ]]; then
    print error "SQL file not found: $intelis_sql_file. Please check the path."
    exit 1
else
    handle_database_setup_and_import # Default to init.sql
fi


mysql_cnf="/etc/mysql/mysql.conf.d/mysqld.cnf"
backup_timestamp=$(date +%Y%m%d%H%M%S)

# --- define what we want ---
declare -A mysql_settings=(
    ["sql_mode"]=""
    ["innodb_strict_mode"]="0"
    ["character-set-server"]="utf8mb4"
    ["collation-server"]="utf8mb4_unicode_ci"
    ["default_authentication_plugin"]="mysql_native_password"
    ["max_connect_errors"]="10000"
)

changes_needed=false

# --- dry-run check first ---
for setting in "${!mysql_settings[@]}"; do
    if ! grep -qE "^[[:space:]]*$setting[[:space:]]*=[[:space:]]*${mysql_settings[$setting]}" "$mysql_cnf"; then
        changes_needed=true
        break
    fi
done

if [ "$changes_needed" = true ]; then
    print info "Changes needed. Backing up and updating MySQL config..."
    cp "$mysql_cnf" "${mysql_cnf}.bak.${backup_timestamp}"

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
    print debug "Using user-provided MySQL root password"
elif [ -f "${lis_path}/configs/config.production.php" ]; then
    mysql_pw=$(extract_mysql_password_from_config "${lis_path}/configs/config.production.php")
    print debug "Extracted MySQL root password from config.production.php"
else
    print error "MySQL root password not provided and config.production.php not found."
    exit 1
fi

if [ -z "$mysql_pw" ]; then
    print warning "Password in config file is empty or missing. Prompting for manual entry..."
    read -sp "Please enter MySQL root password: " mysql_pw
    echo
fi

persist_result=$(MYSQL_PWD="${mysql_pw}" mysql -u root -e "SET PERSIST sql_mode = '';" 2>&1)
persist_status=$?

if [ $persist_status -eq 0 ]; then
    print success "Successfully persisted sql_mode=''"
    log_action "Applied SET PERSIST sql_mode = '';"
else
    print warning "SET PERSIST failed: $persist_result"
    log_action "SET PERSIST sql_mode failed: $persist_result"
fi

chmod 644 "$mysql_cnf"
restart_service mysql

# Remote STS URL is REQUIRED only for LIS nodes; skipped for STS/Standalone
if $is_lis; then
    remote_sts_url="${STS_URL_FLAG:-${INTELIS_STS_URL:-}}"
    trim_url() { echo -n "$1" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' | sed 's:/*$::'; }

    validate_sts() {
        local u; u="$(trim_url "$1")"
        [[ -z "$u" ]] && return 1
        # basic scheme guard
        if ! [[ "$u" =~ ^https?:// ]]; then
        print warning "STS URL missing scheme, assuming https://"
        u="https://$u"
        fi
        # hit version endpoint with timeout & retry
        local code
        code=$(curl -sS -m 6 --connect-timeout 4 --retry 2 --retry-delay 1 -o /dev/null -w '%{http_code}' "$u/api/version.php" || echo "000")
        [[ "$code" == "200" ]] && { remote_sts_url="$u"; return 0; }
        return 1
    }

    if [[ -n "$remote_sts_url" ]]; then
        if ! validate_sts "$remote_sts_url"; then
        print error "Provided STS URL failed validation for LIS. (Use --sts-url or INTELIS_STS_URL)."
        exit 1
        fi
    else
        # prompt only if TTY
        if [[ -t 0 ]]; then
        while true; do
            read -r -p "Enter the Remote STS URL (required for LIS, e.g., https://sts.example.com): " candidate
            if validate_sts "$candidate"; then break; fi
            print error "Validation failed. Please re-enter."
        done
        else
        print error "LIS requires STS URL in non-interactive mode. Provide --sts-url or INTELIS_STS_URL."
        exit 1
        fi
    fi

    


    # persist to config
    config_file="${lis_path}/configs/config.production.php"
    desired_sts_url="\$systemConfig['remoteURL'] = '${remote_sts_url}';"

    if [[ ! -w "${config_file}" ]]; then
        print error "Config ${config_file} is not writable."
        exit 1
    fi

    if grep -qE "^\s*\$systemConfig\['remoteURL'\]\s*=" "${config_file}"; then
        sed -i "s|\$systemConfig\['remoteURL'\]\s*=.*|${desired_sts_url}|" "${config_file}"
    else
        printf "\n%s\n" "${desired_sts_url}" >> "${config_file}"
    fi
    log_action "STS URL set to ${remote_sts_url}"
fi



if grep -q "\['cache_di'\] => false" "${config_file}"; then
    sed -i "s|\('cache_di' => \)false,|\1true,|" "${config_file}"
fi

# Set ACLs
set_permissions "${lis_path}" "quick"

php bin/db-tools.php config-test

print header "Running database migrations and other post-install tasks"
cd "${lis_path}"
sudo -u www-data composer post-install &
pid=$!
spinner "$pid"
wait $pid

if ask_yes_no "Do you want to run maintenance scripts?" "no"; then
    # List the files in maintenance directory
    echo "Available maintenance scripts to run:"
    files=("${lis_path}/maintenance/"*.php)
    for i in "${!files[@]}"; do
        filename=$(basename "${files[$i]}")
        echo "$((i + 1))) $filename"
    done

    # Ask which files to run
    echo "Enter the numbers of the scripts you want to run separated by commas (e.g., 1,2,4) or type 'all' to run them all."
    read -r files_to_run

    # Run selected files
    if [[ "$files_to_run" == "all" ]]; then
        for file in "${files[@]}"; do
            echo "Running $file..."
            sudo -u www-data php "$file"
        done
    else
        IFS=',' read -ra ADDR <<<"$files_to_run"
        for i in "${ADDR[@]}"; do
            # Remove any spaces in the input and correct the array index
            i=$(echo "$i" | xargs)
            file_index=$((i - 1))

            # Check if the selected index is within the range of available files
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


if [ -f "${lis_path}/var/cache/CompiledContainer.php" ]; then
    rm "${lis_path}/var/cache/CompiledContainer.php"
fi

# Set proper permissions
download_file "/usr/local/bin/intelis-refresh" \
  "https://raw.githubusercontent.com/deforay/intelis/master/scripts/refresh.sh" \
  "Downloading intelis-refresh..."

chmod +x /usr/local/bin/intelis-refresh
(print success "Setting final permissions in the background..." &&
    intelis-refresh -p "${lis_path}" -m full >/dev/null 2>&1 &&
    find "${lis_path}" -exec chown www-data:www-data {} \; 2>/dev/null || true) &
disown

print success "Setup complete. Proceed to LIS setup."
log_action "Setup complete. Proceed to LIS setup."
