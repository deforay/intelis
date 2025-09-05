#!/bin/bash

# To use this script:
# cd ~;
# wget -O intelis-host-setup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/default-host-setup.sh
# sudo chmod u+x intelis-host-setup.sh;
# sudo ./intelis-host-setup.sh -p /path/to/vlsm

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Error: This script must be run as root. Please use sudo."
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


# Set up log file
log_file="/tmp/intelis-host-setup-$(date +'%Y%m%d-%H%M%S').log"
log_action "Starting default host setup"

# Prepare the system
prepare_system

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
    print info "Enter the LIS installation path [press enter to select /var/www/vlsm]: "
    read -t 60 lis_path

    # Check if read command timed out or no input was provided
    if [ $? -ne 0 ] || [ -z "$lis_path" ]; then
        lis_path="/var/www/vlsm"
        print info "Using default path: $lis_path"
    else
        print info "LIS installation path is set to ${lis_path}"
    fi
fi

# Convert to absolute path using function from shared_functions.sh
lis_path=$(to_absolute_path "$lis_path")
log_action "Using LIS path: $lis_path"

# Check if the LIS path is valid using function from shared_functions.sh
if ! is_valid_application_path "$lis_path"; then
    print error "The specified path does not appear to be a valid LIS installation. Please check the path and try again."
    log_action "Invalid LIS path specified: $lis_path"
    exit 1
fi

# Update /etc/apache2/sites-available/000-default.conf
print header "Configuring Apache Virtual Host"
vhost_file="/etc/apache2/sites-available/000-default.conf"
document_root="${lis_path}/public"
directory_block="<Directory ${lis_path}/public>\n\
    AddDefaultCharset UTF-8\n\
    Options -Indexes -MultiViews +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>"

# Create backup of the original file
backup_timestamp=$(date +%Y%m%d%H%M%S)
if [ -f "$vhost_file" ]; then
    cp "$vhost_file" "${vhost_file}.bak.${backup_timestamp}"
    print info "Backed up original Apache configuration to ${vhost_file}.bak.${backup_timestamp}"
    log_action "Backed up original Apache configuration"
fi

# Replace the DocumentRoot line
if grep -q "DocumentRoot" "$vhost_file"; then
    sed -i "s|DocumentRoot .*|DocumentRoot ${document_root}|" "$vhost_file"
    print info "Updated DocumentRoot to ${document_root}"
else
    echo "DocumentRoot ${document_root}" >> "$vhost_file"
    print info "Added DocumentRoot ${document_root}"
fi

# Add or replace <Directory /> block
# Handle the Directory block more carefully
print info "Updating Directory configuration..."

# Check if there are any existing Directory blocks
if grep -q "<Directory" "$vhost_file"; then
    # Create a temporary file
    tmp_vhost=$(mktemp)

    # Process the file line by line
    in_directory_block=0
    while IFS= read -r line; do
        # Check if we're entering a Directory block
        if [[ $line =~ \<Directory && $in_directory_block -eq 0 ]]; then
            in_directory_block=1
            continue
        fi

        # Check if we're exiting a Directory block
        if [[ $line =~ \<\/Directory\> && $in_directory_block -eq 1 ]]; then
            in_directory_block=0
            continue
        fi

        # If we're not in a Directory block, write the line to the temp file
        if [ $in_directory_block -eq 0 ]; then
            echo "$line" >> "$tmp_vhost"
        fi
    done < "$vhost_file"

    # Now add our Directory block to the end of the file
    echo -e "$directory_block" >> "$tmp_vhost"

    # Replace the original file with our modified version
    mv "$tmp_vhost" "$vhost_file"
    print info "Removed existing Directory blocks and added new configuration"
else
    # Just append our Directory block if none exists
    echo -e "$directory_block" >> "$vhost_file"
    print info "Added Directory block configuration"
fi

# Check if a2enmod is available (Apache utility)
if command -v a2enmod &>/dev/null; then
    print info "Enabling required Apache modules..."

    # Enable essential Apache modules
    required_modules=("rewrite" "headers" "deflate")

    for module in "${required_modules[@]}"; do
        if ! a2query -m "$module" >/dev/null 2>&1; then
            a2enmod "$module"
            print info "Enabled Apache module: $module"
            log_action "Enabled Apache module: $module"
        else
            print info "Apache module already enabled: $module"
        fi
    done
fi

# Restart Apache to apply changes using function from shared_functions.sh
if restart_service apache; then
    print success "Apache configuration updated successfully"
    log_action "Apache configuration updated successfully"
else
    print error "Failed to restart Apache. Please check the configuration."
    log_action "Failed to restart Apache"

    # Restore backup if restart failed
    if [ -f "${vhost_file}.bak.${backup_timestamp}" ]; then
        mv "${vhost_file}.bak.${backup_timestamp}" "$vhost_file"
        print warning "Restored original Apache configuration"
        log_action "Restored original Apache configuration"

        # Try to restart Apache again
        restart_service apache
    fi

    exit 1
fi

# Set proper permissions on LIS path
print header "Setting Permissions"
set_permissions "$lis_path" "quick"
print success "Permissions set for $lis_path"
log_action "Permissions set for $lis_path"

print header "Setup Complete"
print success "Default host configuration completed successfully"
log_action "Default host configuration completed successfully"

# Final message
print info "Your LIS application is now accessible at: http://$(hostname -I | awk '{print $1}' | tr -d ' ')/"
print info "Log file: $log_file"
