# Migrating From One Ubuntu Machine to Another

## Backup Database on Old System

Open a terminal and execute these commands sequentially:

```bash
cd ~;
sudo -s;
wget -O db-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/db-backup.sh
sudo chmod u+x db-backup.sh;
sudo ./db-backup.sh;
```

During execution:
- Provide MySQL username and password when requested
- Select which database(s) to export
- Allow the script to complete fully
- Transfer the resulting database file(s) to removable media

## Restore Database and Install VLSM on New System

**System requirement:** Ubuntu 22.04 LTS or newer

Transfer the backup file from removable media to the Desktop folder, then run these terminal commands in sequence:

```bash
cd ~/Desktop;
sudo -s;
rm -f *.sql
gzip -d vlsm-*.sql.gz && mv vlsm-*.sql vlsm.sql
wget -O setup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh
chmod +x setup.sh;
./setup.sh --db vlsm.sql
rm setup.sh;
exit; exit;
```

When prompted, enter MySQL credentials and STS URL accurately.

## VLSM Setup

- Edit the production configuration: `sudo gedit /var/www/vlsm/configs/config.production.php`
- Update MySQL details and other settings
- Access http://vlsm/ in your browser
- Create a new administrator account
- Log in with administrator credentials
- In the popup, configure instance details and select **LIS with Remote Ordering Enabled**
- Choose your laboratory from the available options
- Initiate Force Sync and monitor until completion
