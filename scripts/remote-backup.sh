#!/bin/bash

# To use this script:
# cd ~;
# wget -O remote-intelis-backup.sh https://raw.githubusercontent.com/deforay/vlsm/master/scripts/remote-backup.sh
# sudo chmod u+x remote-intelis-backup.sh;
# sudo ./remote-intelis-backup.sh;

# Check if running as root - fixed to be compatible with sh
if [ "$(id -u)" -ne 0 ]; then
    echo "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo"
    exit 1
fi

# Check if script has been run before
backup_script="/usr/local/bin/intelis-backup.sh"
if [ -f "$backup_script" ]; then
    echo "Backup script already exists at $backup_script."
    echo "This script appears to have been run before."
    echo -n "Do you want to continue and reconfigure? (y/n): "
    read answer
    if [[ ! "$answer" =~ ^[Yy]$ ]]; then
        echo "Operation cancelled."
        exit 0
    fi
fi


# Define a unified print function that colors the entire message
# Using printf instead of echo -e for better compatibility with sh
print() {
    local type=$1
    local message=$2

    case $type in
    error)
        printf "\033[0;31mError: %s\033[0m\n" "$message"
        ;;
    success)
        printf "\033[0;32mSuccess: %s\033[0m\n" "$message"
        ;;
    warning)
        printf "\033[0;33mWarning: %s\033[0m\n" "$message"
        ;;
    info)
        # Changed from blue (\033[0;34m) to teal/turquoise (\033[0;36m)
        printf "\033[0;36mInfo: %s\033[0m\n" "$message"
        ;;
    debug)
        # Using a lighter cyan color for debug messages
        printf "\033[1;36mDebug: %s\033[0m\n" "$message"
        ;;
    header)
        # Changed from blue to a brighter cyan/teal
        printf "\033[1;36m==== %s ====\033[0m\n" "$message"
        ;;
    *)
        printf "%s\n" "$message"
        ;;
    esac
}

# Function to check if the provided path is a valid application installation
is_valid_application_path() {
    local path=$1
    # Check for a specific file or directory that should exist in the application installation
    if [ -f "$path/configs/config.production.php" ] && [ -d "$path/public" ]; then
        return 0 # Path is valid
    else
        return 1 # Path is not valid
    fi
}

# Step 1: Prompt for instance name and sanitize it
print header "Setting up instance name"
echo -n "Enter the current lab name or lab code: "
read instance_name

if [ -z "$instance_name" ]; then
    print error "Instance name cannot be empty."
    exit 1
fi

# Sanitize name - trim leading/trailing spaces first, then replace internal spaces with hyphens
sanitized_name=$(echo "$instance_name" | xargs | tr -s '[:space:]' '-' | tr -cd '[:alnum:]-')
# Remove any trailing hyphens that might have been added
sanitized_name=$(echo "$sanitized_name" | sed 's/-*$//')

instance_name_file="/var/www/.instance_name"
echo "$sanitized_name" > "$instance_name_file"
print success "Instance name set to: $sanitized_name"

# Step 2: Prompt for LIS folder path with default
print header "Setting up LIS folder path"
default_lis_path="/var/www/vlsm"
echo -n "Enter the LIS folder path [default: $default_lis_path]: "
read lis_path
lis_path=${lis_path:-$default_lis_path}  # Use default if empty

