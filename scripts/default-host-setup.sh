#!/bin/bash

# To use this script:
# cd ~;
# wget -O default-host-setup.sh https://raw.githubusercontent.com/deforay/vlsm/master/scripts/default-host-setup.sh
# sudo chmod u+x default-host-setup.sh;
# sudo ./default-host-setup.sh -p /path/to/vlsm

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "This script must be run as root. Please use sudo."
    exit 1
fi

# Initialize the LIS path
vlsm_path=""

# Function to check if the provided path is a valid LIS installation
is_valid_application_path() {
    local path=$1
    # Check for specific files or directories that should exist in the LIS installation
    if [ -f "$path/configs/config.production.php" ] && [ -d "$path/public" ]; then
        return 0 # Path is valid
    else
        return 1 # Path is not valid
    fi
}

# Parse command-line options
while getopts ":p:" opt; do
    case $opt in
    p) vlsm_path="$OPTARG" ;;
        # Ignore invalid options silently
    esac
done

# Prompt for the LIS path if not provided via -p
if [ -z "$vlsm_path" ]; then
    echo "Enter the LIS installation path [press enter to select /var/www/vlsm]: "
    read -t 60 vlsm_path

    # Check if read command timed out or no input was provided
    if [ $? -ne 0 ] || [ -z "$vlsm_path" ]; then
        vlsm_path="/var/www/vlsm"
        echo "Using default path: $vlsm_path"
    else
        echo "LIS installation path is set to ${vlsm_path}"
    fi
fi

# Convert relative path to absolute path if necessary
if [[ "$vlsm_path" != /* ]]; then
    vlsm_path="$(realpath "$vlsm_path")"
    echo "Converted to absolute path: $vlsm_path"
fi

# Check if the specified path is a valid LIS installation
if ! is_valid_application_path "$vlsm_path"; then
    echo "The specified path does not appear to be a valid LIS installation. Please check the path and try again."
    exit 1
fi

# Update /etc/apache2/sites-available/000-default.conf
vhost_file="/etc/apache2/sites-available/000-default.conf"
document_root="${vlsm_path}/public"
directory_block="<Directory ${vlsm_path}/public>\n\
    AddDefaultCharset UTF-8\n\
    Options -Indexes -MultiViews +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>"

# Replace the DocumentRoot line
if grep -q "DocumentRoot" "$vhost_file"; then
    sed -i "s|DocumentRoot .*|DocumentRoot ${document_root}|" "$vhost_file"
else
    echo "DocumentRoot ${document_root}" >>"$vhost_file"
fi

# Add or replace <Directory /> block
if grep -q "<Directory" "$vhost_file"; then
    sed -i "/<Directory/,/<\/Directory>/c\\$directory_block" "$vhost_file"
else
    echo -e "$directory_block" >>"$vhost_file"
fi

# Restart Apache to apply changes
service apache2 restart || {
    echo "Failed to restart Apache. Please check the configuration."
    exit 1
}

echo "Apache configuration updated successfully."
