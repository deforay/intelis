#!/bin/bash
# shared-functions.sh - Common functions for LIS scripts

# Ensure UTF-8 locale so Unicode characters (─ ▶ ✓ ✅ etc.) render correctly.
export LANG="${LANG:-en_US.UTF-8}"
export LC_ALL="${LC_ALL:-en_US.UTF-8}"
# Unified print function for colored output
print() {
    local type=$1
    local message=$2
    local header_char="="

    case $type in
        error)
            printf "\033[1;91m❌ Error:\033[0m %s\n" "$message"
        ;;
        success)
            printf "\033[1;92m✅ Success:\033[0m %s\n" "$message"
        ;;
        warning)
            printf "\033[1;93m⚠️ Warning:\033[0m %s\n" "$message"
        ;;
        info)
            printf "\033[1;96mℹ️ Info:\033[0m %s\n" "$message"
        ;;
        debug)
            printf "\033[1;95m🐛 Debug:\033[0m %s\n" "$message"
        ;;
        header)
            local term_width
            term_width=$( [ -t 1 ] && tput cols 2>/dev/null || echo 80 )
            local msg_length=${#message}
            local padding=$(((term_width - msg_length) / 2))
            ((padding < 0)) && padding=0
            local pad_str
            pad_str=$(printf '%*s' "$padding" '')
            printf "\n\033[1;96m%*s\033[0m\n" "$term_width" '' | tr ' ' "$header_char"
            printf "\033[1;96m%s%s\033[0m\n" "$pad_str" "$message"
            printf "\033[1;96m%*s\033[0m\n\n" "$term_width" '' | tr ' ' "$header_char"
        ;;
        *)
            printf "%s\n" "$message"
        ;;
    esac
}

# Print a status board for all instances during multi-instance upgrades
# Usage: print_instance_status paths_array_name statuses_array_name
print_instance_status() {
    local -n _paths=$1
    local -n _statuses=$2
    local total=${#_paths[@]}
    local term_width
    term_width=$([ -t 1 ] && tput cols 2>/dev/null || echo 80)

    echo ""
    printf "\033[1;96m%${term_width}s\033[0m\n" '' | tr ' ' '='
    printf "\033[1;96m  Instance Progress (%d total)\033[0m\n" "$total"
    printf "\033[1;96m%${term_width}s\033[0m\n" '' | tr ' ' '='
    local i
    for i in "${!_paths[@]}"; do
        local status="${_statuses[$i]}"
        local icon label color
        case $status in
            pending)  icon="○"; label="pending";     color="\033[0;37m"  ;;
            running)  icon="▶"; label="in progress"; color="\033[1;93m"  ;;
            done)     icon="✓"; label="done";         color="\033[1;92m"  ;;
            failed)   icon="✗"; label="failed";       color="\033[1;91m"  ;;
        esac
        printf "  ${color}[%d/%d] %s  %s  (%s)\033[0m\n" "$((i+1))" "$total" "$icon" "${_paths[$i]}" "$label"
    done
    printf "\033[1;96m%${term_width}s\033[0m\n" '' | tr ' ' '='
    echo ""
}

