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

# Fetch to a temporary file and swap it in only once it looks like the real
# thing. `wget -O` truncates the destination before the transfer starts, so
# downloading straight onto the installed copy destroys it the moment the
# network hiccups — and a zero-byte file sources without complaint, leaving
# every function undefined while the script walks on regardless. upgrade.sh
# learned this the hard way; the same hole was still open here.
#
# The transfer is bounded, too. Unbounded, wget retries 20 times with a 900s
# read timeout, and this script is normally run from inside a background job
# whose output is discarded — so a stalled link is minutes of complete silence
# that looks exactly like a hang.
fetch_shared_fn() {
    local dest="$1" url="$2" tmp
    tmp="$(mktemp "${dest}.XXXXXX" 2>/dev/null)" || return 1
    if wget -q --timeout=15 --tries=2 -O "$tmp" "$url" &&
        [ -s "$tmp" ] && grep -q '^set_permissions()' "$tmp"; then
        mv -f "$tmp" "$dest"
        return 0
    fi
    rm -f "$tmp"
    return 1
}

# The caller may have fetched this very file seconds ago: upgrade.sh downloads
# it on line one of every run and then calls this script. Repeating the round
# trip there buys nothing and can only cost time.
if [ "${INTELIS_SHARED_FN_FRESH:-}" = "1" ] && [ -s "$SHARED_FN_PATH" ]; then
    :
elif fetch_shared_fn "$SHARED_FN_PATH" "$SHARED_FN_URL"; then
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

# Show help if requested
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    echo "Usage: sudo intelis-refresh [-p path] [-m mode] [-a] [-d]"
    echo "  -p : LIS install path (default: /var/www/intelis)"
    echo "  -m : Mode (full, quick, minimal, deep)"
    echo "       deep also sweeps var/audit-trail and backups, which full skips"
    echo "       because nothing but the application itself ever writes there."
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
    lis_path="/var/www/intelis"
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

# Fix ownerships.
#
# These were "cache logs public/temporary public/uploads" — paths from before the
# runtime tree moved under var/. Nothing lives at <install>/cache or
# <install>/logs any more, so `[ -d ]` was false every time and the chown
# silently never ran on the directories that needed it, while backups and
# var/temporary were not listed at all. set_permissions covers the tree with
# ACLs, so the app kept working and the dead loop went unnoticed; what showed up
# was root-owned var/logs and backups in the post-upgrade check.
#
# Kept in step with the path constants in bootstrap.php.
#
# Split by whether the directory is bounded, for the same reason backups below
# is not recursive. var/logs and the two temporary dirs are small and rotated,
# so sweeping them costs nothing and repairs anything the app left behind.
# var/cache and public/uploads are not: a busy instance shards tens of thousands
# of cache entries, and uploads grow with the lab's own data. Walking those on
# every run — including the hourly cron this script installs — was the longest
# thing either one did, and it was buying nothing. Unlinking a cache entry needs
# write access to the DIRECTORY holding it, not ownership of the file, and
# set_permissions has already swept every directory under var/cache and given it
# a default ACL. var/track-api is the same case: millions of request and response
# bodies on a busy STS, all written by the application itself. `-m deep` still sweeps the contents for the one-off case where
# an existing tree really does have the wrong owner.
for d in var/logs var/temporary public/temporary; do
    if [ -d "${lis_path}/$d" ]; then
        chown -R www-data:www-data "${lis_path}/$d"
    fi
done

for d in var/cache var/track-api public/uploads; do
    if [ -d "${lis_path}/$d" ]; then
        if [ "$mode" = "deep" ]; then
            chown -R www-data:www-data "${lis_path}/$d"
        else
            chown www-data:www-data "${lis_path}/$d"
        fi
    fi
done

