#!/bin/bash

# Accept the environment configuration parameter from the command line
# If not provided, default to 'production'
APPLICATION_ENV=${1:-production}

# Export the environment configuration as an environment variable
export APPLICATION_ENV

# Get the directory where the script is located
SCRIPT_DIR=$(dirname "$0")

# Run the crunzphp command using the script's directory to construct the path
"$SCRIPT_DIR"/vendor/bin/crunz schedule:run