escape_php_string_for_sed() {
    # Escape for PHP single-quoted strings and sed replacement
    local value="$1"
    value=${value//\\/\\\\}   # escape backslashes for PHP single-quoted strings
    value=${value//\'/\\\'}   # escape single quotes
    value=${value//|/\\|}     # escape sed delimiter
    value=${value//&/\\&}     # escape sed replacement backreference
    printf '%s' "$value"
}

# Install required packages
install_packages() {
    local required_pkgs=(curl aria2 wget lsb-release bc pigz gpg fzf zstd git rsync)
    # Map package names to their actual command names
    declare -A pkg_to_cmd=(
        ["curl"]="curl"
        ["aria2"]="aria2c"
        ["wget"]="wget"
        ["lsb-release"]="lsb_release"
        ["bc"]="bc"
        ["pigz"]="pigz"
        ["gpg"]="gpg"
        ["fzf"]="fzf"
        ["zstd"]="zstd"
        ["git"]="git"
        ["rsync"]="rsync"
    )
    
    local missing_pkgs=()
    for pkg in "${required_pkgs[@]}"; do
        local cmd="${pkg_to_cmd[$pkg]}"
        if ! command -v "$cmd" &>/dev/null; then
            missing_pkgs+=("$pkg")
        fi
    done

    if [ "${#missing_pkgs[@]}" -gt 0 ]; then
        apt-get update
        apt-get install -y "${missing_pkgs[@]}"
        
        # Re-check all required packages with correct command names
        for pkg in "${required_pkgs[@]}"; do
            local cmd="${pkg_to_cmd[$pkg]}"
            if ! command -v "$cmd" &>/dev/null; then
                print error "Failed to install required package: $pkg (command: $cmd). Exiting."
                exit 1
            fi
        done
    fi
}
prepare_system() {
    install_packages
    check_ubuntu_version "20.04"

    if ! command -v needrestart &>/dev/null; then
        print info "Installing needrestart..."
        apt-get install -y needrestart
    fi

    export NEEDRESTART_MODE=a # Auto-restart services non-interactively

    # Configure needrestart to non-interactive
    local conf_file="/etc/needrestart/needrestart.conf"
    if [ -f "$conf_file" ]; then
        sed -i "s/^\(\$nrconf{restart}\s*=\s*\).*/\1'a';/" "$conf_file" || echo "\$nrconf{restart} = 'a';" >>"$conf_file"
    else
        echo "\$nrconf{restart} = 'a';" >"$conf_file"
    fi

    print success "System preparation complete with non-interactive restarts configured."
}
spinner() {
    # BC signature: spinner <pid> [message]
    local pid="${1:-}"
    local message="${2:-Processing...}"
    local delay=0.2
    local status=1
    local is_tty=0

    # Basic validation
    [[ "$pid" =~ ^[0-9]+$ ]] || {
        printf "[FAIL] %s (invalid pid)\n" "$message"
        return 1
    }

    # TTY check (no locale/tput usage; set -u safe)
    [ -t 1 ] && is_tty=1

    # One-line start
    if (( is_tty )); then
        # Print message and then dots while we wait
        printf "%s " "$message"
    fi

    # First try to 'wait' if it's our child; else fall back to polling
    if wait "$pid" 2>/dev/null; then
        status=0
    else
        status=$?
        if [[ $status -eq 127 ]]; then
            # Not our child → poll existence until it exits
            status=0
            while kill -0 "$pid" 2>/dev/null; do
                (( is_tty )) && printf "."
                sleep "$delay"
            done
            # Can't know true exit code here; treat as success unless caller checks otherwise
        fi
    fi

    # Line end for TTY
    (( is_tty )) && printf "\n"

    # BC: print a clear success/fail line with the same message
    if (( status == 0 )); then
        printf "\033[1;92m✅ Success:\033[0m %s\n" "$message"
    else
        printf "\033[1;91m❌ Error:\033[0m %s (exit code: %d)\n" "$message" "$status"
    fi

    return "$status"
}


download_file() {
    local output_file="$1"
    local url="$2"
    local default_msg="Downloading $(basename "$output_file")..."
    local message="${3:-$default_msg}"

    # Get output directory and filename
    local output_dir
    output_dir=$(dirname "$output_file")
    local filename
    filename=$(basename "$output_file")

    # Create the directory if it doesn't exist
    if [ ! -d "$output_dir" ]; then
        mkdir -p "$output_dir" || {
            print error "Failed to create directory $output_dir"
            return 1
        }
    fi

    # Remove existing file if it exists
    [ -f "$output_file" ] && rm -f "$output_file"

    print info "$message"

    local log_file
    log_file=$(mktemp)

    # Try aria2c first
    if command -v aria2c &>/dev/null; then
        aria2c -x 5 -s 5 \
            --console-log-level=error \
            --summary-interval=0 \
            --allow-overwrite=true \
            --no-conf \
            --conditional-get=false \
            --remote-time=false \
            -d "$output_dir" \
            -o "$filename" \
            "$url" >"$log_file" 2>&1 &
        
        local download_pid=$!
        spinner "$download_pid" "$message"
        
        # Check if file downloaded successfully
        if [ -f "$output_file" ] && [ -s "$output_file" ]; then
            print success "Download completed: $filename"
            rm -f "$log_file"
            return 0
        fi
        
        # aria2c failed, try wget
        print warning "aria2c failed, trying wget..."
        rm -f "$output_file"
    fi

    # Fallback to wget
    if command -v wget &>/dev/null; then
        wget --progress=bar:force \
            --tries=3 \
            --timeout=30 \
            -O "$output_file" \
            "$url" >"$log_file" 2>&1 &
        
        local download_pid=$!
        spinner "$download_pid" "$message"
        
        # Check if wget succeeded
        if [ -f "$output_file" ] && [ -s "$output_file" ]; then
            print success "Download completed: $filename"
            rm -f "$log_file"
            return 0
        fi
    fi

    # Both failed
    print error "Download failed for: $filename"
    print info "Detailed download logs:"
    cat "$log_file"
    rm -f "$log_file"
    return 1
}


# Download a file only if the remote version has changed
download_if_changed() {
    local output_file="$1"
    local url="$2"

    local tmpfile
    tmpfile=$(mktemp)

    if ! wget -q -O "$tmpfile" "$url"; then
        print error "Failed to download $(basename "$output_file") from $url"
        rm -f "$tmpfile"
        return 1
    fi

    if [ -f "$output_file" ]; then
        local new_checksum old_checksum
        new_checksum=$(md5sum "$tmpfile" | awk '{print $1}')
        old_checksum=$(md5sum "$output_file" | awk '{print $1}')

        if [ "$new_checksum" = "$old_checksum" ]; then
            print info "$(basename "$output_file") is already up-to-date."
            rm -f "$tmpfile"
            return 0
        fi
    fi

    mv "$tmpfile" "$output_file"
    chmod +x "$output_file"
    print success "Downloaded and updated $(basename "$output_file")"
    return 0
}


error_handling() {
    local last_cmd=$1
    local last_line=$2
    local last_error=$3
    echo "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"
    log_action "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"
    exit 1
}

# Ubuntu version check
check_ubuntu_version() {
    local min_version=$1
    local current_version=$(lsb_release -rs)

    # Check if version is greater than or equal to min_version
    if [[ "$(printf '%s\n' "$min_version" "$current_version" | sort -V | head -n1)" != "$min_version" ]]; then
        print error "This script requires Ubuntu ${min_version} or newer."
        exit 1
    fi

    # Check if it's an LTS release
    local description=$(lsb_release -d)
    if ! echo "$description" | grep -q "LTS"; then
        print error "This script requires an Ubuntu LTS release."
        exit 1
    fi

    print success "Ubuntu version check passed: Running Ubuntu ${current_version} LTS."
}

# Validate LIS application path
is_valid_application_path() {
    local path=$1
    if [ -f "$path/configs/config.production.php" ] && [ -d "$path/public" ]; then
        return 0
    else
        return 1
    fi
}

# Convert to absolute path
to_absolute_path() {
    local p="$1"

    # empty → echo empty (caller decides fallback)
    [ -z "$p" ] && { echo ""; return 0; }

    # expand leading "~" → $HOME
    [[ "$p" == "~"* ]] && p="${p/#\~/$HOME}"

    if command -v realpath >/dev/null 2>&1; then
        # -m: canonicalize even if components don’t exist; "." works too
        realpath -m -- "$p"
        return $?
    fi

    # GNU readlink: prefer -m if available, else -f (requires existing path)
    if readlink -m / >/dev/null 2>&1; then
        readlink -m -- "$p"
        return $?
    fi

    case "$p" in
        /*) printf '%s\n' "$p" ;;
        *)  printf '%s\n' "$(pwd)/$p" ;;
    esac
}


# ---------------------------------------------------------------------------
# Source acquisition — shared by setup.sh (first install) and upgrade.sh's
# prepare phase. Keeping it here means both fetch the master tree the same way:
# a persistent shallow git mirror with cheap delta fetches, a fresh shallow
# clone as fallback, and the codeload tarball as the last resort.
# ---------------------------------------------------------------------------
MASTER_GIT_URL="${MASTER_GIT_URL:-https://github.com/deforay/intelis.git}"
MASTER_TARBALL_URL="${MASTER_TARBALL_URL:-https://codeload.github.com/deforay/intelis/tar.gz/refs/heads/master}"

# Persistent shallow mirror of master. After the first clone, callers advance it
# with DELTA fetches (only changed objects, usually tens of KB) instead of
# re-downloading the full codeload tarball every time.
INTELIS_SRC_DIR="${INTELIS_SRC_DIR:-/usr/local/lib/intelis/src}"

# git network wrapper: a generous wall-clock backstop so a hung connection can't
# stall forever, plus a low-speed abort (below ~1KB/s for 60s) that fails a truly
# dead link fast while a slow-but-working one survives. safe.directory='*' avoids
# git's "dubious ownership" refusal if the mirror's owner ever differs from root.
run_git() {
    local _timeout_cmd=""
    command -v timeout >/dev/null 2>&1 && _timeout_cmd="timeout --kill-after=15 ${GIT_NET_TIMEOUT:-2400}"
    $_timeout_cmd git -c safe.directory='*' -c http.lowSpeedLimit=1000 -c http.lowSpeedTime=60 "$@"
}

# fetch_master_tree <extract_dir>
#   Populate <extract_dir> with the deforay/intelis master working tree — the
#   equivalent of the codeload tarball's intelis-master/ contents at the top
#   level. <extract_dir>'s basename MUST be "intelis-master" so the tarball
#   fallback (which extracts into the parent) lands in the right place.
#
#   Strategy, cheapest first:
#     1. delta-fetch an existing shallow mirror at $INTELIS_SRC_DIR
#     2. fresh shallow clone into the mirror (3 attempts)
#     3. codeload tarball (git missing/unreachable) — no mirror, so no future
#        deltas; the next run re-establishes it
#
#   Writes <extract_dir>/VERSION.txt with the commit SHA when it can determine
#   one (rev-parse on the git paths; best-effort GitHub API on the tarball path).
#   Echoes progress to stdout so callers may redirect it to a log. Returns 0 only
#   when <extract_dir>/composer.json exists afterward.
fetch_master_tree() {
    local extract_dir="$1"
    if [ -z "$extract_dir" ]; then
        echo "fetch_master_tree: no extract dir given" >&2
        return 2
    fi

    local staging_dir
    staging_dir="$(dirname "$extract_dir")"
    local master_tar="${staging_dir}/master.tar.gz"
    local src_ready=false

    # Already staged (resumable callers) — skip.
    if [ -d "$extract_dir" ] && [ -f "$extract_dir/composer.json" ]; then
        echo "master: already staged at ${extract_dir}, skipping"
        return 0
    fi

    # Attempt 1: delta-fetch an existing mirror (cheap; changed objects only).
    if command -v git >/dev/null 2>&1 && [ -d "$INTELIS_SRC_DIR/.git" ]; then
        echo "master: updating source mirror (delta fetch — only changed files)"
        if run_git -C "$INTELIS_SRC_DIR" fetch --depth 1 origin master &&
            git -c safe.directory='*' -C "$INTELIS_SRC_DIR" reset --hard FETCH_HEAD &&
            git -c safe.directory='*' -C "$INTELIS_SRC_DIR" clean -fd; then
            # Shallow fetch/reset orphans the previous tip; sweep it now so the
            # mirror doesn't bloat over many runs.
            git -c safe.directory='*' -C "$INTELIS_SRC_DIR" gc --prune=now --quiet 2>/dev/null || true
            src_ready=true
            echo "master: mirror updated via delta fetch"
        else
            echo "master: delta fetch failed; will re-clone the mirror"
            rm -rf "$INTELIS_SRC_DIR"
        fi
    fi

    # Attempt 2: fresh shallow clone into the mirror.
    if [ "$src_ready" = false ] && command -v git >/dev/null 2>&1; then
        echo "master: shallow-cloning master into source mirror"
        local attempt
        for attempt in 1 2 3; do
            rm -rf "$INTELIS_SRC_DIR"
            mkdir -p "$(dirname "$INTELIS_SRC_DIR")"
            if run_git clone --depth 1 --single-branch --branch master \
                "$MASTER_GIT_URL" "$INTELIS_SRC_DIR"; then
                src_ready=true
                echo "master: cloned (attempt ${attempt}); future updates will be delta-only"
                break
            fi
            echo "master: clone attempt ${attempt}/3 failed"
            sleep 3
        done
    fi

    if [ "$src_ready" = true ]; then
        # Stage the working tree (minus .git, which must never reach an instance).
        echo "master: staging tree from mirror"
        rm -rf "$extract_dir"
        mkdir -p "$extract_dir"
        rsync -a --exclude='.git' --exclude='.git/' "$INTELIS_SRC_DIR/" "$extract_dir/"

        local _master_sha
        _master_sha=$(git -c safe.directory='*' -C "$INTELIS_SRC_DIR" rev-parse HEAD 2>/dev/null || true)
        if [ -n "$_master_sha" ]; then
            printf '%s\n' "$_master_sha" >"$extract_dir/VERSION.txt"
            echo "master: commit SHA $_master_sha captured"
        fi
    else
        # Attempt 3: tarball fallback (git missing/unreachable).
        echo "master: git unavailable; falling back to codeload tarball"
        if [ ! -f "$master_tar" ]; then
            echo "master: downloading from $MASTER_TARBALL_URL"
            download_file "$master_tar" "$MASTER_TARBALL_URL" "master: downloading tarball" || {
                echo "master: tarball download failed" >&2
                return 1
            }
        else
            echo "master: tarball already present, skipping download"
        fi
        # Verify it's a valid gzip tarball before extracting so a truncated or
        # corrupt download fails loudly instead of extracting a partial tree.
        if ! tar -tzf "$master_tar" >/dev/null 2>&1; then
            echo "master: ${master_tar} is not a valid archive (truncated/corrupt)" >&2
            return 1
        fi
        echo "master: extracting"
        rm -rf "$extract_dir"
        # codeload wraps contents in intelis-master/, matching extract_dir's
        # basename — extract into the parent so it lands at $extract_dir.
        tar -xzf "$master_tar" -C "$staging_dir" || {
            echo "master: tarball extraction failed" >&2
            return 1
        }

        # No local git to rev-parse; capture HEAD SHA via the GitHub API
        # (best-effort). Tiny race: API HEAD vs. tarball content can differ by a
        # commit if someone pushes between the two requests.
        local _sha_response _master_sha
        _sha_response=$(curl -sS --max-time 10 \
            "https://api.github.com/repos/deforay/intelis/commits/master" 2>/dev/null || true)
        _master_sha=$(printf '%s' "$_sha_response" \
            | grep -oE '"sha"[[:space:]]*:[[:space:]]*"[0-9a-f]{40}"' \
            | head -1 \
            | grep -oE '[0-9a-f]{40}')
        if [ -n "$_master_sha" ]; then
            printf '%s\n' "$_master_sha" >"$extract_dir/VERSION.txt"
            echo "master: commit SHA $_master_sha captured"
        else
            echo "master: commit SHA lookup skipped (no network or rate-limited)"
        fi
    fi

    if [ ! -f "$extract_dir/composer.json" ]; then
        echo "master: composer.json missing after staging" >&2
        return 1
    fi
    echo "master: ready"
    return 0
}


# Set ACL-based permissions (async by default; pass third arg "sync" to wait).
#
# Performance notes:
#   - Batched: setfacl is called with up to $ACL_BATCH paths per invocation
#     (~200 by default), not one fork per file. On a typical instance this
#     turns ~15k forks into ~75 and cuts wall-clock from minutes to seconds.
#   - Excludes .git and node_modules across ALL modes (was previously only
#     excluded in `full`).
#   - Probes ACL support upfront: filesystems that reject ACLs (overlayfs,
#     some NFS mounts) fall back to chown/chmod once instead of every file
#     bouncing through setfacl + the failures log.
#   - Truncates /tmp/acl_failures.log at start so the warning at the end
#     reflects ONLY this run's failures (was previously cumulative across
#     every upgrade).
set_permissions() {
    local path=$1
    local mode=${2:-"full"}          # full | quick | minimal
    local wait_mode=${3:-"async"}    # async | sync

    # Who to grant (robust under sudo/non-interactive)
    local who="${SUDO_USER:-${USER:-root}}"

    # The path may legitimately not exist yet (e.g. var/logs on a fresh
    # instance). Don't let a missing directory get misdiagnosed as "no ACL
    # support" and then abort the whole run when chown/chmod fail. Create it
    # so permissions can actually be applied.
    if [[ ! -e "$path" ]]; then
        if ! mkdir -p "$path" 2>/dev/null; then
            print warning "Path ${path} does not exist and could not be created. Skipping permissions."
            return 0
        fi
    fi

    # Only THIS run's failures should drive the warning at the end.
    : > /tmp/acl_failures.log

    if ! command -v setfacl &>/dev/null; then
        print warning "setfacl not found. Falling back to chown/chmod..."
        chown -R "$who":www-data "$path" || true
        chmod -R u+rwX,g+rwX "$path" || true
        return 0
    fi

    # Probe: does this filesystem accept ACLs at all? If not, every setfacl
    # below would fail; fall back once instead of churning through thousands
    # of forks.
    if ! setfacl -m "u:${who}:rwx" "$path" 2>/dev/null; then
        print warning "Filesystem at ${path} does not support ACLs. Falling back to chown/chmod..."
        chown -R "$who":www-data "$path" || true
        chmod -R u+rwX,g+rwX "$path" || true
        return 0
    fi

    # Tunables
    local PARALLEL=${PARALLEL:-$(nproc)}
    local BATCH=${ACL_BATCH:-200}                      # files per setfacl call
    local CPU_NICE="nice -n 10"
    local IO_NICE=""
    command -v ionice >/dev/null 2>&1 && IO_NICE="ionice -c3"

    print info "Setting permissions for ${path} (${mode}, ${wait_mode})..."

    # Common excludes — keep .git and node_modules out of the sweep across
    # all modes. .git alone can be 5k–30k files on a long-running repo.
    local -a EXCLUDES=(-not -path "*/.git*" -not -path "*/node_modules*")

    local pids=()

    case "$mode" in
        full)
            # Directories: rwx to user + www-data
            find "$path" -type d "${EXCLUDES[@]}" -print0 \
                | $CPU_NICE $IO_NICE xargs -0 -n "$BATCH" -P "$PARALLEL" \
                    setfacl -m "u:${who}:rwx,u:www-data:rwx" 2>>/tmp/acl_failures.log &
            pids+=($!)

            # Files: rw to user + www-data
            find "$path" -type f "${EXCLUDES[@]}" -print0 \
                | $CPU_NICE $IO_NICE xargs -0 -n "$BATCH" -P "$PARALLEL" \
                    setfacl -m "u:${who}:rw,u:www-data:rw" 2>>/tmp/acl_failures.log &
            pids+=($!)
        ;;
        quick)
            find "$path" -type d "${EXCLUDES[@]}" -print0 \
                | $CPU_NICE $IO_NICE xargs -0 -n "$BATCH" -P "$PARALLEL" \
                    setfacl -m "u:${who}:rwx,u:www-data:rwx" 2>>/tmp/acl_failures.log &
            pids+=($!)

            find "$path" -type f -name "*.php" "${EXCLUDES[@]}" -print0 \
                | $CPU_NICE $IO_NICE xargs -0 -n "$BATCH" -P "$PARALLEL" \
                    setfacl -m "u:${who}:rw,u:www-data:rw" 2>>/tmp/acl_failures.log &
            pids+=($!)
        ;;
        minimal)
            find "$path" -type d "${EXCLUDES[@]}" -print0 \
                | $CPU_NICE $IO_NICE xargs -0 -n "$BATCH" -P "$PARALLEL" \
                    setfacl -m "u:${who}:rwx,u:www-data:rwx" 2>>/tmp/acl_failures.log &
            pids+=($!)
        ;;
      *)
        print warning "Unknown mode '${mode}', using 'full'."
        "$FUNCNAME" "$path" full "$wait_mode"
        return
        ;;
    esac

    if [[ "$wait_mode" == "sync" ]]; then
        for pid in "${pids[@]}"; do wait "$pid"; done
        if [[ -s /tmp/acl_failures.log ]]; then
            local n_fail
            n_fail=$(wc -l </tmp/acl_failures.log | tr -d ' ')
            print warning "Some ACL operations failed (${n_fail} line(s)). See /tmp/acl_failures.log"
        fi
        print success "Permissions applied (sync)."
    else
        print info "ACLs applying in background (async)."
    fi
}

