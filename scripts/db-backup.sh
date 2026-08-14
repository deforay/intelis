#!/bin/bash

# To use this script:
# cd ~;
# wget -O db-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/db-backup.sh
# sudo chmod u+x db-backup.sh;
# sudo ./db-backup.sh;

# A failed mysqldump still produces a valid (but truncated) .gz, so the exit
# status of the pipeline has to come from mysqldump, not from gzip.
set -o pipefail

# Ensure the script is run with sudo privileges
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root or use sudo"
    exit 1
fi

# Function to log messages
log_action() {
    local message=$1
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $message" >>./db_backup.log
}

error_handling() {
    local last_cmd=$1
    local last_line=$2
    local last_error=$3
    echo "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"
    log_action "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"
    exit 1
}

# Error trap
trap 'error_handling "${BASH_COMMAND}" "$LINENO" "$?"' ERR

echo "This script will help you export selected MySQL databases."

# Resolve the systemd unit name; MariaDB hosts do not always answer to "mysql".
mysql_unit_name() {
    local unit
    for unit in mysql mysqld mariadb; do
        if systemctl list-unit-files "${unit}.service" >/dev/null 2>&1; then
            echo "$unit"
            return 0
        fi
    done
    echo "mysql"
}

# systemctl returning 0 only means the unit was handed off, so wait for the
# server to actually answer. `mysqladmin ping` reports the server alive even on
# an auth error, so this needs no credentials.
mysql_is_up() {
    mysqladmin ping --silent >/dev/null 2>&1
}

# Where the reason for a refusal to start is actually written. The journal
# almost always just says "the control process exited with an error code".
mysql_error_log_path() {
    local candidate cnf
    for cnf in /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/my.cnf /etc/my.cnf /etc/mysql/mariadb.conf.d/50-server.cnf; do
        [ -f "$cnf" ] || continue
        candidate="$(awk -F= '/^[[:space:]]*log[-_]error[[:space:]]*=/ {gsub(/[[:space:]]/, "", $2); print $2; exit}' "$cnf" 2>/dev/null || true)"
        if [ -n "$candidate" ] && [ -f "$candidate" ]; then
            echo "$candidate"
            return 0
        fi
    done

    for candidate in /var/log/mysql/error.log /var/log/mysqld.log /var/log/mariadb/mariadb.log /var/log/mysql/mysql.err; do
        if [ -f "$candidate" ]; then
            echo "$candidate"
            return 0
        fi
    done

    return 1
}

# These machines are in labs on the other side of the world, and whoever runs
# this script is the only person who can see the screen. So when MySQL will not
# start, print the evidence that distinguishes the handful of real causes (full
# disk, OOM, a config option mysqld refuses, a corrupt data directory) rather
# than just the fact that it failed.
mysql_start_diagnostics() {
    local unit=$1 err_log

    # The evidence below is worth printing, but it is eighty lines of log and
    # the person reading it usually cannot act on any of it. What they can do is
    # run the one thing that reads the same evidence, names the cause in a
    # sentence and offers to repair it. So that goes first, where it will be
    # seen, and the raw detail goes underneath for whoever it is useful to.
    echo
    echo "----------------------------------------------------------------"
    echo "MySQL would not start, so no backup can be taken yet."
    echo
    echo "To find out why and repair it, run:"
    echo
    echo "    sudo intelis fix-database"
    echo
    echo "If that command is not on this machine, run this instead:"
    echo
    echo "    sudo bash -c \"\$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/mysql-doctor.sh)\""
    echo
    echo "Then run this backup again."
    echo "----------------------------------------------------------------"
    echo
    echo "The detail below is for support. Everything above is the useful part."

    echo
    echo "--- disk space (a full disk is the most common cause) ---"
    df -h /var/lib/mysql / 2>/dev/null || true
    df -i /var/lib/mysql / 2>/dev/null || true

    echo
    echo "--- memory ---"
    free -m 2>/dev/null || true

    err_log="$(mysql_error_log_path || true)"
    if [ -n "$err_log" ]; then
        echo
        echo "--- ${err_log} (last 30 lines) ---"
        tail -n 30 "$err_log" 2>/dev/null || true
    fi

    echo
    echo "--- journalctl -u ${unit} (last 30 lines) ---"
    journalctl -u "$unit" -n 30 --no-pager 2>/dev/null || true

    echo
    echo "--- kernel: out-of-memory kills in the last 24h ---"
    journalctl -k --since "-24 hours" --no-pager 2>/dev/null |
        grep -iE 'out of memory|oom.?kill|killed process' | tail -n 10 || true

    echo
    echo "----------------------------------------------------------------"
}

# Start MySQL if it's stopped
echo "Checking MySQL status..."
MYSQL_UNIT="$(mysql_unit_name)"
if ! mysql_is_up; then
    echo "MySQL is stopped, starting MySQL (unit: ${MYSQL_UNIT})..."
    log_action "MySQL is stopped, starting MySQL (unit: ${MYSQL_UNIT})..."

    # The trap must not fire here: a failed start is handled below, with the
    # reason attached, instead of aborting on a bare "exited with status 1".
    systemctl start "$MYSQL_UNIT" >/dev/null 2>&1 || true

    for _ in $(seq 1 30); do
        mysql_is_up && break
        sleep 1
    done

    if ! mysql_is_up; then
        log_action "MySQL failed to start (unit: ${MYSQL_UNIT}); backup aborted"
        mysql_start_diagnostics "$MYSQL_UNIT"
        echo
        echo "No backup was taken. Run 'sudo intelis fix-database' first."
        exit 1
    fi

    echo "MySQL started."
    log_action "MySQL started"
