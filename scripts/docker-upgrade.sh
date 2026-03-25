#!/bin/bash

# Usage:
#   cd /path/to/intelis && sudo ./scripts/docker-upgrade.sh
#
# Options:
#   -p PATH   Specify the installation path (default: current directory)
#   -b        Skip backup prompt
#   -s        Skip code update (just restart containers to re-run migrations)

set -euo pipefail

# --- Defaults ---
lis_path="$(cd "$(dirname "$0")/.." && pwd)"
skip_backup=false
skip_code_update=false

# --- Parse options ---
while getopts ":p:bs" opt; do
    case $opt in
        p) lis_path="$(cd "$OPTARG" && pwd)" ;;
        b) skip_backup=true ;;
        s) skip_code_update=true ;;
        *) ;;
    esac
done

# --- Validation ---
if [ ! -f "$lis_path/docker-compose.yml" ]; then
    echo "Error: docker-compose.yml not found in $lis_path"
    echo "Usage: $0 [-p /path/to/intelis]"
    exit 1
fi

if [ ! -f "$lis_path/composer.json" ]; then
    echo "Error: Not a valid InteLIS installation at $lis_path"
    exit 1
fi

cd "$lis_path"

echo "========================================"
echo "  InteLIS Docker Upgrade"
echo "  Path: $lis_path"
echo "========================================"
echo ""

# --- Check Docker is running ---
if ! docker compose version >/dev/null 2>&1; then
    echo "Error: Docker Compose is not available. Is Docker running?"
    exit 1
fi

# --- Backup ---
if [ "$skip_backup" = false ]; then
    read -r -p "Do you want to backup the database before upgrading? [y/N]: " do_backup
    if [[ "$do_backup" =~ ^[Yy] ]]; then
        timestamp=$(date +%Y%m%d-%H%M%S)
        backup_dir="${lis_path}/backups/db"
        mkdir -p "$backup_dir"

        echo "Backing up database..."
        docker compose exec -T db mysqldump -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" \
            --all-databases --single-transaction --quick \
            | gzip > "${backup_dir}/all_databases_${timestamp}.sql.gz"

        echo "Backup saved to: ${backup_dir}/all_databases_${timestamp}.sql.gz"
    fi
fi

# --- Save current composer checksums ---
current_json_md5="none"
current_lock_md5="none"
if [ -f composer.json ]; then
    current_json_md5=$(md5sum composer.json 2>/dev/null | awk '{print $1}' || md5 -q composer.json 2>/dev/null || echo "none")
fi
if [ -f composer.lock ]; then
    current_lock_md5=$(md5sum composer.lock 2>/dev/null | awk '{print $1}' || md5 -q composer.lock 2>/dev/null || echo "none")
fi

# --- Update code ---
if [ "$skip_code_update" = false ]; then
    echo ""
    echo "Downloading latest InteLIS..."

    temp_dir=$(mktemp -d)
    trap 'rm -rf "$temp_dir"' EXIT

    if command -v wget >/dev/null 2>&1; then
        wget -q -O "$temp_dir/master.tar.gz" \
            "https://codeload.github.com/deforay/intelis/tar.gz/refs/heads/master"
    elif command -v curl >/dev/null 2>&1; then
        curl -sL -o "$temp_dir/master.tar.gz" \
            "https://codeload.github.com/deforay/intelis/tar.gz/refs/heads/master"
    else
        echo "Error: Neither wget nor curl found."
        exit 1
    fi

    echo "Extracting..."
    tar -xzf "$temp_dir/master.tar.gz" -C "$temp_dir"

    echo "Updating files..."
    rsync -a --exclude='.env' \
        --exclude='configs/config.production.php' \
        --exclude='docker-compose.override.yml' \
        --exclude='public/uploads/' \
        --exclude='public/temporary/' \
        --exclude='var/' \
        --exclude='backups/' \
        --exclude='backup/' \
        "$temp_dir/intelis-master/" "$lis_path/"

    echo "Code updated."
fi

# --- Check if composer dependencies changed ---
new_json_md5="none"
new_lock_md5="none"
if [ -f composer.json ]; then
    new_json_md5=$(md5sum composer.json 2>/dev/null | awk '{print $1}' || md5 -q composer.json 2>/dev/null || echo "none")
fi
if [ -f composer.lock ]; then
    new_lock_md5=$(md5sum composer.lock 2>/dev/null | awk '{print $1}' || md5 -q composer.lock 2>/dev/null || echo "none")
fi

need_composer_install=false
if [ ! -d vendor ]; then
    need_composer_install=true
elif [ "$current_json_md5" != "$new_json_md5" ] || [ "$current_lock_md5" != "$new_lock_md5" ]; then
    need_composer_install=true
fi

# --- Vendor update ---
if [ "$need_composer_install" = true ]; then
    echo ""
    echo "Composer dependencies changed. Updating vendor..."

    # Try pre-built vendor archive first (faster)
    vendor_downloaded=false
    if command -v curl >/dev/null 2>&1 && \
       curl --output /dev/null --silent --head --fail \
           "https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz"; then

        echo "Downloading pre-built vendor packages..."
        curl -sL -o /tmp/vendor.tar.gz \
            "https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz"
        curl -sL -o /tmp/vendor.tar.gz.md5 \
            "https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz.md5"

        if (cd /tmp && md5sum -c vendor.tar.gz.md5 >/dev/null 2>&1); then
            echo "Extracting vendor packages..."
            tar -xzf /tmp/vendor.tar.gz -C "$lis_path"
            vendor_downloaded=true
        fi
        rm -f /tmp/vendor.tar.gz /tmp/vendor.tar.gz.md5
    fi

    # Run composer install in the web container
    echo "Running composer install..."
    docker compose exec -T web composer install --no-dev --optimize-autoloader --no-interaction
    docker compose exec -T web composer dump-autoload -o --no-interaction
else
    echo ""
    echo "Composer dependencies unchanged. Skipping vendor update."
fi

# --- Restart containers ---
# The entrypoint handles: cache purge, migrations, db:repair, run-once scripts, sql_mode persist
echo ""
echo "Restarting containers..."
docker compose down
docker compose up -d

echo ""
echo "Waiting for containers to be healthy..."
timeout=120
elapsed=0
while [ $elapsed -lt $timeout ]; do
    if docker compose exec -T web true 2>/dev/null; then
        break
    fi
    sleep 2
    elapsed=$((elapsed + 2))
done

if [ $elapsed -ge $timeout ]; then
    echo "Warning: Containers took too long to start. Check logs with: docker compose logs"
    exit 1
fi

# --- Fix permissions on bind mount ---
docker compose exec -T web bash -c 'chown -R www-data:www-data /var/www/html/var /var/www/html/public/uploads /var/www/html/public/temporary 2>/dev/null || true'

echo ""
echo "========================================"
echo "  Upgrade complete!"
echo "  Check logs: docker compose logs -f web"
echo "========================================"
