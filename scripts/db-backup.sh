#!/bin/bash

# To use this script:
# cd ~;
# wget -O upgrade.sh https://raw.githubusercontent.com/deforay/vlsm/master/scripts/db-backup.sh
# sudo chmod u+x db-backup.sh;
# sudo ./db-backup.sh;

# Ensure the script is run with sudo privileges
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root or use sudo"
    exit 1
fi

error_handling() {
    local last_cmd=$1
    local last_line=$2
    local last_error=$3
    echo "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"
    exit 1
}

# Error trap
trap 'error_handling "${BASH_COMMAND}" "$LINENO" "$?"' ERR

echo "This script will help you export selected MySQL databases."

# Ask for MySQL root or administrative username
read -p "Enter MySQL username [root]: " USERNAME
USERNAME=${USERNAME:-root}

# Ask for MySQL password
while true; do
    read -sp "Enter MySQL password: " PASSWORD
    echo
    read -sp "Confirm MySQL password: " PASSWORD_CONFIRM
    echo
    if [ "$PASSWORD" == "$PASSWORD_CONFIRM" ]; then
        break
    else
        echo "Passwords do not match. Please try again."
    fi
done

# List all databases
echo "Fetching list of databases..."
DATABASES=$(mysql -u "$USERNAME" -p"$PASSWORD" -e "SHOW DATABASES;" | grep -v Database | grep -v information_schema | grep -v performance_schema | grep -v mysql | grep -v sys)

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

# Confirm selected databases
echo "You have selected the following databases for export:"
for db in "${SELECTED_DBS[@]}"; do
    echo "- $db"
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

# Change to the export directory
cd "$EXPORT_LOCATION" || exit

# Function to show a spinning cursor
spinner() {
    local pid=$!
    local delay=0.1
    local spinstr='|/-\\'
    while [ "$(ps a | awk '{print $1}' | grep $pid)" ]; do
        local temp=${spinstr#?}
        printf " [%c]  " "$spinstr"
        local spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b\b\b"
    done
    printf "    \b\b\b\b"
}

# Export each selected database
for db in "${SELECTED_DBS[@]}"; do
    echo "Exporting $db..."
    (mysqldump --default-character-set=utf8mb4 -u "$USERNAME" -p"$PASSWORD" "$db" | gzip >"${db}-$(date +%Y-%m-%d-%H-%M-%S).sql.gz") &
    spinner
    echo "Exported $db to ${EXPORT_LOCATION}/${db}-$(date +%Y-%m-%d-%H-%M-%S).sql.gz"
done

# Ask if the user wants to restart the services
read -p "Restart Apache and MySQL? (y/n): " RESTART_SERVICES
if [[ "$RESTART_SERVICES" == "y" || "$RESTART_SERVICES" == "Y" ]]; then
    echo "Restarting Apache2..."
    service apache2 start
    echo "Restarting MySQL..."
    service mysql start
else
    echo "Services have not been restarted; remember to manually restart services when appropriate."
fi

echo "Script completed."
