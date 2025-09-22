#!/bin/bash

# To use this script:
# sudo wget -O /usr/local/bin/intelis-refresh https://raw.githubusercontent.com/deforay/intelis/master/scripts/refresh.sh && sudo chmod +x /usr/local/bin/intelis-refresh
# sudo intelis-refresh

if [ "$EUID" -ne 0 ]; then
    echo "Need admin privileges. Use: sudo intelis-refresh"
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

# Show help if requested
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    echo "Usage: sudo intelis-refresh [-p path] [-m mode] [-a] [-d]"
    echo "  -p : LIS install path (default: /var/www/vlsm)"
    echo "  -m : Mode (full, quick, minimal)"
    echo "  -a : Restart Apache/httpd"
    echo "  -d : Restart MySQL"
    exit 0
fi

lis_path=""
mode="full"
log_file="/tmp/intelis-refresh-$(date +'%Y%m%d-%H%M%S').log"
restart_apache=false
restart_mysql=false
no_cron=false
remove_cron=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        -p) lis_path="$2"; shift 2 ;;
        -m) mode="$2"; shift 2 ;;
        -a) restart_apache=true; shift ;;
        -d) restart_mysql=true; shift ;;
        --no-cron) no_cron=true; shift ;;
        --remove-cron) remove_cron=true; shift ;;
        -h|--help)
            echo "Usage: sudo intelis-refresh [-p path] [-m mode] [-a] [-d] [--no-cron] [--remove-cron]"
            exit 0
            ;;
        *) echo "Unknown option: $1"; exit 1 ;;
    esac
done


log_action() {
    echo "$(date +'%F %T') - $1" >> "$log_file"
}

trap 'echo "Error on line $LINENO"; log_action "Error on line $LINENO"; exit 1' ERR

is_valid_application_path() {
    [ -f "$1/configs/config.production.php" ] && [ -d "$1/public" ]
}

# Cron-safe default path
if [ -z "$lis_path" ]; then
    lis_path="/var/www/vlsm"
    print info "No path specified. Using default: $lis_path"
fi

lis_path=$(to_absolute_path "$lis_path")

if ! is_valid_application_path "$lis_path"; then
    echo "Invalid LIS path: $lis_path"
    log_action "Invalid path: $lis_path"
    exit 1
fi

log_action "LIS path: $lis_path"

set_permissions "$lis_path" "$mode"
wait  # Ensure background ACL jobs are done

# Fix ownerships
for d in cache logs public/temporary public/uploads; do
    [ -d "${lis_path}/$d" ] && chown -R www-data:www-data "${lis_path}/$d"
done

restart_service apache
restart_service mysql

sudo chmod 644 /etc/mysql/mysql.conf.d/mysqld.cnf

print success "✅ LIS refresh complete."
log_action "LIS refresh complete"

cron_line="5 * * * * /usr/local/bin/intelis-refresh -p ${lis_path} -m quick > /dev/null 2>&1"
cron_marker="# added_by_intelis_refresh"
full_cron_entry="${cron_line} ${cron_marker}"

if [ "$remove_cron" = true ]; then
    current_crontab=$(mktemp)
    crontab -u root -l 2>/dev/null | grep -vF "$cron_marker" > "$current_crontab" || true
    crontab -u root "$current_crontab"
    rm -f "$current_crontab"
    print info "🗑️ Removed cron job for path: ${lis_path}"
    log_action "Cron job removed for path: ${lis_path}"
elif [ "$no_cron" = false ]; then
    if ! crontab -u root -l 2>/dev/null | grep -Fq "$cron_marker"; then
        ( crontab -u root -l 2>/dev/null || true; echo "$full_cron_entry" ) | crontab -u root -
        print success "🕒 Cron job added: $cron_line"
        log_action "Cron job added for path: ${lis_path}"
    else
        print info "🕒 Cron job already exists for path: ${lis_path} — skipping"
        log_action "Cron job already exists for path: ${lis_path}"
    fi
fi
