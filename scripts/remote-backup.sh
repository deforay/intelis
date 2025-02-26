#!/bin/bash

# To use this script:
# cd ~;
# wget -O remote-intelis-backup.sh https://raw.githubusercontent.com/deforay/vlsm/master/scripts/remote-intelis-backup.sh
# sudo chmod u+x remote-intelis-backup.sh;
# sudo ./remote-intelis-backup.sh;

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo"
    exit 1
fi

# Check if script has been run before
backup_script="/var/www/intelis-backup.sh"
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

# Step 1: Prompt for instance name and sanitize it
echo -n "Enter the current lab name or lab code: "
read instance_name

if [ -z "$instance_name" ]; then
    echo "Error: Instance name cannot be empty."
    exit 1
fi

# Sanitize name - trim leading/trailing spaces first, then replace internal spaces with hyphens
sanitized_name=$(echo "$instance_name" | xargs | tr -s '[:space:]' '-' | tr -cd '[:alnum:]-')
# Remove any trailing hyphens that might have been added
sanitized_name=$(echo "$sanitized_name" | sed 's/-*$//')

instance_name_file="/var/www/.instance_name"
echo "$sanitized_name" > "$instance_name_file"

# Step 2: Prompt for LIS folder path with default
default_lis_path="/var/www/vlsm"
echo -n "Enter the LIS folder path [default: $default_lis_path]: "
read lis_path
lis_path=${lis_path:-$default_lis_path}  # Use default if empty

# Verify the path exists
if [ ! -d "$lis_path" ]; then
    echo "Warning: The specified path '$lis_path' does not exist. Creating it..."
    mkdir -p "$lis_path"
fi

# Step 3: Prompt for backup machine details
echo -n "Enter the backup Ubuntu username: "
read backup_user
echo -n "Enter the backup Ubuntu hostname or IP: "
read backup_host
echo -n "Enter the SSH port (default 22): "
read backup_port
backup_port=${backup_port:-22}  # Set default port to 22 if left empty

# Step 4: Generate SSH key first (before any connection attempts)
ssh_key="$HOME/.ssh/id_rsa"
if [ ! -f "$ssh_key" ]; then
    echo "Generating SSH key..."
    ssh-keygen -t rsa -b 4096 -C "$sanitized_name" -N "" -f "$ssh_key"
else
    echo "SSH key already exists."
fi

# Step 5: Copy SSH key to backup machine (this will prompt for password once)
echo "Copying SSH key to backup machine..."
echo "You will be prompted for the password of the remote server."
if ! ssh-copy-id -p "$backup_port" "$backup_user@$backup_host"; then
    echo "Failed to connect to the backup server. Please check your credentials and try again."
    exit 1
fi

# Now we can use SSH without password for the remaining operations
echo "Testing connection to backup machine..."
if ! ssh -p "$backup_port" "$backup_user@$backup_host" "echo Connection successful"; then
    echo "Connection failed after key setup. This is unexpected. Terminating setup."
    exit 1
fi

# Check if a folder with the same name already exists
if ssh -p "$backup_port" "$backup_user@$backup_host" "[ -d /backups/$sanitized_name ]"; then
    echo "A folder with the name '$sanitized_name' already exists on the remote server."
    echo -n "Enter a different name for this machine: "
    read instance_name

    if [ -z "$instance_name" ]; then
        echo "Error: Instance name cannot be empty."
        exit 1
    fi

    # Sanitize the new name with the same rules
    sanitized_name=$(echo "$instance_name" | xargs | tr -s '[:space:]' '-' | tr -cd '[:alnum:]-')
    sanitized_name=$(echo "$sanitized_name" | sed 's/-*$//')
    echo "$sanitized_name" > "$instance_name_file"
fi

# Step 6: Update hostname
echo "Updating this machine's hostname..."
echo "$sanitized_name" | sudo tee /etc/hostname >/dev/null
sudo hostnamectl set-hostname "$sanitized_name"
# Check if hostname already exists in /etc/hosts before adding
if ! grep -q "127.0.0.1 $sanitized_name" /etc/hosts; then
    echo "127.0.0.1 $sanitized_name" | sudo tee -a /etc/hosts >/dev/null
fi

# Step 7: Install required tools
echo "Installing required tools (Rsync)..."
sudo apt update
sudo apt install -y rsync

# Step 8: Create remote backup directory if it doesn't exist
echo "Creating backup directory on remote server..."
ssh -p "$backup_port" "$backup_user@$backup_host" "mkdir -p /backups/$sanitized_name"

# Step 9: Create backup script
echo "Creating backup script..."
cat <<EOL | sudo tee $backup_script >/dev/null
#!/bin/bash

source_dir="$lis_path"
backup_user="$backup_user"
backup_host="$backup_host"
backup_port="$backup_port"
instance_name="$sanitized_name"
backup_dir="/backups/\${instance_name}"

# Sync local directory to backup directory
rsync -avz -e "ssh -p \${backup_port}" --delete "\$source_dir" "\$backup_user@\$backup_host:\$backup_dir"
EOL

sudo chmod +x $backup_script

# Step 10: Automate backups with cron
echo "Setting up cron jobs..."
# First remove any existing entries for our backup script
existing_crontab=$(sudo crontab -l 2>/dev/null | grep -v "/var/www/intelis-backup.sh")

# Create a temporary file for the new crontab
temp_crontab=$(mktemp)

# If there was an existing crontab, write it to our temp file
if [ -n "$existing_crontab" ]; then
    echo "$existing_crontab" > "$temp_crontab"
fi

# Add our new entries
echo "@reboot /var/www/intelis-backup.sh" >> "$temp_crontab"
echo "0 */6 * * * /var/www/intelis-backup.sh" >> "$temp_crontab"

# Install the new crontab
sudo crontab "$temp_crontab"

# Clean up
rm "$temp_crontab"

# Final message
echo "Setup complete! Backups will run automatically every 6 hours and on reboot."
echo "You can manually run the backup script anytime with: sudo /var/www/intelis-backup.sh"
echo "The LIS folder being backed up is: $lis_path"
echo "Remote backup location: /backups/$sanitized_name"
