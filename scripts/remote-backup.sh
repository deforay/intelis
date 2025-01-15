#!/bin/bash

# To use this script:
# cd ~;
# wget -O remote-backup.sh https://raw.githubusercontent.com/deforay/vlsm/master/scripts/remote-backup.sh
# sudo chmod u+x remote-backup.sh;
# sudo ./remote-backup.sh;

# Step 1: Prompt for instance name and sanitize it
echo -n "Enter the lab or instance name or code: "
read instance_name

if [ -z "$instance_name" ]; then
    echo "Error: Instance name cannot be empty."
    exit 1
fi

sanitized_name=$(echo "$instance_name" | tr -s '[:space:]' '-' | tr -cd '[:alnum:]-')
instance_name_file="/var/www/.instance_name"

echo "$sanitized_name" >"$instance_name_file"

# Step 2: Update hostname
echo "Updating this machine's hostname..."
echo "$sanitized_name" | sudo tee /etc/hostname >/dev/null
sudo hostnamectl set-hostname "$sanitized_name"
echo "127.0.0.1 $sanitized_name" | sudo tee -a /etc/hosts >/dev/null

# Step 3: Install required tools
echo "Installing required tools (Rsync)..."
sudo apt update
sudo apt install -y rsync

# Step 4: Generate SSH key
ssh_key="$HOME/.ssh/id_rsa"
if [ ! -f "$ssh_key" ]; then
    echo "Generating SSH key..."
    ssh-keygen -t rsa -b 4096 -C "$sanitized_name" -N "" -f "$ssh_key"
else
    echo "SSH key already exists."
fi

# Step 5: Prompt for backup machine details
connection_success=0
for attempt in {1..3}; do
    echo -n "Enter the backup Ubuntu username: "
    read backup_user
    echo -n "Enter the backup Ubuntu hostname or IP: "
    read backup_host

    # Step 6: Test connectivity
    echo "Testing connection to backup machine (attempt $attempt)..."
    if ssh -o BatchMode=yes -o ConnectTimeout=5 "$backup_user@$backup_host" exit 2>/dev/null; then
        echo "Connection successful!"
        connection_success=1
        break
    else
        echo "Connection failed! Please check the username, hostname, or IP."
    fi
done

if [ $connection_success -ne 1 ]; then
    echo "Failed to connect after 3 attempts. Terminating setup."
    exit 1
fi

# Step 7: Copy SSH key to backup machine
echo "Copying SSH key to backup machine..."
ssh-copy-id "$backup_user@$backup_host"

# Step 8: Create backup script
backup_script="/var/www/backup.sh"
echo "Creating backup script..."
cat <<EOL | sudo tee $backup_script >/dev/null
#!/bin/bash

source_dir="/var/www/vlsm"
backup_user="$backup_user"
backup_host="$backup_host"
instance_name="$sanitized_name"
backup_dir="/backups/\${instance_name}"

# Sync local directory to backup directory
rsync -avz --delete "\$source_dir" "\$backup_user@\$backup_host:\$backup_dir"
EOL

sudo chmod +x $backup_script

# Step 9: Automate backups with cron
echo "Setting up cron jobs..."
(
    sudo crontab -l 2>/dev/null
    echo "@reboot /var/www/backup.sh"
) | sudo crontab -
(
    sudo crontab -l 2>/dev/null
    echo "0 */6 * * * /var/www/backup.sh"
) | sudo crontab -

# Final message
echo "Setup complete! Backups will run automatically every 6 hours and on reboot."
echo "You can manually run the backup script anytime with: sudo /var/www/backup.sh"