# Convert relative path to absolute path if necessary
if [[ "$lis_path" != /* ]]; then
    lis_path="$(realpath "$lis_path")"
    print info "Converted to absolute path: $lis_path"
fi

# Verify the path exists
if [ ! -d "$lis_path" ]; then
    print error "The specified path '$lis_path' does not exist. Please provide a valid path."
    exit 1
fi

# Check if the path is a valid application installation
if ! is_valid_application_path "$lis_path"; then
    print error "The specified path '$lis_path' does not appear to be a valid LIS installation."
    exit 1
fi

# Also check if the required subdirectories exist
required_dirs=("$lis_path/backups" "$lis_path/audit-trail" "$lis_path/public/uploads")
missing_dirs=()

for dir in "${required_dirs[@]}"; do
    if [ ! -d "$dir" ]; then
        missing_dirs+=("$dir")
    fi
done

if [ ${#missing_dirs[@]} -gt 0 ]; then
    print error "The following required directories do not exist:"
    for dir in "${missing_dirs[@]}"; do
        echo "  - $dir"
    done
    print error "Please ensure these directories exist before running this script."
    exit 1
fi
print success "Valid LIS installation found at: $lis_path"

# Step 3: Prompt for backup machine details
print header "Setting up backup destination"
echo -n "Enter the backup Ubuntu username: "
read backup_user
echo -n "Enter the backup Ubuntu hostname or IP: "
read backup_host
echo -n "Enter the SSH port (default 22): "
read backup_port
backup_port=${backup_port:-22}  # Set default port to 22 if left empty

# Step 4: Generate SSH key first (before any connection attempts)
print header "Setting up SSH keys"
ssh_key="$HOME/.ssh/id_rsa"
if [ ! -f "$ssh_key" ]; then
    print info "Generating SSH key..."
    ssh-keygen -t rsa -b 4096 -C "$sanitized_name" -N "" -f "$ssh_key"
else
    print info "SSH key already exists."
fi

# Step 5: Copy SSH key to backup machine (this will prompt for password once)
print info "Copying SSH key to backup machine..."
print info "You will be prompted for the password of the remote server."
if ! ssh-copy-id -p "$backup_port" "$backup_user@$backup_host"; then
    print error "Failed to connect to the backup server. Please check your credentials and try again."
    exit 1
fi

# Now we can use SSH without password for the remaining operations
print info "Testing connection to backup machine..."
if ! ssh -p "$backup_port" "$backup_user@$backup_host" "echo Connection successful"; then
    print error "Connection failed after key setup. This is unexpected. Terminating setup."
    exit 1
fi
print success "SSH key setup successful"

# Check if a folder with the same name already exists
if ssh -p "$backup_port" "$backup_user@$backup_host" "[ -d ~/backups/$sanitized_name ]"; then
    print warning "A folder with the name '$sanitized_name' already exists on the remote server."
    echo -n "Enter a different name for this machine: "
    read instance_name

    if [ -z "$instance_name" ]; then
        print error "Instance name cannot be empty."
        exit 1
    fi

    # Sanitize the new name with the same rules
    sanitized_name=$(echo "$instance_name" | xargs | tr -s '[:space:]' '-' | tr -cd '[:alnum:]-')
    sanitized_name=$(echo "$sanitized_name" | sed 's/-*$//')
    echo "$sanitized_name" > "$instance_name_file"
    print info "Updated instance name to: $sanitized_name"
fi

# Step 6: Update hostname
print header "Updating hostname"
print info "Setting hostname to: $sanitized_name"
echo "$sanitized_name" | sudo tee /etc/hostname >/dev/null
sudo hostnamectl set-hostname "$sanitized_name"
# Check if hostname already exists in /etc/hosts before adding
if ! grep -q "127.0.0.1 $sanitized_name" /etc/hosts; then
    echo "127.0.0.1 $sanitized_name" | sudo tee -a /etc/hosts >/dev/null
fi
print success "Hostname updated successfully"

# Step 7: Install required tools
print header "Installing required tools"
print info "Updating package list and installing rsync..."
sudo apt update
sudo apt install -y rsync
print success "Required tools installed"

# Step 8: Create remote backup directory if it doesn't exist
print header "Setting up remote backup location"
print info "Creating backup directory on remote server..."
ssh -p "$backup_port" "$backup_user@$backup_host" "mkdir -p ~/backups/$sanitized_name"
print success "Remote backup directory created: ~/backups/$sanitized_name"

# Step 9: Create backup script
print header "Creating backup script"
print info "Writing backup script to $backup_script"
cat <<EOL | sudo tee $backup_script >/dev/null
#!/bin/bash

# Intelis backup script - created $(date)
# This script backs up critical data from $lis_path to $backup_host

# Define print function for colored output
print() {
    local type=\$1
    local message=\$2

    case \$type in
    error)
        printf "\033[0;31mError: %s\033[0m\n" "\$message"
        ;;
    success)
        printf "\033[0;32mSuccess: %s\033[0m\n" "\$message"
        ;;
    warning)
        printf "\033[0;33mWarning: %s\033[0m\n" "\$message"
        ;;
    info)
        printf "\033[0;36mInfo: %s\033[0m\n" "\$message"
        ;;
    *)
        printf "%s\n" "\$message"
        ;;
    esac
}

source_dir="$lis_path"
backup_user="$backup_user"
backup_host="$backup_host"
backup_port="$backup_port"
instance_name="$sanitized_name"
backup_dir="~/backups/\${instance_name}"

print info "Starting backup at \$(date)"
print info "Source: \${source_dir}"
print info "Destination: \${backup_user}@\${backup_host}:\${backup_dir}"

# Check if source directories exist
if [ ! -d "\${source_dir}/backups" ] || [ ! -d "\${source_dir}/audit-trail" ] || [ ! -d "\${source_dir}/public/uploads" ]; then
    print error "One or more required source directories do not exist. Backup aborted."
    exit 1
fi

# Create backup directories if they don't exist
print info "Ensuring remote directories exist..."
ssh -p "\${backup_port}" "\${backup_user}@\${backup_host}" "mkdir -p \${backup_dir}/backups \${backup_dir}/audit-trail \${backup_dir}/public/uploads"

# Sync only the required directories to backup location
print info "Backing up backups directory..."
rsync -avz -e "ssh -p \${backup_port}" --delete "\${source_dir}/backups/" "\${backup_user}@\${backup_host}:\${backup_dir}/backups/"

# Clear the backups directory after successful copy
if [ $? -eq 0 ]; then
    print info "Clearing source backups directory to free up space..."
    # Keep a small number of most recent files/folders (adjust the number as needed)
    cd "\${source_dir}/backups/" && ls -tp | grep -v '/
rsync -avz -e "ssh -p \${backup_port}" --delete "\${source_dir}/audit-trail/" "\${backup_user}@\${backup_host}:\${backup_dir}/audit-trail/"

print info "Backing up uploads directory..."
rsync -avz -e "ssh -p \${backup_port}" --delete "\${source_dir}/public/uploads/" "\${backup_user}@\${backup_host}:\${backup_dir}/public/uploads/"

print success "Backup completed at \$(date)"
EOL

sudo chmod +x $backup_script
print success "Backup script created successfully"

# Step 10: Automate backups with cron
print header "Setting up scheduled backups"
print info "Setting up cron jobs..."
# First remove any existing entries for our backup script
existing_crontab=$(sudo crontab -l 2>/dev/null | grep -v "/usr/local/bin/intelis-backup.sh")

# Create a temporary file for the new crontab
temp_crontab=$(mktemp)

# If there was an existing crontab, write it to our temp file
if [ -n "$existing_crontab" ]; then
    echo "$existing_crontab" > "$temp_crontab"
fi

# Add our new entries
echo "@reboot /usr/local/bin/intelis-backup.sh" >> "$temp_crontab"
echo "0 */6 * * * /usr/local/bin/intelis-backup.sh" >> "$temp_crontab"

# Install the new crontab
sudo crontab "$temp_crontab"

# Clean up
rm "$temp_crontab"
print success "Scheduled backups configured (every 6 hours and at reboot)"

# Final message
print header "Setup Complete"
print success "Backup system has been successfully configured!"
print info "You can manually run the backup script anytime with: sudo /usr/local/bin/intelis-backup.sh"
print info "The LIS folder being backed up is: $lis_path"
print info "Specific directories backed up:"
echo "  - $lis_path/backups"
echo "  - $lis_path/audit-trail"
echo "  - $lis_path/public/uploads"
print info "Remote backup location: ~/backups/$sanitized_name"
 | tail -n +31 | xargs -I {} rm -- {} 2>/dev/null
    # Alternative: remove all files older than 7 days
    # find "\${source_dir}/backups/" -type f -mtime +7 -exec rm {} \; 2>/dev/null
    print success "Cleared older backup files"
else
    print warning "Backup of backups directory had issues. Not clearing source directory."
fi

print info "Backing up audit-trail directory..."
rsync -avz -e "ssh -p \${backup_port}" --delete "\${source_dir}/audit-trail/" "\${backup_user}@\${backup_host}:\${backup_dir}/audit-trail/"

print info "Backing up uploads directory..."
rsync -avz -e "ssh -p \${backup_port}" --delete "\${source_dir}/public/uploads/" "\${backup_user}@\${backup_host}:\${backup_dir}/public/uploads/"

print success "Backup completed at \$(date)"
EOL

sudo chmod +x $backup_script
print success "Backup script created successfully"

# Step 10: Automate backups with cron
print header "Setting up scheduled backups"
print info "Setting up cron jobs..."
# First remove any existing entries for our backup script
existing_crontab=$(sudo crontab -l 2>/dev/null | grep -v "/usr/local/bin/intelis-backup.sh")

# Create a temporary file for the new crontab
temp_crontab=$(mktemp)

# If there was an existing crontab, write it to our temp file
if [ -n "$existing_crontab" ]; then
    echo "$existing_crontab" > "$temp_crontab"
fi

# Add our new entries
echo "@reboot /usr/local/bin/intelis-backup.sh" >> "$temp_crontab"
echo "0 */6 * * * /usr/local/bin/intelis-backup.sh" >> "$temp_crontab"

# Install the new crontab
sudo crontab "$temp_crontab"

# Clean up
rm "$temp_crontab"
print success "Scheduled backups configured (every 6 hours and at reboot)"

# Final message
print header "Setup Complete"
print success "Backup system has been successfully configured!"
print info "You can manually run the backup script anytime with: sudo /usr/local/bin/intelis-backup.sh"
print info "The LIS folder being backed up is: $lis_path"
print info "Specific directories backed up:"
echo "  - $lis_path/backups"
echo "  - $lis_path/audit-trail"
echo "  - $lis_path/public/uploads"
print info "Remote backup location: ~/backups/$sanitized_name"
