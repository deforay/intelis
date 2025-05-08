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

# Download and update shared-functions.sh only if needed
SHARED_FN_PATH="/usr/local/lib/intelis/shared-functions.sh"
SHARED_FN_URL="https://raw.githubusercontent.com/deforay/vlsm/master/scripts/shared-functions.sh"

mkdir -p "$(dirname "$SHARED_FN_PATH")"

temp_shared_fn=$(mktemp)
if wget -q -O "$temp_shared_fn" "$SHARED_FN_URL"; then
    if [ -f "$SHARED_FN_PATH" ]; then
        existing_checksum=$(md5sum "$SHARED_FN_PATH" | awk '{print $1}')
        new_checksum=$(md5sum "$temp_shared_fn" | awk '{print $1}')
        if [ "$existing_checksum" != "$new_checksum" ]; then
            cp "$temp_shared_fn" "$SHARED_FN_PATH"
            chmod +x "$SHARED_FN_PATH"
            echo "Updated shared-functions.sh."
        else
            echo "shared-functions.sh is already up-to-date."
        fi
    else
        mv "$temp_shared_fn" "$SHARED_FN_PATH"
        chmod +x "$SHARED_FN_PATH"
        echo "Downloaded shared-functions.sh."
    fi
else
    echo "Failed to download shared-functions.sh."
    if [ ! -f "$SHARED_FN_PATH" ]; then
        echo "shared-functions.sh missing. Cannot proceed."
        exit 1
    fi
fi

# Source the shared functions
source "$SHARED_FN_PATH"


# Initialize the LIS path
lis_path=""

# Parse command-line options
while getopts ":p:" opt; do
    case $opt in
    p) lis_path="$OPTARG" ;;
        # Ignore invalid options silently
    esac
done

# Prompt for the LIS path if not provided via -p
if [ -z "$lis_path" ]; then
    echo "Enter the LIS installation path [press enter to select /var/www/vlsm]: "
    read -t 60 lis_path

    # Check if read command timed out or no input was provided
    if [ $? -ne 0 ] || [ -z "$lis_path" ]; then
        lis_path="/var/www/vlsm"
        echo "Using default path: $lis_path"
    else
        echo "LIS installation path is set to ${lis_path}"
    fi
fi

# Convert VLSM path to absolute path
lis_path=$(to_absolute_path "$lis_path")

# Check if the LIS path is valid
if ! is_valid_application_path "$lis_path"; then
    print error "The specified path does not appear to be a valid LIS installation. Please check the path and try again."
    log_action "Invalid LIS path specified: $lis_path"
    exit 1
fi

# Update /etc/apache2/sites-available/000-default.conf
vhost_file="/etc/apache2/sites-available/000-default.conf"
document_root="${lis_path}/public"
directory_block="<Directory ${lis_path}/public>\n\
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
