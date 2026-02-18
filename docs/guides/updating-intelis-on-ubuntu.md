# Updating InteLIS on Ubuntu 22.04 or above (only Ubuntu LTS)

**Note:** This will work on Ubuntu 22.04 or above (ONLY LTS).

## Update Steps

Open a terminal and execute the following commands sequentially:

```bash
sudo wget -O /usr/local/bin/intelis-update https://github.com/deforay/intelis/raw/master/scripts/upgrade.sh;

sudo chmod +x /usr/local/bin/intelis-update;

sudo intelis-update
```

## Important Requirements

When the update process runs, you will be prompted to provide two critical pieces of information:

1. MySQL password
2. STS URL

Enter the MySQL password and STS URL correctly when prompted. Incorrect entries may cause the update to fail.
