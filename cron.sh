#!/bin/bash

# Accept the environment configuration parameter from the command line
# If not provided, default to 'production'
APPLICATION_ENV=${1:-production}

# Export the environment configuration as an environment variable
export APPLICATION_ENV

# Get the directory where the script is located
SCRIPT_DIR=$(dirname "$0")

# Pause marker, written by upgrade.sh around the database migration step.
#
# Scheduled tasks open transactions on the form tables that step then runs DDL
# against, and MySQL grants metadata locks in request order: once a DDL is
# waiting, every later query on that table waits behind it. Skipping a minute
# or two of scheduled runs keeps that window clear.
#
# The marker expires on its own after MAX_PAUSE_MINUTES. Nothing else would
# guarantee cron comes back if an upgrade were interrupted between writing the
# marker and removing it, and silently stopping every scheduled task on an
# instance is far worse than running one during a migration.
PAUSE_FILE="${SCRIPT_DIR}/var/cron-paused"
MAX_PAUSE_MINUTES=30

if [ -f "$PAUSE_FILE" ]; then
    if [ -n "$(find "$PAUSE_FILE" -mmin +${MAX_PAUSE_MINUTES} 2>/dev/null)" ]; then
        rm -f "$PAUSE_FILE"
    else
        exit 0
    fi
fi

# Run the crunzphp command using the script's directory to construct the path
"$SCRIPT_DIR"/vendor/bin/crunz schedule:run
