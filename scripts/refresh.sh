#!/bin/bash

# To use this script:
# sudo wget -O /usr/local/bin/intelis-refresh https://raw.githubusercontent.com/deforay/vlsm/master/scripts/refresh.sh && sudo chmod +x /usr/local/bin/intelis-refresh
# sudo intelis-refresh

if [ "$EUID" -ne 0 ]; then
    echo "Need admin privileges. Use: sudo intelis-refresh"
    exit 1
fi

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

while getopts ":p:m:ad" opt; do
    case $opt in
        p) lis_path="$OPTARG" ;;
        m) mode="$OPTARG" ;;
        a) restart_apache=true ;;
        d) restart_mysql=true ;;
    esac
done

log_action() {
    echo "$(date +'%F %T') - $1" >> "$log_file"
}

trap 'echo "Error on line $LINENO"; log_action "Error on line $LINENO"; exit 1' ERR

to_absolute_path() {
    [[ "$1" = /* ]] && echo "$1" || echo "$(pwd)/$1"
}

is_valid_application_path() {
    [ -f "$1/configs/config.production.php" ] && [ -d "$1/public" ]
}

# Cron-safe default path
if [ -z "$lis_path" ]; then
    lis_path="/var/www/vlsm"
    echo "No path specified. Using default: $lis_path"
fi

lis_path=$(to_absolute_path "$lis_path")

if ! is_valid_application_path "$lis_path"; then
    echo "Invalid LIS path: $lis_path"
    log_action "Invalid path: $lis_path"
    exit 1
fi

log_action "LIS path: $lis_path"

set_permissions() {
    local target="$1"
    local mode="$2"
    echo "Setting permissions ($mode)..."

    case "$mode" in
        full)
            find "$target" -type d -not -path "*/.git*" -not -path "*/node_modules*" -exec setfacl -m u:"$USER":rwx,u:www-data:rwx {} \; 2>/dev/null
            find "$target" -type f -not -path "*/.git*" -not -path "*/node_modules*" -print0 | xargs -0 -P "$(nproc)" -I{} setfacl -m u:"$USER":rw,u:www-data:rw {} 2>/dev/null &
            ;;
        quick)
            find "$target" -type d -exec setfacl -m u:"$USER":rwx,u:www-data:rwx {} \; 2>/dev/null
            find "$target" -type f -name "*.php" -print0 | xargs -0 -P "$(nproc)" -I{} setfacl -m u:"$USER":rw,u:www-data:rw {} 2>/dev/null &
            ;;
        minimal)
            find "$target" -type d -exec setfacl -m u:"$USER":rwx,u:www-data:rwx {} \; 2>/dev/null
            ;;
    esac
}

set_permissions "$lis_path" "$mode"
wait  # Ensure background ACL jobs are done

# Fix ownerships
for d in cache logs public/temporary public/uploads; do
    [ -d "${lis_path}/$d" ] && chown -R www-data:www-data "${lis_path}/$d"
done

# Restart Apache or httpd
if [ "$restart_apache" = true ]; then
    if systemctl list-units --type=service | grep -q apache2; then
        echo "Restarting Apache (apache2)..."
        log_action "Restarting apache2"
        systemctl restart apache2 || { echo "Apache restart failed"; log_action "Apache restart failed"; }
    elif systemctl list-units --type=service | grep -q httpd; then
        echo "Restarting Apache (httpd)..."
        log_action "Restarting httpd"
        systemctl restart httpd || { echo "httpd restart failed"; log_action "httpd restart failed"; }
    else
        echo "Apache/httpd service not found"
        log_action "Apache/httpd not found"
    fi
fi

# Restart MySQL
if [ "$restart_mysql" = true ]; then
    echo "Restarting MySQL..."
    log_action "Restarting MySQL"
    systemctl restart mysql || { echo "MySQL restart failed"; log_action "MySQL restart failed"; }
fi

echo "✅ LIS refresh complete."
log_action "LIS refresh complete"

# Add cron entry if not already present
cron_line="0 3 * * * /usr/local/bin/intelis-refresh -p ${lis_path} -m quick > /dev/null 2>&1"
cron_marker="# added_by_intelis_refresh"

if ! crontab -l 2>/dev/null | grep -F "/usr/local/bin/intelis-refresh" | grep -Fq "$cron_marker"; then
    (crontab -l 2>/dev/null; echo "$cron_line $cron_marker") | crontab -
    echo "🕒 Cron job added: $cron_line"
    log_action "Cron job added"
else
    echo "🕒 Cron job already exists — skipping"
    log_action "Cron job already exists"
fi