# Function to restart a service (MySQL or Apache)
restart_service() {
    local service_type=$1

    case "$service_type" in
        apache)
            if systemctl list-unit-files apache2.service >/dev/null 2>&1; then
                print info "Restarting Apache (apache2)..."
                log_action "Restarting apache2"
                systemctl restart apache2 || return 1
            elif systemctl list-unit-files httpd.service >/dev/null 2>&1; then
                print info "Restarting Apache (httpd)..."
                log_action "Restarting httpd"
                systemctl restart httpd || return 1
            else
                print warning "Apache/httpd service not found"
                log_action "Apache/httpd not found"
                return 1
            fi
            ;;
        mysql)
            print info "Restarting MySQL..."
            log_action "Restarting MySQL"
            systemctl restart mysql || return 1
        ;;
      *)
        print error "Unknown service type: $service_type"
        log_action "Unknown service type: $service_type"
        return 1
        ;;
    esac

    print success "$service_type restarted successfully"
    return 0
}


# Resolve the MySQL/MariaDB systemd unit name on this host.
mysql_unit_name() {
    local unit
    for unit in mysql mysqld mariadb; do
        if systemctl list-unit-files "${unit}.service" >/dev/null 2>&1; then
            echo "$unit"
            return 0
        fi
    done
    echo "mysql"
}

