#!/bin/bash
set -Eeuo pipefail

# The Windows-destination setup is no longer a separate script. One script now
# handles every backup destination — another Linux machine, a Windows shared
# folder, or a USB drive — and asks which one to use.
#
# This file stays behind so the old download link keeps working. It fetches and
# runs the unified script for you. Choose option 2 when asked where the backup
# should go.

UNIFIED_URL="https://raw.githubusercontent.com/deforay/intelis/master/scripts/remote-backup.sh"
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
UNIFIED="${SCRIPT_DIR}/remote-backup.sh"

if [ "$(id -u)" -ne 0 ]; then
  echo "Need admin privileges. Run with sudo."
  exit 1
fi

printf "\033[1;93m⚠️ Note:\033[0m Backing up to Windows is now part of remote-backup.sh.\n"
printf "\033[1;96mℹ️ Info:\033[0m Choose option 2 (a shared folder on a Windows machine) when asked.\n\n"

if [ ! -f "$UNIFIED" ]; then
  echo "Downloading remote-backup.sh..."
  if command -v wget >/dev/null 2>&1; then
    wget -q -O "$UNIFIED" "$UNIFIED_URL" || { echo "Could not download remote-backup.sh. Fetch it by hand from $UNIFIED_URL"; exit 1; }
  elif command -v curl >/dev/null 2>&1; then
    curl -fsSL -o "$UNIFIED" "$UNIFIED_URL" || { echo "Could not download remote-backup.sh. Fetch it by hand from $UNIFIED_URL"; exit 1; }
  else
    echo "Neither wget nor curl is installed. Download $UNIFIED_URL by hand and run it."
    exit 1
  fi
fi

chmod u+x "$UNIFIED"
exec bash "$UNIFIED" "$@"
