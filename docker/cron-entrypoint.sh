#!/bin/bash

# Start cron in the background
cron

# Start the main process (in this case, keep the container running)
tail -f /dev/null