# Reachability probe. `mysqladmin ping` reports the server alive even on auth
# errors, so this needs no credentials.
mysql_is_up() {
    mysqladmin ping --silent >/dev/null 2>&1
}

# Best-effort recovery: make sure MySQL is running. Safe to call multiple times
# and on hosts without MySQL (returns 0 quietly). Strategy:
#   1) if already reachable, do nothing;
#   2) try to start/restart the unit and wait for the socket;
#   3) if a recent mysqld.cnf backup exists, the live config is probably the
#      culprit (e.g. a removed option that makes mysqld refuse to start) — restore
#      the newest backup and retry once;
#   4) on continued failure, dump recent journal lines to help the operator.
# Returns 0 if MySQL ends up reachable, 1 otherwise.
ensure_mysql_running() {
    local cnf="${1:-/etc/mysql/mysql.conf.d/mysqld.cnf}"
    local unit i newest_bak

    # Nothing to restore if MySQL isn't installed on this host.
    command -v mysqladmin >/dev/null 2>&1 || return 0

    if mysql_is_up; then
        return 0
    fi

    unit="$(mysql_unit_name)"
    print warning "MySQL is not reachable. Attempting to bring it back up (unit: ${unit})..."
    log_action "ensure_mysql_running: MySQL down, attempting recovery"

    # 1) Plain start, then restart as a fallback.
    systemctl start "$unit" 2>/dev/null || systemctl restart "$unit" 2>/dev/null || true
    for ((i = 1; i <= 30; i++)); do
        if mysql_is_up; then
            print success "MySQL is back up."
            log_action "ensure_mysql_running: recovered via start/restart"
            return 0
        fi
        sleep 1
    done

    # 2) Live config may be bad. Restore the newest backup and retry.
    newest_bak="$(ls -1t "${cnf}".bak.* 2>/dev/null | head -1)"
    if [ -n "$newest_bak" ] && [ -f "$newest_bak" ]; then
        print warning "Restoring MySQL config from backup: ${newest_bak}"
        log_action "ensure_mysql_running: restoring config from ${newest_bak}"
        cp "$cnf" "${cnf}.failed.$(date +%Y%m%d%H%M%S)" 2>/dev/null || true
        cp "$newest_bak" "$cnf" 2>/dev/null || true
        systemctl restart "$unit" 2>/dev/null || true
        for ((i = 1; i <= 30; i++)); do
            if mysql_is_up; then
                print success "MySQL recovered after restoring config from backup."
                log_action "ensure_mysql_running: recovered via config restore"
                return 0
            fi
            sleep 1
        done
    fi

    # 3) Give up, but leave breadcrumbs.
    print error "MySQL is still down after recovery attempts. Recent service log:"
    journalctl -u "$unit" -n 30 --no-pager 2>/dev/null | sed 's/^/    /' || true
    log_action "ensure_mysql_running: FAILED to recover MySQL"
    return 1
}


# Ask user yes/no
ask_yes_no() {
    local prompt="$1"
    local default="${2:-no}"
    local timeout=15
    local answer

    # Normalize default
    default=$(echo "$default" | awk '{print tolower($0)}')
    [[ "$default" != "yes" && "$default" != "no" ]] && default="no"

    # If stdin is not a terminal, fallback to default
    if [ ! -t 0 ]; then
        [[ "$default" == "yes" ]] && return 0 || return 1
    fi

    echo -n "$prompt (y/n) [default: $default, auto in ${timeout}s]: "

    read -t "$timeout" answer
    if [ $? -ne 0 ]; then
        print info "No input received in ${timeout} seconds. Using default: $default"
        [[ "$default" == "yes" ]] && return 0 || return 1
    fi

    # Treat empty input (Enter) as choosing default
    answer=$(echo "$answer" | awk '{print tolower($0)}')
    if [ -z "$answer" ]; then
        print info "Using default: $default"
        [[ "$default" == "yes" ]] && return 0 || return 1
    fi

    case "$answer" in
        y | yes) return 0 ;;
        n | no)  return 1 ;;
        *)
            print warning "Invalid input. Using default: $default"
            [[ "$default" == "yes" ]] && return 0 || return 1
        ;;
    esac
}


# Extract MySQL root password from config file
extract_mysql_password_from_config() {
    local config_file="$1"
    if [ ! -f "$config_file" ]; then
        print error "Config file not found: $config_file"
        return 1
    fi
    php -r "
        error_reporting(0);
        \$config = include '$config_file';
        echo isset(\$config['database']['password']) ? trim(\$config['database']['password']) : '';
    "
}

# Log action to log file
log_action() {
    local message=$1
    local logfile="${log_file:-/tmp/intelis-$(date +'%Y%m%d').log}"

    # Rotate if larger than 10MB
    if [ -f "$logfile" ] && [ $(stat -c %s "$logfile") -gt 10485760 ]; then
        mv "$logfile" "${logfile}.old"
    fi

    echo "$(date +'%Y-%m-%d %H:%M:%S') - $message" >>"$logfile"
}

# Helper for idempotent file writing
write_if_different() {
    local target="$1"
    local tmp
    tmp="$(mktemp)"
    cat >"$tmp"
    if [[ -f "$target" ]] && cmp -s "$tmp" "$target"; then
        rm -f "$tmp"
        return 1  # unchanged
    fi
    install -D -m 0644 "$tmp" "$target"
    rm -f "$tmp"
    return 0  # written/changed
}

