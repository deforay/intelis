#!/usr/bin/env bash
set -euo pipefail

# Intelis log cleanup (no renaming). Keeps inode, supports dry-run, safe concurrency.
# Usage: cleanup-logs.sh [-n] [-v] [--dir DIR] [--pattern GLOB] [--max-size MB] [--keep-tail MB] [--max-total MB] [--keep N]
#
# Example full crontab
# 30 2 * * * /var/www/intelis/scripts/cleanup-logs.sh --dir /var/www/intelis/var/logs --max-size 500 --max-total 10240 --keep 30 >> /var/log/intelis-log-cleanup.log 2>&1
# or minimal crontab
# 30 2 * * * /var/www/intelis/scripts/cleanup-logs.sh

# --- Defaults ---
# Default to the "var/logs" directory one level up from the script location
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_LOG_DIR="$SCRIPT_DIR/../var/logs"
LOG_DIR="$DEFAULT_LOG_DIR"
PATTERN="*-logfile.log"
MAX_SIZE_MB=500        # Truncate files larger than this
KEEP_TAIL_MB=5         # When truncating, keep last N MB (0 == empty the file)
MAX_TOTAL_MB=10240     # If total matched logs exceed this, prune oldest
MAX_KEEP=30            # Always keep the most recent N files (by mtime)
DRY_RUN=0
VERBOSE=0

# --- Parse args ---
while [[ $# -gt 0 ]]; do
  case "$1" in
    -n|--dry-run) DRY_RUN=1 ;;
    -v|--verbose) VERBOSE=1 ;;
    --dir) LOG_DIR="${2:-}"; shift ;;
    --pattern) PATTERN="${2:-}"; shift ;;
    --max-size) MAX_SIZE_MB="${2:-}"; shift ;;
    --keep-tail) KEEP_TAIL_MB="${2:-}"; shift ;;
    --max-total) MAX_TOTAL_MB="${2:-}"; shift ;;
    --keep) MAX_KEEP="${2:-}"; shift ;;
    -h|--help)
      cat <<EOF
Usage: $0 [-n] [-v] [--dir DIR] [--pattern GLOB] [--max-size MB] [--keep-tail MB] [--max-total MB] [--keep N]
Defaults:
  --dir $DEFAULT_LOG_DIR
  --pattern "$PATTERN"
  --max-size $MAX_SIZE_MB
  --keep-tail $KEEP_TAIL_MB
  --max-total $MAX_TOTAL_MB
  --keep $MAX_KEEP
EOF
      exit 0;;
    *) echo "Unknown arg: $1" >&2; exit 2;;
  esac
  shift
done

# --- Logging ---
LOGFILE="/var/log/vlsm-log-cleanup.log"
mkdir -p "$(dirname "$LOGFILE")"
exec >>"$LOGFILE" 2>&1
ts() { date +'%F %T'; }
log() { echo "[$(ts)] $*"; }
vlog() { [[ $VERBOSE -eq 1 ]] && log "$@"; }

log "--- Run start: dir=$LOG_DIR pattern=$PATTERN dry_run=$DRY_RUN ---"

# --- Lock (avoid concurrent runs) ---
LOCKFILE="/var/lock/vlsm-log-cleanup.lock"
exec 9>"$LOCKFILE" || { log "Cannot open lock $LOCKFILE"; exit 1; }
if ! flock -n 9; then
  log "Another cleanup is running. Exiting."
  exit 0
fi

# --- Validate dir ---
if [[ ! -d "$LOG_DIR" ]]; then
  log "❌ Log directory does not exist: $LOG_DIR"
  exit 1
fi

# --- Collect matching regular files (no symlinks) ---
# Use NUL separators to be safe with spaces.
mapfile -d '' FILES < <(find "$LOG_DIR" -type f -name "$PATTERN" -printf '%p\0' 2>/dev/null || true)
if [[ ${#FILES[@]} -eq 0 ]]; then
  log "No files matched pattern '$PATTERN' in $LOG_DIR"
  exit 0
fi
vlog "Matched ${#FILES[@]} file(s)."

# --- Truncate oversized files (keep last KEEP_TAIL_MB MB) ---
bytes_keep=$(( KEEP_TAIL_MB * 1024 * 1024 ))
oversized=()
while IFS= read -r -d '' path && IFS= read -r -d '' size; do
  sz_mb=$(( (size + 1024*1024 - 1) / (1024*1024) ))
  if (( sz_mb > MAX_SIZE_MB )); then
    oversized+=("$path:$size")
    log "🧹 Truncating: $path (size=${sz_mb}MB > ${MAX_SIZE_MB}MB; keep_tail=${KEEP_TAIL_MB}MB)"
    if [[ $DRY_RUN -eq 0 ]]; then
      if (( bytes_keep > 0 )); then
        tmp="${path}.tmp.$$"
        # Extract last bytes to tmp, then overwrite same file (no rename of original)
        tail -c "$bytes_keep" "$path" > "$tmp" 2>/dev/null || :  # tail returns 1 if file smaller; ignore
        : > "$path"          # truncate in place
        cat "$tmp" >> "$path"
        rm -f "$tmp"
      else
        : > "$path"
      fi
    fi
  else
    vlog "Skip (not oversized): $path (${sz_mb}MB)"
  fi
done < <(find "$LOG_DIR" -type f -name "$PATTERN" -printf '%p\0%s\0' 2>/dev/null)

# --- Compute total size of matched logs (MB) ---
total_bytes=$(find "$LOG_DIR" -type f -name "$PATTERN" -printf '%s\n' 2>/dev/null | awk '{s+=$1} END{print s+0}')
total_mb=$(( (total_bytes + 1024*1024 - 1) / (1024*1024) ))
log "Total matched log size: ${total_mb}MB (limit: ${MAX_TOTAL_MB}MB)"

# --- Prune oldest if above MAX_TOTAL_MB ---
if (( total_mb > MAX_TOTAL_MB )); then
  log "🚨 Over limit. Keeping most recent ${MAX_KEEP} file(s); deleting older ones until under limit."
  # List files by mtime desc; keep first MAX_KEEP; mark rest as candidates
  # Then delete oldest first while total > limit.
  # Build arrays with (mtime, size, path)
  mapfile -d '' entries < <(find "$LOG_DIR" -type f -name "$PATTERN" -printf '%T@ %s %p\0' | sort -z -r -n)
  to_keep=()
  to_consider=()
  idx=0
  for e in "${entries[@]}"; do
    (( idx < MAX_KEEP )) && to_keep+=("$e") || to_consider+=("$e")
    ((idx++))
  done
  vlog "Keeping ${#to_keep[@]} newest file(s), considering ${#to_consider[@]} for deletion."

  for e in "${to_consider[@]}"; do
    # shellcheck disable=SC2086
    mtime=$(awk '{print $1}' <<<"$e")
    size=$(awk '{print $2}' <<<"$e")
    path="${e#* * }"  # strip first two fields and space
    if (( total_mb <= MAX_TOTAL_MB )); then break; fi
    log "🗑️  Deleting: $path (size=$(( (size + 1048575)/1048576 ))MB; mtime=$mtime)"
    if [[ $DRY_RUN -eq 0 ]]; then
      rm -f -- "$path" || log "Failed to delete $path"
    fi
    # update running total
    if (( size < total_bytes )); then
      total_bytes=$(( total_bytes - size ))
      total_mb=$(( (total_bytes + 1024*1024 -1)/ (1024*1024) ))
    else
      total_bytes=0; total_mb=0
    fi
  done
  log "Post-prune total matched log size: ${total_mb}MB"
fi

log "--- Run end ---"
