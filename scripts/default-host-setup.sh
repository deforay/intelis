#!/bin/bash

# To use this script:
# cd ~;
# wget -O default-host-setup.sh https://raw.githubusercontent.com/deforay/vlsm/master/scripts/default-host-setup.sh
# sudo chmod u+x default-host-setup.sh;
# sudo ./default-host-setup.sh;

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "This script must be run as root. Please use sudo."
    exit 1
fi

# Prompt for the VLSM path
echo "Enter the VLSM installation path [press enter to select /var/www/vlsm]: "
read -t 60 vlsm_path

# Check if read command timed out or no input was provided
if [ $? -ne 0 ] || [ -z "$vlsm_path" ]; then
    vlsm_path="/var/www/vlsm"
    echo "Using default path: $vlsm_path"
else
    echo "VLSM installation path is set to ${vlsm_path}."
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
