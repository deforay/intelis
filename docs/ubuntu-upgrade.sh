#!/bin/bash

# To use this script:
# Save the code into a file, for example, ubuntu-upgrade.sh.
# Make the script executable: chmod +x ubuntu-upgrade.sh.
# Run the script: sudo ./ubuntu-upgrade.sh.

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root."
    exit 1
fi

# Ask user for VLSM installation path
read -p "Enter the VLSM installation path [/var/www/vlsm]: " vlsm_path
vlsm_path="${vlsm_path:-/var/www/vlsm}"

# Check if VLSM folder exists
if [ ! -d "$vlsm_path" ]; then
    echo "VLSM folder does not exist at $vlsm_path. Please first run the setup script."
    exit 1
fi

# Check for MySQL
if ! command -v mysql &>/dev/null; then
    echo "MySQL is not installed. Please first run the setup script."
    exit 1
fi

# Check for Apache
if ! command -v apache2ctl &>/dev/null; then
    echo "Apache is not installed. Please first run the setup script."
    exit 1
fi

# Check for PHP
if ! command -v php &>/dev/null; then
    echo "PHP is not installed. Please first run the setup script."
    exit 1
fi

# Check for Composer
if ! command -v composer &>/dev/null; then
    echo "Composer is not installed. Please first run the setup script."
    exit 1
fi

# Proceed with the rest of the script if all checks pass
# ...

echo "All system checks passed. Continuing with the update..."

# Update Ubuntu Packages
echo "Updating Ubuntu packages..."
apt update && apt upgrade -y
apt autoremove -y

# Download New Version of VLSM from GitHub
echo "Downloading new version of VLSM from GitHub..."
wget -q -O vlsm-new-version.zip https://github.com/deforay/vlsm/archive/refs/heads/master.zip

# Backup Old VLSM Folder
echo "Backing up old VLSM folder..."
timestamp=$(date +%Y%m%d-%H%M%S)
backup_folder="$vlsm_path-backup-$timestamp"
cp -R "$vlsm_path" "$backup_folder"

# Unzip New VLSM Version
echo "Unzipping new VLSM version..."
temp_dir=$(mktemp -d)
unzip vlsm-new-version.zip -d "$temp_dir"

# Copy the unzipped content to the VLSM directory, overwriting any existing files
echo "Updating VLSM files..."
cp -RT "$temp_dir/vlsm-master/" "$vlsm_path"

# Cleanup downloaded and temporary files
rm vlsm-new-version.zip
rm -r "$temp_dir"

# Set proper permissions
chown -R www-data:www-data "$vlsm_path"

# Run Composer Update as www-data
echo "Running composer update as www-data user..."
cd "$vlsm_path"
sudo -u www-data composer update

# Run Migrations
echo "Running migrations..."
php app/system/migrate.php -yq

# Ask User to Run 'run-once' Scripts
echo "Do you want to run scripts from $vlsm_path/run-once/? (yes/no)"
read -r run_once_answer

if [[ "$run_once_answer" =~ ^[Yy][Ee][Ss]$ ]]; then
    # List the files in run-once directory
    echo "Available scripts to run:"
    files=("$vlsm_path/run-once/"*.php)
    for i in "${!files[@]}"; do
        filename=$(basename "${files[$i]}")
        echo "$((i + 1))) $filename"
    done

    # Ask which files to run
    echo "Enter the numbers of the scripts you want to run separated by commas (e.g., 1,3,6) or type 'all' to run them all."
    read -r files_to_run

    # Run selected files
    if [[ "$files_to_run" == "all" ]]; then
        for file in "${files[@]}"; do
            echo "Running $file..."
            sudo -u www-data php "$file"
        done
    else
        IFS=',' read -ra ADDR <<<"$files_to_run"
        for i in "${ADDR[@]}"; do
            # Remove any spaces in the input and correct the array index
            i=$(echo "$i" | xargs)
            file_index=$((i - 1))

            # Check if the selected index is within the range of available files
            if [[ $file_index -ge 0 ]] && [[ $file_index -lt ${#files[@]} ]]; then
                file="${files[$file_index]}"
                echo "Running $file..."
                sudo -u www-data php "$file"
            else
                echo "Invalid selection: $i. Please select a number between 1 and ${#files[@]}. Skipping."
            fi
        done
    fi
fi

echo "VLSM update complete."