# Setup Scheduler (systemd timer replacement for cron)
setup_intelis_scheduler() {
    local lis_path="$1"
    local application_env="${2:-production}"

    # Create unique service name based on installation path
    local base_name="$(basename "$lis_path")"
    if [[ "$base_name" == "vlsm" || "$base_name" == "intelis" ]]; then
        local service_name="intelis"
    else
        local service_name="intelis-$base_name"
    fi

    print info "Configuring Scheduler (systemd timer) for ${lis_path}..."
    log_action "Configuring Scheduler with path: $lis_path, environment: $application_env, service: $service_name"

    # Validate paths
    if [[ ! -f "${lis_path}/cron.sh" ]]; then
        print error "cron.sh not found at ${lis_path}/cron.sh"
        log_action "ERROR: cron.sh not found at ${lis_path}/cron.sh"
        return 1
    fi

    # Make cron.sh executable
    chmod +x "${lis_path}/cron.sh"

    # Track what actually changed
    local service_changed=0
    local timer_changed=0
    local cron_removed=0

    # Create/update systemd service
    local service_file="/etc/systemd/system/${service_name}.service"
    if write_if_different "$service_file" <<EOF
[Unit]
Description=Scheduler for ${lis_path}
After=network-online.target mysql.service apache2.service
Wants=network-online.target

[Service]
Type=oneshot
User=www-data
Group=www-data
Environment=APPLICATION_ENV=${application_env}
WorkingDirectory=${lis_path}
ExecStart=${lis_path}/cron.sh ${application_env}

# Prevent multiple instances
RemainAfterExit=no

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=${service_name}
EOF
    then
        service_changed=1
        print info "Updated ${service_name}.service"
        log_action "Updated ${service_name}.service"
    else
        print info "${service_name}.service already up to date"
    fi

    # Create/update systemd timer
    local timer_file="/etc/systemd/system/${service_name}.timer"
    if write_if_different "$timer_file" <<EOF
[Unit]
Description=Run scheduled jobs every minute for ${lis_path}

[Timer]
OnBootSec=120s
OnUnitActiveSec=60s
AccuracySec=5s
Unit=${service_name}.service
Persistent=true

[Install]
WantedBy=timers.target
EOF
    then
        timer_changed=1
        print info "Updated ${service_name}.timer"
        log_action "Updated ${service_name}.timer"
    else
        print info "${service_name}.timer already up to date"
    fi

    # Only reload systemd if files actually changed
    if [[ "$service_changed" == "1" || "$timer_changed" == "1" ]]; then
        systemctl daemon-reload
        print info "Reloaded systemd configuration"
        log_action "Reloaded systemd due to timer/service changes"
    fi

    # Migrate from cron  - comment out matching lines
    local current_crontab
    current_crontab=$(crontab -l 2>/dev/null || echo "")

    # Only comment if there's an uncommented line with both lis_path and cron.sh
    if echo "$current_crontab" | grep -v "^#" | grep -q "${lis_path}" && echo "$current_crontab" | grep -v "^#" | grep -q "cron.sh"; then
        print info "Commenting out old cron job..."
        log_action "Commenting out cron job for $lis_path"
        # Comment out any uncommented line containing both lis_path and cron.sh
        updated_crontab=$(echo "$current_crontab" | sed "s|^\([^#].*${lis_path//\//\\\/}.*cron\.sh.*\)|#\1|")
        echo "$updated_crontab" | crontab -
        cron_removed=1
        print success "Commented out old cron job"
        log_action "Successfully commented out cron job"
    else
        print info "No active cron job found to comment"
    fi

    # Clean up old generic intelis timer if it exists
    if systemctl list-unit-files | grep -q "^intelis\.timer"; then
        print info "Removing old generic intelis timer..."
        systemctl disable --now intelis.timer 2>/dev/null || true
        rm -f /etc/systemd/system/intelis.timer
        rm -f /etc/systemd/system/intelis.service
        systemctl daemon-reload
        print success "Cleaned up old generic intelis timer"
        log_action "Removed old generic intelis timer"
    fi

    # Clean up old intelis-scheduler if it exists
    if systemctl list-unit-files | grep -q "intelis-scheduler.timer"; then
        print info "Removing old intelis-scheduler timer..."
        systemctl disable --now intelis-scheduler.timer 2>/dev/null || true
        rm -f /etc/systemd/system/intelis-scheduler.timer
        rm -f /etc/systemd/system/intelis-scheduler.service
        systemctl daemon-reload
        print success "Cleaned up old intelis-scheduler"
        log_action "Removed old intelis-scheduler timer"
    fi

    # Enable timer
    if ! systemctl is-enabled --quiet "${service_name}.timer"; then
        systemctl enable "${service_name}.timer"
        print info "Enabled ${service_name}.timer"
        log_action "Enabled ${service_name}.timer"
    else
        print info "${service_name}.timer already enabled"
    fi

    # Start timer
    if ! systemctl is-active --quiet "${service_name}.timer"; then
        systemctl start "${service_name}.timer"
        print info "Started ${service_name}.timer"
        log_action "Started ${service_name}.timer"
    else
        print info "${service_name}.timer already running"
    fi

    # Summary of what happened
    local changes_made=0
    [[ "$service_changed" == "1" ]] && ((changes_made++))
    [[ "$timer_changed" == "1" ]] && ((changes_made++))
    [[ "$cron_removed" == "1" ]] && ((changes_made++))

    if [[ "$changes_made" -gt 0 ]]; then
        print success "✅ Scheduler configured for ${lis_path} ($changes_made changes made)"
    else
        print success "✅ Scheduler already configured correctly for ${lis_path}"
    fi

    print info "Monitor: journalctl -u ${service_name}.service -f"
    print info "Status: systemctl status ${service_name}.timer"
    log_action "Scheduler setup completed for $service_name (changes: $changes_made)"
}

# List active Intelis monitoring timers
list_timers() {
    print header "Intelis System Timers"

    local timers_found=false

    # Get timer info and filter for our timers
    while IFS= read -r line; do
        if [[ "$line" =~ (service-guard|resource-monitor|intelis) ]]; then
            echo "$line"
            timers_found=true
        fi
    done < <(systemctl list-timers --no-pager)

    if [[ "$timers_found" == "false" ]]; then
        print warning "No Intelis monitoring timers found"
    fi

    echo
    print info "To check logs: journalctl -u <service-name> -f"
    print info "To check status: systemctl status <timer-name>"
}


# Remove timer and service by name
remove_timer() {
    local timer_name="$1"

    if [[ -z "$timer_name" ]]; then
        print error "Usage: remove_timer <timer-name>"
        print info "Example: remove_timer intelis-vlsm"
        return 1
    fi

    print info "Removing ${timer_name} timer..."

    systemctl disable --now "${timer_name}.timer" 2>/dev/null || true
    rm -f "/etc/systemd/system/${timer_name}.timer"
    rm -f "/etc/systemd/system/${timer_name}.service"
    systemctl daemon-reload

    print success "${timer_name} timer removed"
}

# Remove all intelis timers
remove_all_intelis_timers() {
    print info "Removing all Intelis timers..."

    systemctl list-unit-files 'intelis*.timer' --no-legend \
    | awk '{print $1}' | xargs -r systemctl disable --now 2>/dev/null || true
    find /etc/systemd/system -maxdepth 1 \( -name 'intelis*.timer' -o -name 'intelis*.service' \) -type f -exec rm -f {} +

    systemctl daemon-reload

    print success "All Intelis timers removed"
}

# Remove all monitoring timers (guard + resource-monitor only)
remove_all_monitoring() {
    print info "Removing all monitoring timers..."

    for timer in service-guard resource-monitor; do
        systemctl disable --now "${timer}.timer" 2>/dev/null || true
        rm -f "/etc/systemd/system/${timer}.timer" "/etc/systemd/system/${timer}.service"
    done

    systemctl daemon-reload

    print success "All monitoring timers removed"
}





