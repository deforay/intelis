---
layout: default
title: Permission Denied Issue
---

# Permission Denied Issue

## Solution

To resolve permission denied errors, execute this command in your terminal:

```bash
sudo setfacl -R -m u:$USER:rwx,u:www-data:rwx /var/www;
```

This command applies recursive file access control lists to the `/var/www` directory, granting read, write, and execute permissions to both your user account and the web server user (`www-data`).
