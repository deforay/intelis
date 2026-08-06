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
- After setup completes, open <http://intelis/> in your browser
- InteLIS then prompts you to finalize the configuration and create an administrator account
- Log in as that administrator at <http://intelis/>
