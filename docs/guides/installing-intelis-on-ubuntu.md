# Installing InteLIS on Ubuntu 22.04 or above (only Ubuntu LTS)

**Important:** This installation works exclusively on Ubuntu 22.04 or later LTS versions.

## Installation Steps

Open your terminal and execute these commands sequentially:

```bash
# Download the script to a file, then run it. Do NOT pipe it (curl ... | bash).
cd ~ && wget -O setup.sh "https://github.com/deforay/intelis/raw/master/scripts/setup.sh?v=$(date +%s)" && sudo bash setup.sh
```

**Critical:** When prompted during installation, provide the MySQL password and STS URL with accuracy.

## InteLIS Setup Configuration

- Supply the correct STS URL during setup and choose your Testing Lab
- Following successful setup completion, access InteLIS through http://intelis/ in your browser
- The system will prompt you to finalize LIS configuration and establish an administrator account
- Upon completion, authenticate as admin at http://intelis/