fi

# Ask for MySQL root or administrative username
read -p "Enter MySQL username [root]: " USERNAME
USERNAME=${USERNAME:-root}

# Ask for MySQL password. Checking it here beats asking for it twice: a
# confirmation prompt catches a typo repeated, not a password misremembered.
while true; do
    read -sp "Enter MySQL password: " PASSWORD
    echo
    if MYSQL_PWD="$PASSWORD" mysqladmin -u "$USERNAME" ping --silent >/dev/null 2>&1; then
        break
    fi
    echo "Could not log in as '${USERNAME}' with that password. Please try again."
done

# Passed through the environment rather than on the command line, where it
# would be visible to every user on the machine via `ps`.
export MYSQL_PWD="$PASSWORD"

# List all databases
echo "Fetching list of databases..."
# The grep is wrapped so that filtering everything out is not read as a
# failure; pipefail still surfaces a genuine mysql error.
DATABASES=$(mysql -u "$USERNAME" -N -B -e "SHOW DATABASES;" |
    { grep -vxE 'information_schema|performance_schema|mysql|sys' || true; })

if [ -z "$DATABASES" ]; then
    echo "No databases found to export on this machine."
    log_action "No databases found to export"
    exit 1
fi

echo "Available databases:"
i=1
declare -A db_map
for db in $DATABASES; do
    echo "$i) $db"
    db_map[$i]=$db
    ((i++))
done

# Ask user to select databases
read -p "Enter the numbers of the databases you want to export (e.g., 1,2,3): " DB_SELECTIONS

# Parse selections and prepare to export
IFS=',' read -ra SELECTED_INDEXES <<<"$DB_SELECTIONS"
SELECTED_DBS=()
for index in "${SELECTED_INDEXES[@]}"; do
    trimmed_index=$(echo $index | xargs) # Trim whitespace
    if [[ -n ${db_map[$trimmed_index]} ]]; then
        SELECTED_DBS+=("${db_map[$trimmed_index]}")
    else
        echo "Invalid selection: $index"
    fi
done

if [ ${#SELECTED_DBS[@]} -eq 0 ]; then
    echo "No databases selected. Nothing to export."
    log_action "No databases selected; nothing exported"
    exit 1
fi

# Confirm selected databases
echo "You have selected the following databases for export:"
log_action "Selected databases for export:"
for db in "${SELECTED_DBS[@]}"; do
    echo "- $db"
    log_action "- $db"
done

# Ask for the location of export
read -p "Enter the location to export (default is ~/Desktop or ~ if Desktop does not exist): " EXPORT_LOCATION
if [ -z "$EXPORT_LOCATION" ]; then
    if [ -d "$HOME/Desktop" ]; then
        EXPORT_LOCATION="$HOME/Desktop"
    else
        EXPORT_LOCATION="$HOME"
    fi
fi
mkdir -p "$EXPORT_LOCATION" # Ensure directory exists
log_action "Export location: $EXPORT_LOCATION"

# Change to the export directory
cd "$EXPORT_LOCATION" || exit

# Function to show a spinning cursor. Returns the exit status of the job it
# was watching, so a failed dump cannot be reported as a success.
spinner() {
    local pid=$!
    local delay=0.75
    local spinstr='|/-\'
    while kill -0 $pid 2>/dev/null; do
        local temp=${spinstr#?}
        printf " [%c]  " "$spinstr"
        local spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b\b\b"
    done
    printf "    \b\b\b\b"
    wait $pid
}

# Export each selected database
FAILED_DBS=()
for db in "${SELECTED_DBS[@]}"; do
    echo "Exporting $db..."

    # Stamped once, so the name reported below is the name on disk.
    outfile="${db}-$(date +%Y-%m-%d-%H-%M-%S).sql.gz"

    (mysqldump --default-character-set=utf8mb4 -u "$USERNAME" "$db" | gzip >"$outfile") &

    # The trap must not fire here; a failed dump is reported per database.
    if spinner; then
        echo "Exported $db to ${EXPORT_LOCATION}/${outfile}"
        log_action "Exported $db to ${EXPORT_LOCATION}/${outfile}"
    else
        # The partial file is truncated and would restore silently as an
        # incomplete database, which is worse than having no backup at all.
        rm -f "$outfile"
        FAILED_DBS+=("$db")
        echo "FAILED to export $db. The incomplete file has been deleted."
        log_action "FAILED to export $db; incomplete file deleted"
    fi
done

if [ ${#FAILED_DBS[@]} -gt 0 ]; then
    echo
    echo "These databases were NOT backed up: ${FAILED_DBS[*]}"
    echo "Do not treat this run as a completed backup."
    log_action "Script completed with failures: ${FAILED_DBS[*]}"
    exit 1
fi

echo "Script completed."
log_action "Script completed"