# backups is deliberately NOT recursive.
#
# It grows without limit and set_permissions prunes it for exactly that reason,
# so a `chown -R` here undid the pruning: the whole of it was walked on every
# upgrade and on every hourly cron pass, which on a mature instance was the
# longest single thing either one did. Nothing but the backup task ever writes
# there, and set_permissions gives the directory a default ACL, so owning the
# directory itself is enough for everything written from here on. `-m deep`
# still sweeps the contents, for the one-off case where an existing tree really
# does have the wrong owner.
if [ -d "${lis_path}/backups" ]; then
    if [ "$mode" = "deep" ]; then
        chown -R www-data:www-data "${lis_path}/backups"
    else
        chown www-data:www-data "${lis_path}/backups"
    fi
fi

# Restarts happen only when asked for. -a and -d have always been parsed into
# restart_apache/restart_mysql and then never read, so both services were
# restarted on every run — including the hourly `-m quick` cron this script
# installs itself, which made every instance in the fleet bounce Apache and
# MySQL at five past every hour. A permissions sweep has no reason to interrupt
# either one, and callers that do want it already pass the flags.
if [ "$restart_apache" = true ]; then
    restart_service apache
fi

if [ "$restart_mysql" = true ]; then
    restart_service mysql
fi

# Already running as root, and absent on MariaDB or a non-Debian layout — where
# an unguarded chmod fails and the ERR trap turns the whole run into an error.
if [ -f /etc/mysql/mysql.conf.d/mysqld.cnf ]; then
    chmod 644 /etc/mysql/mysql.conf.d/mysqld.cnf
fi

print success "✅ LIS refresh complete."
log_action "LIS refresh complete"

# The hourly pass runs under flock -n so a run that is still going when the next
# hour comes round makes the new one exit instead of starting a second walk
# beside it. On slow disks a pass could take longer than an hour, and two were
# found running at once, each slowing the other down.
#
# It stays hourly. The scheduled tasks run from root's crontab, so logs and
# cache entries keep getting created as root, and this pass is what hands them
# back to www-data. With the heavy trees pruned, a quick pass only walks the
# code and the small runtime directories, so running it hourly costs little.
refresh_bin="/usr/local/bin/intelis-refresh"
refresh_lock="/run/intelis-refresh.lock"
flock_prefix=""
command -v flock >/dev/null 2>&1 && flock_prefix="flock -n ${refresh_lock} "

cron_line="5 * * * * ${flock_prefix}${refresh_bin} -p ${lis_path} -m quick > /dev/null 2>&1"
cron_marker="# added_by_intelis_refresh"
full_cron_entry="${cron_line} ${cron_marker}"

# add_flock_to_refresh_cron — put flock in front of cron lines installed before
# it was added, keeping each line's own schedule and path.
#
# An upgrade downloads this script fresh and runs it, which is how every
# existing install picks this up with nobody logging in. root's crontab also
# holds the application's cron.sh line, so a crontab that could not be read in
# full, or a rewrite that changed the number of lines, is left alone.
add_flock_to_refresh_cron() {
    [ -n "$flock_prefix" ] || return 0

    local current rewritten
    current=$(crontab -u root -l 2>/dev/null) || return 0
    [ -n "$current" ] || return 0

    # Only lines carrying the marker and not already locked.
    printf '%s\n' "$current" | grep -F "$cron_marker" | grep -vqF "flock " || return 0

    rewritten=$(printf '%s\n' "$current" | awk -v m="$cron_marker" -v bin="$refresh_bin" -v pre="$flock_prefix" '
        index($0, m) && !index($0, "flock ") {
            i = index($0, bin)
            if (i) $0 = substr($0, 1, i - 1) pre substr($0, i)
        }
        { print }')

    if [ -z "$rewritten" ] ||
        [ "$(printf '%s\n' "$rewritten" | wc -l)" -ne "$(printf '%s\n' "$current" | wc -l)" ]; then
        log_action "Refresh cron line not rewritten: rewrite did not match the original crontab"
        return 0
    fi

    if printf '%s\n' "$rewritten" | crontab -u root -; then
        print success "🕒 Hourly refresh now runs under flock, so runs cannot overlap"
        log_action "Refresh cron line rewritten to run under flock"
    fi
    return 0
}

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
        add_flock_to_refresh_cron
        print info "🕒 Cron job already exists for path: ${lis_path} — skipping"
        log_action "Cron job already exists for path: ${lis_path}"
    fi
fi
