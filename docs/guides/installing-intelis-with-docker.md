# Installing InteLIS with Docker

**Prerequisites:** [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/install/) must be installed on your system.

Docker is the simplest way to get InteLIS running. The traditional setup (`setup.sh`) requires manually installing and configuring PHP, Apache, MySQL, Composer, virtual hosts, cron jobs, file permissions, and MySQL tuning — Docker handles all of this automatically in a single command.

## Installation Steps

### 1. Clone the Repository

```bash
git clone https://github.com/deforay/intelis.git
cd intelis
```

### 2. Configure Environment Variables

Copy the example environment file and edit it:

```bash
cp .env.example .env
```

Edit `.env` and set at minimum the MySQL root password:

```ini
DOMAIN=intelis
APACHE_PORT=80

MYSQL_ROOT_PASSWORD=your_secure_password
MYSQL_PORT=3306
MYSQL_DATABASE=vlsm
```

### 3. Start the Containers

```bash
docker compose up -d
```

This starts two services:

- **intelis** — PHP 8.4 / Apache application server
- **intelis-db** — MySQL 8.4 database server

The entrypoint script automatically handles everything that `setup.sh` does manually:

- Configures Apache virtual host and `/etc/hosts`
- Initializes the main database from `sql/init.sql`
- Creates and configures the interfacing database (if enabled)
- Generates `config.production.php` with the correct database credentials
- Installs Composer dependencies with optimized autoloading
- Runs database migrations (`composer post-update`) then generates Audit Trail v2
  triggers (`composer db:repair`, which calls `bin/setup/regenerate-audit-triggers.php
  --apply install` + `bin/reset-seq.php`)
- Executes any run-once scripts
- Starts the cron service for background tasks
- Starts Apache in the foreground

### 4. Access InteLIS

Once the containers are running, open your browser and navigate to:

```
http://localhost/
```

The system will prompt you to finalize LIS configuration and create an administrator account.

## Environment Variables Reference

| Variable               | Default          | Description                          |
| ---------------------- | ---------------- | ------------------------------------ |
| `DOMAIN`               | `intelis`        | Application domain name              |
| `APACHE_PORT`          | `80`             | Host port for the web server         |
| `MYSQL_ROOT_PASSWORD`  | `root_password`  | MySQL root password                  |
| `MYSQL_PORT`           | `3306`           | Host port for MySQL                  |
| `MYSQL_DATABASE`       | `vlsm`           | Main database name                   |
| `INTERFACING_ENABLED`  | `true`           | Enable interfacing database          |
| `INTERFACE_DB_HOST`    | `intelis-db`     | Interfacing DB host                  |
| `INTERFACE_DB_PORT`    | `3306`           | Interfacing DB port                  |
| `INTERFACE_DB_USER`    | `root`           | Interfacing DB username              |
| `INTERFACE_DB_PASSWORD`| *(root password)*| Interfacing DB password              |
| `INTERFACE_DB_NAME`    | `interfacing`    | Interfacing database name            |

## Updating InteLIS

On a traditional Ubuntu installation, updating requires running `upgrade.sh` — a ~1200-line script that handles Ubuntu package updates, PHP version switching, OPcache configuration, MySQL performance tuning (buffer pool sizing based on RAM, SSD detection, slow query logs), Composer updates, Apache config validation, database backups, vendor checksum verification, directory structure migrations, cron job setup, run-once scripts, file permissions, and multi-instance coordination.

With Docker, updating is simply:

```bash
cd intelis
git pull
docker compose up -d --build
```

The container rebuild picks up the new code, and the entrypoint script automatically runs database migrations, repairs, composer updates, and any run-once scripts — the same post-update tasks that `upgrade.sh` handles, without needing to worry about system-level configuration.

!!! tip
    The MySQL and PHP configurations are baked into the Docker images (`docker/mysql/my.cnf` and `docker/php-apache/custom-php.ini`), so you don't need to tune them manually.

## Common Commands

```bash
# Start containers
docker compose up -d

# View logs
docker compose logs -f intelis

# Stop containers
docker compose down

# Rebuild after code changes
docker compose up -d --build

# Access the application container shell
docker compose exec intelis bash

# Access MySQL CLI
docker compose exec intelis-db mysql -u root -p vlsm
```

## Data Persistence

The MySQL data is stored in a named Docker volume (`intelis_db_data`). Your data persists across container restarts and rebuilds.

To completely reset the database:

```bash
docker compose down -v
docker compose up -d
```

!!! warning
    Using `docker compose down -v` will **delete all database data**. Only use this if you want a fresh start.
