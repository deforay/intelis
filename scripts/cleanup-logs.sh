#!/bin/bash

# Cleanup oversized VLSM logs safely without renaming files
# Usage: ./cleanup-logs.sh [/optional/path/to/logs]

# --- Configuration ---
DEFAULT_LOG_DIR="/var/www/vlsm/logs"
LOG_DIR="${1:-$DEFAULT_LOG_DIR}"
MAX_SIZE_MB=500         # Truncate logs above this size
MAX_TOTAL_MB=10240      # Max total log size before cleanup
MAX_KEEP=30             # Number of log files to keep

# --- Logging setup ---
LOGFILE="/var/log/vlsm-log-cleanup.log"
mkdir -p "$(dirname "$LOGFILE")"
exec >> "$LOGFILE" 2>&1
echo "[$(date +'%F %T')] --- Running VLSM log cleanup for $LOG_DIR ---"

# --- Validate log directory ---
if [ ! -d "$LOG_DIR" ]; then
  echo "❌ Log directory does not exist: $LOG_DIR"
  exit 1
fi

# --- Step 1: Truncate oversized files ---
find "$LOG_DIR" -type f -name '*-logfile.log' -size +"${MAX_SIZE_MB}M" -exec sh -c '
  for file; do
    echo "🧹 Truncating $file (exceeds '"${MAX_SIZE_MB}"'MB)"
    : > "$file"
  done
' sh {} +

# --- Step 2: Remove oldest logs if total exceeds limit ---
total_size=$(du -sm "$LOG_DIR" | cut -f1)
if [ "$total_size" -gt "$MAX_TOTAL_MB" ]; then
  echo "🚨 Total log size is ${total_size}MB (limit: ${MAX_TOTAL_MB}MB)"
  echo "🗑️  Deleting oldest logs beyond the most recent ${MAX_KEEP} files..."
  ls -tp "$LOG_DIR"/*-logfile.log 2>/dev/null | grep -v '/$' | tail -n +$((MAX_KEEP + 1)) | while read -r file; do
    echo "Deleting $file"
    rm -f "$file"
  done
fi