# Setup Intelis cron job (classic crontab, idempotent)
setup_intelis_cron() {
    local lis_path="$1"
    local cron_job="* * * * * cd ${lis_path} && ./cron.sh"

    # Ensure cron.sh is executable
    chmod +x "${lis_path}/cron.sh"

    # Load current root crontab without failing if none exists
    local current_crontab
    current_crontab="$(crontab -l 2>/dev/null || true)"

    # Already present?
    if printf '%s\n' "$current_crontab" | grep -Fxq "$cron_job"; then
        print info "Cron job for LIS already active. Skipping."
        log_action "Cron job for LIS already active. Skipped."
        return 0
    fi

    # Remove any existing (active or commented) similar entry
    local updated_crontab
    updated_crontab="$(
        printf '%s\n' "$current_crontab" |
        sed -E "/^[[:space:]]*#?[[:space:]]*\*[[:space:]]\*[[:space:]]\*[[:space:]]\*[[:space:]]\*[[:space:]]+cd[[:space:]]+$(printf '%s' "${lis_path}" | sed 's|/|\\/|g')[[:space:]]+&&[[:space:]]+\\./cron\\.sh$/d"
    )"

    # Write back crontab with our job appended
    {
        printf '%s\n' "$updated_crontab"
        printf '%s\n' "$cron_job"
    } | crontab -

    print success "Cron job for LIS added/replaced in root's crontab."
    log_action "Cron job for LIS added/replaced in root's crontab."
}



ensure_path() {
    case ":$PATH:" in
        *":/usr/local/bin:"*) ;; # already present
        *) export PATH="/usr/local/bin:$PATH" ;;
    esac
}


ensure_switch_php() {
    if command -v switch-php >/dev/null 2>&1; then
        return 0
    fi
    echo "switch-php not found; installing…"
    download_file "/usr/local/bin/switch-php" "https://raw.githubusercontent.com/deforay/utility-scripts/master/php/switch-php"
    chmod +x /usr/local/bin/switch-php
}


# Writable COMPOSER_HOME for www-data. The www-data passwd home (/var/www) is
# root-owned and not writable by www-data, and bare `sudo -u www-data` leaves
# HOME=/root (also unwritable), so composer otherwise runs cache-less, re-
# downloads every package each run, and emits "Cannot create cache directory"
# warnings. A dedicated www-data-owned home gives a shared, persistent package
# cache across all instances in a run.
WWW_DATA_COMPOSER_HOME="${WWW_DATA_COMPOSER_HOME:-/var/www/.composer}"

# Run composer as www-data with that writable COMPOSER_HOME. The mkdir/chown is
# idempotent and self-heals if the dir is missing or mis-owned. Use this for
# EVERY composer invocation that must run as www-data (setup.sh + upgrade.sh).
wwwdata_composer() {
    mkdir -p "$WWW_DATA_COMPOSER_HOME" 2>/dev/null || true
    chown www-data:www-data "$WWW_DATA_COMPOSER_HOME" 2>/dev/null || true
    sudo -u www-data env COMPOSER_HOME="$WWW_DATA_COMPOSER_HOME" composer "$@"
}

ensure_composer() {
    ensure_path

    if command -v composer >/dev/null 2>&1; then
        echo "✓ Composer found: $(command -v composer)"
        return 0
    fi

    echo "Composer not on PATH. Using switch-php to install it…"
    ensure_switch_php

    TARGET_PHP="${TARGET_PHP:-8.4}"
    switch-php "$TARGET_PHP"

    # Re-check PATH; some cron envs miss /usr/local/bin, so add a safety symlink
    if ! command -v composer >/dev/null 2>&1; then
        if [ -x /usr/local/bin/composer ] && [ -w /usr/bin ]; then
            if [ ! -e /usr/bin/composer ] || [ "$(readlink -f /usr/bin/composer)" != "/usr/local/bin/composer" ]; then
            ln -sf /usr/local/bin/composer /usr/bin/composer
            fi
        fi
    fi

    # Fallback: verified install if still missing after switch-php
    if ! command -v composer >/dev/null 2>&1; then
    print warning "Composer still missing after switch-php; installing verified global composer…"

    sig="$(curl -fsSL https://composer.github.io/installer.sig)" || {
        print error "Failed to fetch Composer installer signature."; exit 1; }

    installer="$(mktemp)"
    curl -fsSL https://getcomposer.org/installer -o "$installer" || {
        print error "Failed to download Composer installer."; rm -f "$installer"; exit 1; }

    actual="$(php -r "echo hash_file('sha384', '${installer}');")"
    if [ "$sig" != "$actual" ]; then
        print error "Composer installer signature mismatch."; rm -f "$installer"; exit 1
    fi

    php "$installer" --no-ansi --quiet --install-dir=/usr/local/bin --filename=composer || {
        print error "Composer installation failed."; rm -f "$installer"; exit 1; }
    rm -f "$installer"
    fi
    print success "✓ Composer installed: $(command -v composer)"
    export COMPOSER_ALLOW_SUPERUSER=1
}

# --- Ensure OPcache is installed and enabled for Apache (don’t rely on php -m) ---
ensure_opcache() {
    local ver="${desired_php_version:-8.4}"
    local pkg="php${ver}-opcache"
    local apache_ini_glob="/etc/php/${ver}/apache2/conf.d/*opcache.ini"
    local installed enabled

    # Is the package installed?
    if dpkg-query -W -f='${Status}\n' "$pkg" 2>/dev/null | grep -q "install ok installed"; then
        installed=true
    else
        installed=false
    fi

    # Is it enabled for Apache (conf.d link/file exists)?
    if ls $apache_ini_glob >/dev/null 2>&1; then
        enabled=true
    else
        enabled=false
    fi

    if $installed && $enabled; then
        print success "OPcache already installed and enabled for PHP ${ver} (Apache); skipping."
        return 0
    fi

    if ! $installed; then
        print info "Installing OPcache for PHP ${ver}…"
        apt-get update -y
        apt-get install -y "$pkg" || true
    fi

    if ! $enabled; then
        print info "Enabling OPcache for PHP ${ver} (Apache)…"
        phpenmod -v "$ver" -s apache2 opcache 2>/dev/null || phpenmod opcache 2>/dev/null || true
    fi

    print success "OPcache is ready for PHP ${ver} (Apache)."
}


setup_mysql_config() {
    local config_file="$1"
    local mysql_cnf="/root/.my.cnf"

    if [ ! -f "$mysql_cnf" ] && [ -f "$config_file" ]; then
        local pw=$(php -r "error_reporting(0);\$c=@include '$config_file';echo isset(\$c['database']['password'])?trim(\$c['database']['password']):'';")
        if [ -n "$pw" ]; then
            cat > "$mysql_cnf" << 'EOF'
[client]
user=root
EOF
            printf "password=%s\n" "$pw" >> "$mysql_cnf"
            chmod 600 "$mysql_cnf"
            return 0
        fi
    fi
    return 1
}

# Verify the CLI PHP that Composer will use has every extension we depend on,
# and self-heal the most common failure: a hardening profile that blacklists
# the (compiled-in) "phar" extension via disable_classes/disable_functions,
# which makes Composer abort with "PHP's phar extension is missing." before it
# can do anything. Call this BEFORE any composer invocation.
#
# Usage: ensure_php_cli_extensions <php_version>
# Returns 0 when all required extensions are loaded, 1 (after attempting a fix)
# when "phar" still cannot be loaded — callers should treat that as fatal.
ensure_php_cli_extensions() {
    local php_version="${1:-8.4}"
    # Extensions Composer + VLSM need at the CLI. "phar" is the one Composer
    # itself refuses to start without; the rest fail later and more obscurely.
    local required=(phar mbstring openssl curl json zip)

    print info "Verifying CLI PHP extensions for Composer..."

    # Helper: is a single extension loaded in the *CLI* php on PATH?
    _php_cli_has_ext() {
        php -r "exit(extension_loaded('$1') ? 0 : 1);" >/dev/null 2>&1
    }

    # First pass: collect what's missing.
    local missing=()
    local ext
    for ext in "${required[@]}"; do
        _php_cli_has_ext "$ext" || missing+=("$ext")
    done

    if [ ${#missing[@]} -eq 0 ]; then
        print success "All required CLI PHP extensions are present."
        return 0
    fi

    print warning "Missing CLI PHP extension(s): ${missing[*]}"

    # Self-heal phar: it ships compiled into php-cli, so if it's "missing" it is
    # almost always blacklisted in an ini under the CLI conf.d/ tree. Find and
    # neutralise any disable_classes/disable_functions line that names Phar/phar.
    local cli_ini_dirs=(
        "/etc/php/${php_version}/cli/conf.d"
        "/etc/php/${php_version}/cli"
    )
    if printf '%s\n' "${missing[@]}" | grep -qx 'phar'; then
        local ini
        while IFS= read -r ini; do
            [ -n "$ini" ] || continue
            print warning "Found phar blacklisted in ${ini}; commenting it out."
            cp "$ini" "${ini}.bak.$(date +%Y%m%d%H%M%S)" 2>/dev/null || true
            # Comment any disable_classes/disable_functions line mentioning phar.
            sed -i -E '/^[[:space:]]*disable_(classes|functions)[[:space:]]*=.*[Pp]har/ s/^/;/' "$ini"
        done < <(grep -rEil 'disable_(classes|functions)[[:space:]]*=.*phar' "${cli_ini_dirs[@]}" 2>/dev/null)
    fi

    # Re-check after the heal attempt.
    missing=()
    for ext in "${required[@]}"; do
        _php_cli_has_ext "$ext" || missing+=("$ext")
    done

    if [ ${#missing[@]} -eq 0 ]; then
        print success "CLI PHP extensions resolved."
        return 0
    fi

    # phar still missing is fatal — Composer cannot run. Give an actionable map
    # instead of letting the raw "phar extension is missing" error fly by.
    if printf '%s\n' "${missing[@]}" | grep -qx 'phar'; then
        print error "Composer cannot run: the 'phar' extension is not loaded in the CLI PHP."
        print info  "Diagnose and fix on this machine, then re-run:"
        print info  "  1. php --ini                       # which ini files load"
        print info  "  2. php -i | grep -Ei 'disable_(functions|classes)|suhosin'"
        print info  "  3. Remove 'Phar'/phar from any disable_classes/disable_functions line in"
        print info  "     /etc/php/${php_version}/cli/ (and conf.d/), or: apt-get install --reinstall php${php_version}-cli"
        print info  "  4. Confirm: php -r 'var_dump(extension_loaded(\"phar\"));'  # expect bool(true)"
        print info  "  Also check 'which -a php' / 'update-alternatives --config php' — a stray older php may be first on PATH."
        return 1
    fi

    # Non-phar extensions missing: warn but let Composer proceed (it may surface
    # a clearer per-package requirement, and these are usually apt-installable).
    print warning "Composer will proceed, but these extensions are still missing: ${missing[*]}"
    print info    "Install with: apt-get install $(printf 'php%s-%s ' "${php_version}" "${missing[@]}")"
    return 0
}

# Configure PHP INI settings for production use
# Usage: configure_php_ini <php_version>
# Example: configure_php_ini 8.4
configure_php_ini() {
    local php_version="${1:-8.4}"

    print header "Configuring PHP ${php_version}"

    # Define desired PHP settings
    local desired_error_reporting="error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING"
    local desired_display_errors="display_errors = Off"
    local desired_log_errors="log_errors = On"
    local desired_post_max_size="post_max_size = 1G"
    local desired_upload_max_filesize="upload_max_filesize = 1G"
    local desired_strict_mode="session.use_strict_mode = 1"
    local desired_sid_length="session.sid_length = 48"
    local desired_sid_bits="session.sid_bits_per_character = 6"
    local desired_gc_maxlifetime="session.gc_maxlifetime = 28800"
    local desired_expose_php="expose_php = Off"
    local desired_opcache_enable="opcache.enable=1"
    local desired_opcache_enable_cli="opcache.enable_cli=0"
    local desired_opcache_memory="opcache.memory_consumption=256"
    local desired_opcache_max_files="opcache.max_accelerated_files=40000"
    local desired_opcache_validate="opcache.validate_timestamps=0"
    local desired_opcache_save_comments="opcache.save_comments=1"
    local desired_opcache_jit="opcache.jit=disable"
    local desired_opcache_interned="opcache.interned_strings_buffer=16"
    local desired_opcache_override="opcache.enable_file_override=1"
    # PCRE JIT needs to mmap executable memory, which AppArmor on Ubuntu 26.06+
    # and other hardened kernels refuse. Disabling avoids "Allocation of JIT
    # memory failed" fatals from RegexIterator / preg_* at request time.
    local desired_pcre_jit="pcre.jit=0"

    # Inner function to update a single PHP ini file
    _update_php_ini_file() {
        local ini_file=$1
        local timestamp
        timestamp=$(date +%Y%m%d%H%M%S)
        local backup_file="${ini_file}.bak.${timestamp}"
        local changes_needed=false

        print info "Checking PHP settings in $ini_file..."

        # Check which settings are already correctly set
        local er_set de_set le_set pms_set umf_set sm_set sid_len_set sid_bits_set gc_maxlifetime_set expose_set
        local opcache_enable_set opcache_enable_cli_set opcache_memory_set opcache_max_files_set
        local opcache_validate_set opcache_save_comments_set opcache_jit_set opcache_interned_set opcache_override_set
        local pcre_jit_set

        er_set=$(grep -q "^${desired_error_reporting}$" "$ini_file" && echo true || echo false)
        de_set=$(grep -q "^${desired_display_errors}$" "$ini_file" && echo true || echo false)
        le_set=$(grep -q "^${desired_log_errors}$" "$ini_file" && echo true || echo false)
        pms_set=$(grep -q "^${desired_post_max_size}$" "$ini_file" && echo true || echo false)
        umf_set=$(grep -q "^${desired_upload_max_filesize}$" "$ini_file" && echo true || echo false)
        sm_set=$(grep -q "^${desired_strict_mode}$" "$ini_file" && echo true || echo false)
        sid_len_set=$(grep -q "^${desired_sid_length}$" "$ini_file" && echo true || echo false)
        sid_bits_set=$(grep -q "^${desired_sid_bits}$" "$ini_file" && echo true || echo false)
        gc_maxlifetime_set=$(grep -q "^${desired_gc_maxlifetime}$" "$ini_file" && echo true || echo false)
        expose_set=$(grep -q "^${desired_expose_php}$" "$ini_file" && echo true || echo false)
        opcache_enable_set=$(grep -q "^${desired_opcache_enable}$" "$ini_file" && echo true || echo false)
        opcache_enable_cli_set=$(grep -q "^${desired_opcache_enable_cli}$" "$ini_file" && echo true || echo false)
        opcache_memory_set=$(grep -q "^${desired_opcache_memory}$" "$ini_file" && echo true || echo false)
        opcache_max_files_set=$(grep -q "^${desired_opcache_max_files}$" "$ini_file" && echo true || echo false)
        opcache_validate_set=$(grep -q "^${desired_opcache_validate}$" "$ini_file" && echo true || echo false)
        opcache_save_comments_set=$(grep -q "^${desired_opcache_save_comments}$" "$ini_file" && echo true || echo false)
        opcache_jit_set=$(grep -q "^${desired_opcache_jit}$" "$ini_file" && echo true || echo false)
        opcache_interned_set=$(grep -q "^${desired_opcache_interned}$" "$ini_file" && echo true || echo false)
        opcache_override_set=$(grep -q "^${desired_opcache_override}$" "$ini_file" && echo true || echo false)
        pcre_jit_set=$(grep -q "^${desired_pcre_jit}$" "$ini_file" && echo true || echo false)

        # If ANY are missing, we need to rewrite
        if [ "$er_set" = false ] || [ "$de_set" = false ] || [ "$le_set" = false ] || [ "$pms_set" = false ] || [ "$umf_set" = false ] || [ "$sm_set" = false ] \
            || [ "$sid_len_set" = false ] || [ "$sid_bits_set" = false ] || [ "$gc_maxlifetime_set" = false ] \
            || [ "$expose_set" = false ] \
            || [ "$opcache_enable_set" = false ] || [ "$opcache_enable_cli_set" = false ] || [ "$opcache_memory_set" = false ] \
            || [ "$opcache_max_files_set" = false ] || [ "$opcache_validate_set" = false ] || [ "$opcache_save_comments_set" = false ] || [ "$opcache_jit_set" = false ] \
            || [ "$opcache_interned_set" = false ] || [ "$opcache_override_set" = false ] \
            || [ "$pcre_jit_set" = false ]; then
            changes_needed=true
            cp "$ini_file" "$backup_file"
            print info "Changes needed. Backup created at $backup_file"
        fi

        if [ "$changes_needed" = true ]; then
            local temp_file
            temp_file=$(mktemp)

            # Rewrite file, commenting old keys and inserting desired ones once
            while IFS= read -r line; do
                if [[ "$line" =~ ^[[:space:]]*error_reporting[[:space:]]*= ]] && [ "$er_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_error_reporting" >>"$temp_file"; er_set=true
                elif [[ "$line" =~ ^[[:space:]]*display_errors[[:space:]]*= ]] && [ "$de_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_display_errors" >>"$temp_file"; de_set=true
                elif [[ "$line" =~ ^[[:space:]]*log_errors[[:space:]]*= ]] && [ "$le_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_log_errors" >>"$temp_file"; le_set=true
                elif [[ "$line" =~ ^[[:space:]]*post_max_size[[:space:]]*= ]] && [ "$pms_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_post_max_size" >>"$temp_file"; pms_set=true
                elif [[ "$line" =~ ^[[:space:]]*upload_max_filesize[[:space:]]*= ]] && [ "$umf_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_upload_max_filesize" >>"$temp_file"; umf_set=true
                elif [[ "$line" =~ ^[[:space:]]*session\.use_strict_mode[[:space:]]*= ]] && [ "$sm_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_strict_mode" >>"$temp_file"; sm_set=true
                elif [[ "$line" =~ ^[[:space:]]*session\.sid_length[[:space:]]*= ]] && [ "$sid_len_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_sid_length" >>"$temp_file"; sid_len_set=true
                elif [[ "$line" =~ ^[[:space:]]*session\.sid_bits_per_character[[:space:]]*= ]] && [ "$sid_bits_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_sid_bits" >>"$temp_file"; sid_bits_set=true
                elif [[ "$line" =~ ^[[:space:]]*session\.gc_maxlifetime[[:space:]]*= ]] && [ "$gc_maxlifetime_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_gc_maxlifetime" >>"$temp_file"; gc_maxlifetime_set=true
                elif [[ "$line" =~ ^[[:space:]]*expose_php[[:space:]]*= ]] && [ "$expose_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_expose_php" >>"$temp_file"; expose_set=true
                elif [[ "$line" =~ ^[[:space:]]*opcache\.enable[[:space:]]*= ]] && [ "$opcache_enable_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_opcache_enable" >>"$temp_file"; opcache_enable_set=true
                elif [[ "$line" =~ ^[[:space:]]*opcache\.enable_cli[[:space:]]*= ]] && [ "$opcache_enable_cli_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_opcache_enable_cli" >>"$temp_file"; opcache_enable_cli_set=true
                elif [[ "$line" =~ ^[[:space:]]*opcache\.memory_consumption[[:space:]]*= ]] && [ "$opcache_memory_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_opcache_memory" >>"$temp_file"; opcache_memory_set=true
                elif [[ "$line" =~ ^[[:space:]]*opcache\.max_accelerated_files[[:space:]]*= ]] && [ "$opcache_max_files_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_opcache_max_files" >>"$temp_file"; opcache_max_files_set=true
                elif [[ "$line" =~ ^[[:space:]]*opcache\.validate_timestamps[[:space:]]*= ]] && [ "$opcache_validate_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_opcache_validate" >>"$temp_file"; opcache_validate_set=true
                elif [[ "$line" =~ ^[[:space:]]*opcache\.save_comments[[:space:]]*= ]] && [ "$opcache_save_comments_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_opcache_save_comments" >>"$temp_file"; opcache_save_comments_set=true
                elif [[ "$line" =~ ^[[:space:]]*opcache\.jit[[:space:]]*= ]] && [ "$opcache_jit_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_opcache_jit" >>"$temp_file"; opcache_jit_set=true
                elif [[ "$line" =~ ^[[:space:]]*opcache\.interned_strings_buffer[[:space:]]*= ]] && [ "$opcache_interned_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_opcache_interned" >>"$temp_file"; opcache_interned_set=true
                elif [[ "$line" =~ ^[[:space:]]*opcache\.enable_file_override[[:space:]]*= ]] && [ "$opcache_override_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_opcache_override" >>"$temp_file"; opcache_override_set=true
                elif [[ "$line" =~ ^[[:space:]]*pcre\.jit[[:space:]]*= ]] && [ "$pcre_jit_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_pcre_jit" >>"$temp_file"; pcre_jit_set=true
                else
                    echo "$line" >>"$temp_file"
                fi
            done <"$ini_file"

            # Append any directives that were entirely missing
            [ "$er_set" = true ] || echo "$desired_error_reporting" >>"$temp_file"
            [ "$de_set" = true ] || echo "$desired_display_errors" >>"$temp_file"
            [ "$le_set" = true ] || echo "$desired_log_errors" >>"$temp_file"
            [ "$pms_set" = true ] || echo "$desired_post_max_size" >>"$temp_file"
            [ "$umf_set" = true ] || echo "$desired_upload_max_filesize" >>"$temp_file"
            [ "$sm_set" = true ] || echo "$desired_strict_mode" >>"$temp_file"
            [ "$sid_len_set" = true ] || echo "$desired_sid_length" >>"$temp_file"
            [ "$sid_bits_set" = true ] || echo "$desired_sid_bits" >>"$temp_file"
            [ "$gc_maxlifetime_set" = true ] || echo "$desired_gc_maxlifetime" >>"$temp_file"
            [ "$expose_set" = true ] || echo "$desired_expose_php" >>"$temp_file"
            [ "$opcache_enable_set" = true ] || echo "$desired_opcache_enable" >>"$temp_file"
            [ "$opcache_enable_cli_set" = true ] || echo "$desired_opcache_enable_cli" >>"$temp_file"
            [ "$opcache_memory_set" = true ] || echo "$desired_opcache_memory" >>"$temp_file"
            [ "$opcache_max_files_set" = true ] || echo "$desired_opcache_max_files" >>"$temp_file"
            [ "$opcache_validate_set" = true ] || echo "$desired_opcache_validate" >>"$temp_file"
            [ "$opcache_save_comments_set" = true ] || echo "$desired_opcache_save_comments" >>"$temp_file"
            [ "$opcache_jit_set" = true ] || echo "$desired_opcache_jit" >>"$temp_file"
            [ "$opcache_interned_set" = true ] || echo "$desired_opcache_interned" >>"$temp_file"
            [ "$opcache_override_set" = true ] || echo "$desired_opcache_override" >>"$temp_file"
            [ "$pcre_jit_set" = true ] || echo "$desired_pcre_jit" >>"$temp_file"

            mv "$temp_file" "$ini_file"
            print success "Updated PHP settings in $ini_file"

            # Remove backup once successful
            if [ -f "$backup_file" ]; then
                rm "$backup_file"
                print info "Removed backup file $backup_file"
            fi
        else
            print info "PHP settings are already correctly set in $ini_file"
        fi
    }

    # Apply changes to PHP configuration files
    for phpini in /etc/php/${php_version}/apache2/php.ini /etc/php/${php_version}/cli/php.ini; do
        if [ -f "$phpini" ]; then
            _update_php_ini_file "$phpini"
        else
            print warning "PHP configuration file not found: $phpini"
        fi
    done
}
