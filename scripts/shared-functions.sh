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
    # Escape for a PHP single-quoted string that is delivered through a sed
    # replacement. Order matters, and the two layers are separate:
    #
    #   1. PHP layer: \ and ' must be backslash-escaped inside '...'.
    #   2. sed layer: sed consumes one level of backslashes in the replacement
    #      text, so every backslash produced by step 1 has to be doubled or the
    #      PHP escaping is eaten on the way through and the file stops parsing.
    #      (A password containing an apostrophe used to write a broken config.)
    local value="$1"
    value=${value//\\/\\\\}   # PHP: escape backslashes
    value=${value//\'/\\\'}   # PHP: escape single quotes
    value=${value//\\/\\\\}   # sed: double every backslash so one survives
    value=${value//|/\\|}     # sed: escape the delimiter used by our s|| commands
    value=${value//&/\\&}     # sed: & means "the whole match" in a replacement
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

# Pick the default PHP major.minor for the running OS.
# Ubuntu 24.04 and older carry (and ondrej's PPA still builds) PHP 8.4, our
# well-tested baseline. Ubuntu 26.04 dropped PHP 8.4 — its archive/PPA ship
# PHP 8.5 instead — so on 26.04+ we default to 8.5. The app itself supports
# 8.2–8.5 (see composer.json), so both are safe; this only chooses the default
# when the operator hasn't pinned one with --php.
default_php_version_for_os() {
    local ubuntu_version
    ubuntu_version=$(lsb_release -rs 2>/dev/null || echo "")

    # Can't detect the release -> stick with the current baseline.
    if [[ -z "$ubuntu_version" ]]; then
        echo "8.4"
        return
    fi

    # 26.04 and newer no longer provide PHP 8.4; use 8.5.
    if [[ "$(printf '%s\n' "26.04" "$ubuntu_version" | sort -V | head -n1)" == "26.04" ]]; then
        echo "8.5"
    else
        echo "8.4"
    fi
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

# What a lab upgrades TO.
#
# Labs tracked the tip of master, which made merging and shipping the same
# event: anything pushed reached every installation on its next upgrade, with no
# point in between where somebody decided it was ready. Now they follow the
# newest vN.N.N tag, so publishing is a deliberate act — and a cheap one, since
# `composer version patch -- -y` plus a tag push is the whole ceremony. An
# urgent fix still ships in the time it takes to type it.
#
# INTELIS_TRACK overrides this:
#   INTELIS_TRACK=master     the old behaviour, for hotfixing one lab from tip
#   INTELIS_TRACK=v5.7.1     pin an installation to an exact release
#
# Falls back to master when no release tag exists yet, so an instance is never
# left unable to update because nothing has been tagged.
INTELIS_TRACK="${INTELIS_TRACK:-latest}"

# resolve_intelis_ref — echo the git ref this machine should upgrade to.
#
# The tag filter is deliberately strict: ^v[0-9]+\.[0-9]+\.[0-9]+$ excludes the
# vendor-latest release tag, which is a build artefact and not a version of the
# application, and excludes pre-release suffixes. sort -V so v5.10.0 ranks above
# v5.7.1 rather than below it, which a lexical sort gets backwards.
resolve_intelis_ref() {
    case "$INTELIS_TRACK" in
        master) printf 'refs/heads/master'; return 0 ;;
        v[0-9]*) printf 'refs/tags/%s' "$INTELIS_TRACK"; return 0 ;;
    esac

    local newest
    newest=$(git ls-remote --tags "$MASTER_GIT_URL" 2>/dev/null \
        | sed 's|.*refs/tags/||' \
        | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' \
        | sort -V | tail -1)

    if [ -n "$newest" ]; then
        printf 'refs/tags/%s' "$newest"
    else
        printf 'refs/heads/master'
    fi
}

INTELIS_REF="${INTELIS_REF:-$(resolve_intelis_ref)}"
MASTER_TARBALL_URL="${MASTER_TARBALL_URL:-https://codeload.github.com/deforay/intelis/tar.gz/${INTELIS_REF}}"

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
        echo "source: updating mirror to ${INTELIS_REF} (delta fetch — only changed files)"
        if run_git -C "$INTELIS_SRC_DIR" fetch --depth 1 origin "$INTELIS_REF" &&
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
        echo "source: shallow-cloning ${INTELIS_REF} into source mirror"
        local attempt
        # --branch takes a tag as well as a branch name, so the short ref works
        # for both; strip the refs/ prefix the resolver returns.
        local clone_ref="${INTELIS_REF#refs/heads/}"
        clone_ref="${clone_ref#refs/tags/}"
        for attempt in 1 2 3; do
            rm -rf "$INTELIS_SRC_DIR"
            mkdir -p "$(dirname "$INTELIS_SRC_DIR")"
            if run_git clone --depth 1 --single-branch --branch "$clone_ref" \
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


# app_heavy_dirs — the subtrees of an install that no upgrade ever writes into
# and that are unbounded in size, printed one absolute path per line (only the
# ones that actually exist).
#
# Every full-tree pass over an install — the ACL pass, the ownership pass, the
# rollback snapshot — is O(files), and on a mature lab almost all of those files
# are in here: the audit-trail alone is one entry per sample. Walking them is
# what turns a four-minute upgrade into a forty-minute one, and it buys nothing,
# because the application wrote every one of them as www-data in the first
# place. The pruned roots get an owner and a default ACL instead, so whatever is
# created in them next inherits the right access without anyone re-walking what
# is already there.
#
# The bare names are the pre-var/ locations of the same content. The migration
# into var/ runs in the background during an upgrade, so a passthrough that only
# knows the var/ names misses the whole audit-trail on any instance that has not
# been migrated yet — which is exactly the long-running instance where it costs
# the most.
app_heavy_dirs() {
    local lp="${1%/}"
    local d
    for d in var/audit-trail var/cache backups \
        audit-trail logs cache metadata \
        public/uploads public/temporary public/files; do
        [ -d "${lp}/${d}" ] && printf '%s\n' "${lp}/${d}"
    done
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
    local mode=${2:-"full"}          # full | quick | minimal | deep
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

    # Common excludes, pruned rather than filtered.
    #
    # `-not -path` only stops find PRINTING a match; find still descends the
    # directory and stats every file in it, which is the whole cost. -prune
    # stops it walking in at all.
    #
    # What is pruned, and why each one is safe to leave alone:
    #
    #   .git, node_modules  — not part of the running application. .git alone
    #                         can be 5k-30k files on a long-running repo.
    #   var/audit-trail     — one compressed CSV per sample, so on a mature
    #                         instance this is most of the files in the whole
    #                         install. No upgrade ever writes to them, and the
    #                         application only ever reads them back.
    #   backups             — same: written by the backup task, never by an
    #                         upgrade, and large.
    #   audit-trail, logs,  — the pre-var/ locations of the same content. This
    #   cache, metadata       pass runs while the migration into var/ is still
    #                         going, so on an instance that has not been
    #                         migrated yet the audit-trail is sitting at the
    #                         root under its old name and none of the var/
    #                         prunes match it.
    #   var/cache           — regenerable, and the default ACL below is what
    #                         actually keeps it clearable; walking every shard
    #                         buys nothing.
    #   public/uploads,     — user-uploaded data. Written by the application,
    #   public/temporary,     never by an upgrade, and unbounded.
    #   public/files
    #
    # Everything pruned gets an ACL on the directory itself plus a DEFAULT ACL,
    # so everything created in them from here on inherits the right access
    # without anything ever having to walk what is already there. `-m deep`
    # still sweeps them, for the one-off case where existing files really do
    # have the wrong owner.
    local root="${path%/}"
    local -a PRUNE=(-path "*/.git" -o -path "*/node_modules")
    local -a HEAVY=()

    if [[ "$mode" != "deep" ]]; then
        mapfile -t HEAVY < <(app_heavy_dirs "$root")
        local heavy
        for heavy in "${HEAVY[@]}"; do
            PRUNE+=(-o -path "$heavy")
        done
    fi

    local -a EXCLUDES=(\( "${PRUNE[@]}" \) -prune -o)

    local pids=()

    # The prune expression has to come before the -type test, or find has
    # already descended by the time the test is evaluated.
    case "$mode" in
        full|deep)
            # Directories: rwx to user + www-data
            find "$path" "${EXCLUDES[@]}" -type d -print0 \
                | $CPU_NICE $IO_NICE xargs -0 -n "$BATCH" -P "$PARALLEL" \
                    setfacl -m "u:${who}:rwx,u:www-data:rwx" 2>>/tmp/acl_failures.log &
            pids+=($!)

            # Files: rw to user + www-data
            find "$path" "${EXCLUDES[@]}" -type f -print0 \
                | $CPU_NICE $IO_NICE xargs -0 -n "$BATCH" -P "$PARALLEL" \
                    setfacl -m "u:${who}:rw,u:www-data:rw" 2>>/tmp/acl_failures.log &
            pids+=($!)
        ;;
        quick)
            find "$path" "${EXCLUDES[@]}" -type d -print0 \
                | $CPU_NICE $IO_NICE xargs -0 -n "$BATCH" -P "$PARALLEL" \
                    setfacl -m "u:${who}:rwx,u:www-data:rwx" 2>>/tmp/acl_failures.log &
            pids+=($!)

            find "$path" "${EXCLUDES[@]}" -type f -name "*.php" -print0 \
                | $CPU_NICE $IO_NICE xargs -0 -n "$BATCH" -P "$PARALLEL" \
                    setfacl -m "u:${who}:rw,u:www-data:rw" 2>>/tmp/acl_failures.log &
            pids+=($!)
        ;;
        minimal)
            find "$path" "${EXCLUDES[@]}" -type d -print0 \
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

    # Default ACLs on the directories that get written at runtime.
    #
    # An access ACL only covers entries that already exist. What keeps causing
    # trouble is the ones that do not yet: the scheduled tasks run from root's
    # crontab, so composer and the application create var/cache entries -- and
    # the sharded subdirectories holding them -- owned by root. Unlinking a file
    # needs write access to the DIRECTORY that holds it, not ownership of the
    # file, so a root-owned subdirectory is what stops www-data clearing the
    # cache, and what surfaces as "N cache entries could not be removed while
    # running as www-data (N owned by root)".
    #
    # A default ACL is inherited by everything created underneath from here on,
    # and inheritance is transitive: a directory created inside one carries the
    # default forward to its own children. So setting it high enough is enough
    # to keep every future entry removable, whoever writes it.
    local acl_default="u:${who}:rwx,u:www-data:rwx,d:u:${who}:rwx,d:u:www-data:rwx"
    local dflt_dir

    # The runtime directories are swept in full, directories only. They are
    # small, and going all the way down repairs an instance whose existing
    # cache shards are already root-owned rather than only fixing the next
    # entries to be written.
    for dflt_dir in var/cache var/logs var/temporary public/temporary public/uploads; do
        [[ -d "${root}/${dflt_dir}" ]] || continue
        find "${root}/${dflt_dir}" -type d -print0 \
            | xargs -0 -r setfacl -m "$acl_default" 2>>/tmp/acl_failures.log || true
    done

    # The pruned trees get the roots and their immediate children only -- going
    # deeper is exactly the walk the pruning exists to avoid, and nothing but
    # the application writes there anyway.
    for dflt_dir in "${HEAVY[@]}"; do
        [[ -d "$dflt_dir" ]] || continue
        find "$dflt_dir" -maxdepth 1 -type d -print0 \
            | xargs -0 -r setfacl -m "$acl_default" 2>>/tmp/acl_failures.log || true
    done

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

# --- MySQL option-file editing ------------------------------------------------
#
# MySQL reads an option file top to bottom and every option belongs to the
# [section] heading above it. Appending a server setting to the end of the file
# therefore only does the right thing while the last heading in that file is
# [mysqld]. The moment anything adds a [client] or [mysql] section at the bottom
# - by hand, or by a tool setting default-character-set - every setting appended
# after it silently becomes a *client* option instead. Two things then go wrong
# at once and neither announces itself: the server never reads the tuning, so it
# runs on defaults while the file says otherwise, and every client (mysql, and
# mysqldump, and therefore the nightly backup) refuses to start with
# "unknown variable 'innodb_file_per_table=1'". These helpers write into the
# [mysqld] section rather than at the end of the file so that cannot happen.
#
# Option names treat - and _ as the same character, so every comparison here is
# made on a normalised name: character-set-server and character_set_server are
# one setting, not two, and matching only one of them would write a duplicate.

# Print the value <section> gives to <key>, and return 1 when the section does
# not set it at all. Absent and set-to-empty have to stay distinguishable:
# sql_mode is deliberately set to the empty string, while a missing sql_mode
# line means the server keeps its own non-empty default - opposite meanings that
# would otherwise both look like "". The last occurrence wins, which is how
# MySQL itself resolves a repeated option.
mysql_cnf_get_option() { # mysql_cnf_get_option <file> <section> <key>
    local file="$1" section="$2" key="$3" found

    [ -f "$file" ] || return 1

    # The "=" prefix is what separates found-and-empty from not-found; the value
    # itself can be anything, so nothing that could also be a value is usable as
    # the marker.
    found="$(
        awk -v want_section="$section" -v want_key="$key" '
            function norm(s) { gsub(/-/, "_", s); return tolower(s) }
            /^[[:space:]]*[#;!]/ { next }
            /^[[:space:]]*\[/ {
                s = $0
                sub(/^[[:space:]]*\[/, "", s)
                sub(/\].*$/, "", s)
                gsub(/[[:space:]]/, "", s)
                current = norm(s)
                next
            }
            current != norm(want_section) { next }
            {
                k = $0
                sub(/=.*$/, "", k)
                gsub(/[[:space:]]/, "", k)
                if (k == "" || norm(k) != norm(want_key)) next
                v = ""
                if (index($0, "=")) {
                    v = $0
                    sub(/^[^=]*=/, "", v)
                    sub(/^[[:space:]]+/, "", v)
                    sub(/[[:space:]]+$/, "", v)
                }
                value = v
                seen = 1
            }
            END { if (seen) printf "=%s", value }
        ' "$file" 2>/dev/null
    )"

    [ -n "$found" ] || return 1
    printf '%s' "${found#=}"
}

# Comment out every active occurrence of <key>, in every section of the file.
# Deliberately not limited to one section: a copy stranded under [client] is
# precisely the fault this exists to clear, and a stale copy anywhere else would
# only go on overriding the line we are about to write.
mysql_cnf_comment_option() { # mysql_cnf_comment_option <file> <key>
    local file="$1" key="$2" tmp

    [ -f "$file" ] || return 1

    tmp="$(mktemp)" || return 1
    awk -v want_key="$key" '
        function norm(s) { gsub(/-/, "_", s); return tolower(s) }
        /^[[:space:]]*[#;!]/ { print; next }
        /^[[:space:]]*\[/    { print; next }
        {
            k = $0
            sub(/=.*$/, "", k)
            gsub(/[[:space:]]/, "", k)
            if (k != "" && norm(k) == norm(want_key)) { print "#" $0; next }
            print
        }
    ' "$file" >"$tmp" 2>/dev/null || {
        rm -f "$tmp"
        return 1
    }

    # Written back through the original so the file keeps its owner and mode.
    cat "$tmp" >"$file" || {
        rm -f "$tmp"
        return 1
    }
    rm -f "$tmp"
}

# Add the lines in <lines-file> to the end of the [mysqld] section, creating the
# section when the file has none. Inserting before the next heading instead of
# at the end of the file is the entire point. The blank lines that trail the
# section are stepped over so that running this repeatedly does not open a
# widening gap in the middle of the file.
mysql_cnf_insert_mysqld_options() { # mysql_cnf_insert_mysqld_options <file> <lines-file>
    local file="$1" lines="$2" tmp

    [ -f "$file" ] && [ -s "$lines" ] || return 1

    tmp="$(mktemp)" || return 1
    awk -v lines_file="$lines" '
        function emit_new_lines(   line) {
            while ((getline line < lines_file) > 0) print line
            close(lines_file)
        }
        { text[NR] = $0 }
        /^[[:space:]]*\[/ {
            s = $0
            sub(/^[[:space:]]*\[/, "", s)
            sub(/\].*$/, "", s)
            gsub(/[[:space:]]/, "", s)
            # The last [mysqld] is the one to extend: when a file has two, the
            # later one has the final say on any option written in both.
            if (tolower(s) == "mysqld") { start = NR; stop = 0 }
            else if (start && !stop)    { stop = NR }
        }
        END {
            if (!start) {
                for (i = 1; i <= NR; i++) print text[i]
                if (NR > 0 && text[NR] !~ /^[[:space:]]*$/) print ""
                print "[mysqld]"
                emit_new_lines()
                exit
            }
            if (!stop) stop = NR + 1
            at = stop - 1
            while (at > start && text[at] ~ /^[[:space:]]*$/) at--
            for (i = 1; i <= at; i++) print text[i]
            emit_new_lines()
            for (i = at + 1; i <= NR; i++) print text[i]
        }
    ' "$file" >"$tmp" 2>/dev/null || {
        rm -f "$tmp"
        return 1
    }

    cat "$tmp" >"$file" || {
        rm -f "$tmp"
        return 1
    }
    rm -f "$tmp"
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
    print error "MySQL is still down after recovery attempts."
    mysql_diagnostics || true
    log_action "ensure_mysql_running: FAILED to recover MySQL"
    return 1
}

# Resolve the MySQL/MariaDB error log, which is where the reason for a refusal
# to start is actually written (the journal usually only says "failed").
mysql_error_log_path() {
    local candidate cnf
    for cnf in /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/my.cnf /etc/my.cnf /etc/mysql/mariadb.conf.d/50-server.cnf; do
        if [ -f "$cnf" ]; then
            candidate="$(awk -F= '/^[[:space:]]*log[-_]error[[:space:]]*=/ {gsub(/[[:space:]]/, "", $2); print $2; exit}' "$cnf" 2>/dev/null || true)"
            if [ -n "$candidate" ] && [ -f "$candidate" ]; then
                echo "$candidate"
                return 0
            fi
        fi
    done

    for candidate in /var/log/mysql/error.log /var/log/mysqld.log /var/log/mariadb/mariadb.log /var/log/mysql/mysql.err; do
        if [ -f "$candidate" ]; then
            echo "$candidate"
            return 0
        fi
    done

    return 1
}

# Explain why MySQL is down or unreachable.
#
# "Database not reachable" on its own is useless on a machine we cannot log in
# to, and these boxes are in labs on the other side of the world. So gather the
# evidence that actually distinguishes the handful of real causes — the OOM
# killer, a full disk, a config option mysqld refuses, a crash loop — print a
# verdict, and leave the full detail in a file the operator can send back.
#
# Every probe here is best-effort and individually guarded: this runs on a host
# that is already in trouble and must never itself abort the caller.
mysql_diagnostics() {
    local report="${1:-/var/log/intelis-mysql-diagnostics-$(date +%Y%m%d%H%M%S).log}"
    local unit err_log verdicts=() line
    local oom_hits="" disk_line="" avail_kb="" mem_total_kb="" pool_setting="" refusal=""

    command -v mysqladmin >/dev/null 2>&1 || return 0
    unit="$(mysql_unit_name)"

    {
        echo "=== InteLIS MySQL diagnostics: $(date +'%F %T') ==="
        echo "--- host ---"
        uname -a 2>/dev/null || true
        echo
        echo "--- unit: ${unit} ---"
        systemctl is-active "$unit" 2>/dev/null || true
        systemctl is-enabled "$unit" 2>/dev/null || true
        systemctl status "$unit" --no-pager -l 2>/dev/null | head -n 25 || true
        echo
        echo "--- journal (last 60 lines) ---"
        journalctl -u "$unit" -n 60 --no-pager 2>/dev/null || true
        echo
        echo "--- kernel: OOM / I/O errors (last 24h) ---"
        journalctl -k --since "-24 hours" --no-pager 2>/dev/null |
            grep -iE 'out of memory|oom.?kill|killed process|I/O error|EXT4-fs error' | tail -n 40 || true
        echo
        echo "--- memory ---"
        free -m 2>/dev/null || true
        echo
        echo "--- disk (data dir + root) ---"
        df -h /var/lib/mysql / 2>/dev/null || true
        df -i /var/lib/mysql / 2>/dev/null || true
        echo
        echo "--- innodb sizing in effect ---"
        grep -rhiE '^[[:space:]]*innodb_(buffer_pool_size|log_file_size|log_buffer_size)' /etc/mysql 2>/dev/null || true
        echo
        echo "--- recent config changes ---"
        ls -1t /etc/mysql/mysql.conf.d/mysqld.cnf.bak.* /etc/mysql/mysql.conf.d/mysqld.cnf.failed.* 2>/dev/null | head -n 5 || true
    } >"$report" 2>&1 || true

    err_log="$(mysql_error_log_path 2>/dev/null || true)"
    if [ -n "$err_log" ]; then
        {
            echo
            echo "--- error log: ${err_log} (last 80 lines) ---"
            tail -n 80 "$err_log" 2>/dev/null || true
        } >>"$report" 2>&1 || true
    fi

    # --- work out a verdict from what was collected ---

    oom_hits="$(journalctl -k --since "-24 hours" --no-pager 2>/dev/null |
        grep -iE 'oom.?kill|out of memory' | grep -ci mysql || true)"
    if [ -n "$oom_hits" ] && [ "$oom_hits" -gt 0 ] 2>/dev/null; then
        verdicts+=("The kernel OOM killer has killed mysqld (${oom_hits} event(s) in the last 24h). The server is running out of RAM — check innodb_buffer_pool_size against actual memory, and what else runs on this box.")
    fi

    avail_kb="$(df -Pk /var/lib/mysql 2>/dev/null | awk 'NR==2 {print $4}' || true)"
    if [ -n "$avail_kb" ] && [ "$avail_kb" -lt 524288 ] 2>/dev/null; then
        disk_line="$(df -h /var/lib/mysql 2>/dev/null | awk 'NR==2 {print $4" free of "$2}' || true)"
        verdicts+=("The MySQL data partition is nearly full (${disk_line}). InnoDB stops and the server shuts down when it cannot extend its files.")
    fi

    if [ -n "$err_log" ]; then
        refusal="$(grep -iE "unknown variable|unknown option|can't start server|aborting|permission denied|corrupt|table space|Cannot allocate memory" "$err_log" 2>/dev/null | tail -n 3 || true)"
        if [ -n "$refusal" ]; then
            verdicts+=("mysqld reported: ${refusal}")
        fi
    fi

    if [ -n "$(ls -1t /etc/mysql/mysql.conf.d/mysqld.cnf.failed.* 2>/dev/null | head -n 1 || true)" ]; then
        verdicts+=("A previous run already had to roll back the MySQL config (see mysqld.cnf.failed.* in /etc/mysql/mysql.conf.d), so the tuning this script applies is a suspect.")
    fi

    if [ "${#verdicts[@]}" -eq 0 ]; then
        # Total RAM vs configured buffer pool is worth stating even with no
        # smoking gun: it is the setting this script itself writes.
        mem_total_kb="$(awk '/MemTotal/ {print $2}' /proc/meminfo 2>/dev/null || true)"
        pool_setting="$(grep -rhiE '^[[:space:]]*innodb_buffer_pool_size' /etc/mysql 2>/dev/null | tail -n 1 || true)"
        print warning "No single obvious cause found. Total RAM: $(( ${mem_total_kb:-0} / 1024 ))MB; ${pool_setting:-innodb_buffer_pool_size not set in /etc/mysql}"
    else
        print error "Likely cause:"
        for line in "${verdicts[@]}"; do
            printf '    - %s\n' "$line"
        done
    fi

    print info "Full MySQL diagnostics written to: ${report}"
    print info "  Send that file along when reporting this, it has the service log, the error log, memory and disk."
    log_action "mysql_diagnostics written to ${report}"
    return 0
}


# Ask user yes/no
# ask_yes_no <question> [default] [timeout-seconds]
#
# There is no timeout unless one is asked for. There used to be a flat 15
# seconds, which could only ever fire when a human WAS at the keyboard —
# a run with no terminal returns the default at the top of this function and
# never reaches the read — so its whole effect was to answer for operators who
# stopped to think. On a question like "reuse your previous answers?" that is
# the machine making the decision the operator came to make.
ask_yes_no() {
    local prompt="$1"
    local default="${2:-no}"
    local timeout="${3:-0}"
    local answer

    # Normalize default
    default=$(echo "$default" | awk '{print tolower($0)}')
    [[ "$default" != "yes" && "$default" != "no" ]] && default="no"

    # If stdin is not a terminal, fallback to default
    if [ ! -t 0 ]; then
        [[ "$default" == "yes" ]] && return 0 || return 1
    fi

    # gum confirm has no timeout of its own, so it is only used for the
    # untimed case — which, since the timeout is now opt-in, is nearly all of
    # them. Its exit status is the answer: 0 for yes, 1 for no.
    if [ "$timeout" -eq 0 ] 2>/dev/null && [ "$(ui_renderer)" = "gum" ]; then
        local affirmative="Yes" negative="No" default_flag="--default=true"
        [ "$default" = "no" ] && default_flag="--default=false"
        gum confirm "$prompt" --affirmative "$affirmative" --negative "$negative" "$default_flag"
        return $?
    fi

    if [ "$timeout" -gt 0 ] 2>/dev/null; then
        echo -n "$prompt (y/n) [default: $default, auto in ${timeout}s]: "
        read -t "$timeout" answer
        if [ $? -ne 0 ]; then
            print info "No input received in ${timeout} seconds. Using default: $default"
            [[ "$default" == "yes" ]] && return 0 || return 1
        fi
    else
        echo -n "$prompt (y/n) [default: $default]: "
        if ! read -r answer; then
            [[ "$default" == "yes" ]] && return 0 || return 1
        fi
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

# Read any dotted key out of an instance config, e.g. database.username.
extract_config_value() {
    local config_file="$1" dotted_key="$2"
    [ -f "$config_file" ] || return 1
    php -r "
        error_reporting(0);
        \$config = include '$config_file';
        \$value = \$config;
        foreach (explode('.', '$dotted_key') as \$part) {
            if (!is_array(\$value) || !isset(\$value[\$part])) { echo ''; exit; }
            \$value = \$value[\$part];
        }
        echo is_scalar(\$value) ? trim((string) \$value) : '';
    " 2>/dev/null
}

# Repair a database password that was HTML-escaped on its way into the config.
#
# The app's input sanitizer is an HTML purifier, and until this was fixed it ran
# over the setup form's database fields too: a password of mko)(*&^ was written
# to config.production.php as mko)(*&amp;^. Every connection from that instance
# then fails with access denied, which surfaces as "database not reachable" and
# blocks upgrades on a machine that is otherwise fine.
#
# Only rewrites the file when the escaped password fails AND the decoded one is
# proven to work, so it cannot make a working instance worse. Returns 0 if it
# repaired the config.
repair_html_escaped_db_password() {
    local config_file="$1"
    local current decoded user host port db_name backup

    [ -f "$config_file" ] || return 1
    command -v mysql >/dev/null 2>&1 || return 1

    current="$(extract_mysql_password_from_config "$config_file" 2>/dev/null || true)"
    [ -n "$current" ] || return 1

    case "$current" in
        *"&amp;"* | *"&lt;"* | *"&gt;"* | *"&quot;"* | *"&#039;"* | *"&#39;"*) ;;
        *) return 1 ;;
    esac

    decoded="$(php -r 'echo html_entity_decode($argv[1], ENT_QUOTES | ENT_HTML5, "UTF-8");' "$current" 2>/dev/null || true)"
    [ -n "$decoded" ] || return 1
    [ "$decoded" != "$current" ] || return 1

    user="$(extract_config_value "$config_file" "database.username" || true)"
    host="$(extract_config_value "$config_file" "database.host" || true)"
    port="$(extract_config_value "$config_file" "database.port" || true)"
    db_name="$(extract_config_value "$config_file" "database.db" || true)"
    [ -n "$user" ] || user="root"
    [ -n "$host" ] || host="localhost"
    [ -n "$port" ] || port="3306"

    # Both probes below ask "does this exact password work". They have to be the
    # only source of one, and --no-defaults is what makes them so.
    #
    # MySQL reads option files before anything handed to it, and the precedence
    # between the three ways to supply a password is command line > option file >
    # environment. These pass it in MYSQL_PWD, the weakest of the three, and
    # setup_mysql_config() writes /root/.my.cnf on every machine this runs on. So
    # without the flag both probes silently test that file's password instead of
    # the two being compared, and the answer is the same for both: either the
    # stored password appears to work and the repair is skipped as unnecessary,
    # or the decoded one appears to fail and it is skipped as unproven. This
    # function could not repair anything on the machines it was written for.
    #
    # It must be the first argument or MySQL ignores it without saying so.
    # Unconditional here, unlike elsewhere: both passwords are non-empty by the
    # checks above, so there is never a case where the option file is the
    # intended source.

    # The stored password must actually be broken...
    if MYSQL_PWD="$current" mysql --no-defaults -u "$user" -h "$host" -P "$port" ${db_name:+"$db_name"} -e "SELECT 1" >/dev/null 2>&1; then
        return 1
    fi
    # ...and the decoded one must actually work.
    if ! MYSQL_PWD="$decoded" mysql --no-defaults -u "$user" -h "$host" -P "$port" ${db_name:+"$db_name"} -e "SELECT 1" >/dev/null 2>&1; then
        return 1
    fi

    backup="${config_file}.bak.$(date +%Y%m%d%H%M%S)"
    cp "$config_file" "$backup" || return 1

    local escaped
    escaped="$(escape_php_string_for_sed "$decoded")"
    sed -i "s|\$systemConfig\['database'\]\['password'\][[:space:]]*=.*|\$systemConfig['database']['password'] = '$escaped';|" "$config_file"

    # Verify the file still parses and now holds the working password, or put it back.
    if [ "$(extract_mysql_password_from_config "$config_file" 2>/dev/null || true)" != "$decoded" ]; then
        cp "$backup" "$config_file"
        print warning "Could not rewrite the database password in ${config_file}; restored the original."
        return 1
    fi

    print success "Repaired an HTML-escaped database password in ${config_file} (backup: ${backup})."
    print info "  The stored password contained &amp; and similar entities, which MySQL rejected."
    log_action "repair_html_escaped_db_password: fixed ${config_file}"
    return 0
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
# chown_app_tree — give www-data ownership of the application tree, skipping
# the directories an upgrade never writes to.
#
# The plain `chown -R` this replaces walked the entire install: .git (which the
# ACL sweep prunes precisely because it is large and has nothing to do with the
# running application), vendor/ (already chowned right after the vendor sync)
# and var/audit-trail (one compressed CSV per sample, written only ever by the
# application itself). On a mature instance that was the longest single step of
# an upgrade, and all of it was repeating work already done.
chown_app_tree() {
    local lp="${1%/}"
    [ -d "$lp" ] || return 0

    # Batched and parallel, for the same reason the ACL pass is: one chown per
    # file is one fork per file, and an instance is tens of thousands of them.
    local jobs=1
    command -v nproc >/dev/null 2>&1 && jobs=$(nproc)

    local -a heavy=()
    mapfile -t heavy < <(app_heavy_dirs "$lp")

    local -a prune=(-path "*/.git" -o -path "*/node_modules")
    local d
    for d in "${heavy[@]}"; do
        prune+=(-o -path "$d")
    done

    find "$lp" \
        \( "${prune[@]}" \) -prune \
        -o -print0 \
        | xargs -0 -r -n 200 -P "$jobs" chown -h www-data:www-data 2>/dev/null || true

    # The pruned roots still need the right owner themselves; their contents do
    # not, and set_permissions has given them default ACLs so new entries
    # inherit access without anyone walking what is already there.
    for d in "${heavy[@]}"; do
        chown -h www-data:www-data "$d" 2>/dev/null || true
    done

    return 0
}

# format_duration — seconds as something an operator can read at a glance.
#
# Upgrades range from seconds on a small instance to well over an hour on a big
# one, so a bare seconds count is either noise or arithmetic. Units are dropped
# once they are not needed rather than zero-padded through: "48s", "6m 12s",
# "1h 04m".
format_duration() {
    local total="${1:-0}"

    # Guard against a clock that moved backwards mid-run (NTP stepping the time
    # on a machine that has just come up is the realistic way this happens).
    [[ "$total" =~ ^[0-9]+$ ]] || total=0

    local h=$(( total / 3600 ))
    local m=$(( (total % 3600) / 60 ))
    local s=$(( total % 60 ))

    if [ "$h" -gt 0 ]; then
        printf '%dh %02dm' "$h" "$m"
    elif [ "$m" -gt 0 ]; then
        printf '%dm %02ds' "$m" "$s"
    else
        printf '%ds' "$s"
    fi
}

# wait_with_progress — block on background jobs, but visibly.
#
# `wait` is silent. On a big or slow instance the final permission pass can
# still be running when everything else is done, and the operator gets a line
# saying we are waiting followed by minutes of nothing — indistinguishable from
# a hang, on exactly the machines where it is most likely to be one.
#
# Not gum: `gum spin` takes a command to run, cannot poll jobs belonging to this
# shell, and shows a fixed title with no elapsed time — and the older machines
# where this wait is long are the least likely to have it installed. A counter
# ticking upward is the thing that answers "is it stuck?", so we print one.
#
# Falls back to a plain wait when stdout is not a terminal: a log full of
# carriage returns helps nobody.
# Returns 0 when every job finished, 2 when it gave up waiting — either because
# WAIT_PROGRESS_TIMEOUT elapsed or because the operator pressed Ctrl+C. In both
# of those cases the jobs are still running; the caller decides what that means
# for whatever it was going to do next.
#
# Giving up is the point. This is the last thing an upgrade does, on work that
# nobody is blocked on, and an operator watching a spinner tick past ten minutes
# has no way to tell it from a hang — so they interrupt, which used to take the
# half-finished ownership pass down with the script and skip the post-upgrade
# check entirely. Now the wait ends and the pass carries on.
#
# The jobs survive Ctrl+C only if the caller made them ignore SIGINT; a job in
# this shell's process group still gets the terminal's interrupt regardless of
# what happens here.
wait_with_progress() {
    local message="${1:-Working}"
    shift

    local pids=("$@")
    [ "${#pids[@]}" -gt 0 ] || return 0

    local timeout="${WAIT_PROGRESS_TIMEOUT:-0}"
    [[ "$timeout" =~ ^[0-9]+$ ]] || timeout=0

    local interrupted=false
    local previous_int_trap
    previous_int_trap="$(trap -p INT || true)"
    trap 'interrupted=true' INT

    local started frames='|/-\' i=0 alive elapsed rc=0 tty=false
    [ -t 1 ] && tty=true
    started=$(date +%s)

    while :; do
        alive=0
        for pid in "${pids[@]}"; do
            kill -0 "$pid" 2>/dev/null && alive=1
        done
        [ "$alive" -eq 0 ] && break

        elapsed=$(( $(date +%s) - started ))

        if [ "$interrupted" = true ]; then
            rc=2
            break
        fi
        if [ "$timeout" -gt 0 ] && [ "$elapsed" -ge "$timeout" ]; then
            rc=2
            break
        fi

        if [ "$tty" = true ]; then
            printf '\r  %s %s  %s' "${frames:$i:1}" "$message" "$(format_duration "$elapsed")"
            i=$(( (i + 1) % 4 ))
        fi
        sleep 0.5
    done

    # Clear the spinner line so whatever prints next starts clean.
    [ "$tty" = true ] && printf '\r\033[K'

    trap - INT
    [ -n "$previous_int_trap" ] && eval "$previous_int_trap"

    if [ "$rc" -eq 0 ]; then
        for pid in "${pids[@]}"; do wait "$pid" 2>/dev/null || true; done
    fi
    return "$rc"
}

# Phase timing within one instance.
#
# The run total says an upgrade took an hour and never which part of it did.
# These record wall clock between marks so the summary can name the step that
# actually cost the time, which is the difference between "the upgrade is slow"
# and something anyone can act on.
declare -A phase_seconds=()
declare -a phase_order=()
_phase_label=""
_phase_started=0

phase_reset() {
    phase_seconds=()
    phase_order=()
    _phase_label=""
    _phase_started=0
}

# Close the phase in progress, if any, and open one called $1. Called with no
# argument it only closes, which is how the last phase of a run is ended.
phase_mark() {
    if [ -n "$_phase_label" ]; then
        local elapsed=$(( $(date +%s) - _phase_started ))
        [ "$elapsed" -lt 0 ] && elapsed=0
        if [ -z "${phase_seconds[$_phase_label]:-}" ]; then
            phase_order+=("$_phase_label")
            phase_seconds["$_phase_label"]=0
        fi
        phase_seconds["$_phase_label"]=$(( ${phase_seconds[$_phase_label]} + elapsed ))
    fi

    _phase_label="${1:-}"
    _phase_started=$(date +%s)
}

# Print the breakdown, longest step called out.
#
# Silent when nothing was marked, so an instance that failed before the first
# mark says nothing rather than printing an empty table. An instance that
# failed part-way through prints what it got through, which is the half worth
# having.
phase_report() {
    phase_mark   # close whatever is still open

    [ "${#phase_order[@]}" -gt 0 ] || return 0

    local label longest="" longest_secs=0 width=0
    for label in "${phase_order[@]}"; do
        [ "${#label}" -gt "$width" ] && width=${#label}
        if [ "${phase_seconds[$label]}" -gt "$longest_secs" ]; then
            longest_secs=${phase_seconds[$label]}
            longest="$label"
        fi
    done

    print info "Where the time went:"
    for label in "${phase_order[@]}"; do
        local note=""
        # Only worth pointing at when there is a spread to point at.
        if [ "$label" = "$longest" ] && [ "$longest_secs" -ge 10 ]; then
            note="   <- longest"
        fi
        printf '    %-*s  %8s%s\n' \
            "$width" "$label" "$(format_duration "${phase_seconds[$label]}")" "$note"
    done
}

# pause_cron / resume_cron — hold scheduled tasks across a window where the
# database is being altered.
#
# A marker file rather than a crontab edit, deliberately. Editing the crontab
# means an upgrade that dies at the wrong moment leaves the instance with its
# scheduled tasks switched off and nothing to say so; a marker cron.sh checks
# is inert on its own and expires after 30 minutes regardless of what happens
# to the process that wrote it.
#
# This only stops NEW runs from starting. A task already running keeps its
# transactions, which is fine: the point is to stop fresh ones piling in for
# the couple of minutes the DDL needs.
pause_cron() {
    local lis_path="$1"
    [ -n "$lis_path" ] || return 0

    mkdir -p "${lis_path}/var" 2>/dev/null || true
    if touch "${lis_path}/var/cron-paused" 2>/dev/null; then
        chown www-data:www-data "${lis_path}/var/cron-paused" 2>/dev/null || true
        print info "Scheduled tasks paused for the database step."
    else
        # Not fatal. The window is short and the DDL now fails fast rather than
        # hanging, so an unpaused cron costs a retry at worst.
        print warning "Could not pause scheduled tasks; continuing."
    fi
}

resume_cron() {
    local lis_path="$1"
    [ -n "$lis_path" ] || return 0

    rm -f "${lis_path}/var/cron-paused" 2>/dev/null || true
}

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
    # NOTE: no ~E_STRICT here. E_STRICT has been a no-op since PHP 8.0 (all its
    # notices were reclassified) and the constant itself is deprecated in 8.4+;
    # naming it in php.ini emits a startup deprecation on 8.4/8.5.
    local desired_error_reporting="error_reporting = E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING"
    local desired_display_errors="display_errors = Off"
    local desired_log_errors="log_errors = On"
    local desired_post_max_size="post_max_size = 1G"
    local desired_upload_max_filesize="upload_max_filesize = 1G"
    # preflight.php warns below 256M because exports and imports run out of memory
    # there, and Debian ships 128M, so without this the warning is permanent on
    # every box.
    #
    # Sized from RAM, like the MySQL tuning in upgrade.sh, because the ceiling that
    # lets a big export finish on a 32GB server is the one that lets a small box
    # start swapping. memory_limit is a per-request ceiling rather than a
    # reservation - an ordinary page still peaks at a few tens of MB, and only
    # exports and imports go near it - but with mod_php every Apache worker can
    # reach it at once, and MySQL has already claimed roughly half the machine for
    # its buffer pool. The tiers leave that room. The smallest one clears
    # preflight's floor and no more.
    local php_mem_total_kb php_mem_total_gb desired_memory_limit
    php_mem_total_kb="$(awk '/MemTotal/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
    php_mem_total_gb=$((php_mem_total_kb / 1024 / 1024))
    if [ "$php_mem_total_gb" -ge 16 ]; then
        desired_memory_limit="memory_limit = 2G"
    elif [ "$php_mem_total_gb" -ge 8 ]; then
        desired_memory_limit="memory_limit = 1G"
    elif [ "$php_mem_total_gb" -ge 4 ]; then
        desired_memory_limit="memory_limit = 512M"
    else
        # Includes the case where /proc/meminfo could not be read at all, where
        # guessing high on an unknown machine is the worse mistake.
        desired_memory_limit="memory_limit = 256M"
    fi
    print info "RAM detected: ${php_mem_total_gb}GB - setting PHP ${desired_memory_limit#memory_limit = }"
    local desired_strict_mode="session.use_strict_mode = 1"
    local desired_sid_length="session.sid_length = 48"
    local desired_sid_bits="session.sid_bits_per_character = 6"
    local desired_gc_maxlifetime="session.gc_maxlifetime = 28800"
    local desired_expose_php="expose_php = Off"
    local desired_opcache_enable="opcache.enable=1"
    local desired_opcache_enable_cli="opcache.enable_cli=0"
    local desired_opcache_memory="opcache.memory_consumption=256"
    local desired_opcache_max_files="opcache.max_accelerated_files=40000"
    # Revalidate timestamps so edited PHP/config files are picked up without a
    # manual OPcache reset or Apache restart. revalidate_freq caps the stat()
    # cost to at most once per file per 60s. The app also self-heals instantly on
    # upgrade (composer purge-cache bumps var/cache/opcache.gen; bootstrap.php
    # resets OPcache on the next request), so this is the belt-and-suspenders for
    # manual config edits.
    local desired_opcache_validate="opcache.validate_timestamps=1"
    local desired_opcache_revalidate_freq="opcache.revalidate_freq=60"
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
        local er_set de_set le_set pms_set umf_set ml_set sm_set sid_len_set sid_bits_set gc_maxlifetime_set expose_set
        local opcache_enable_set opcache_enable_cli_set opcache_memory_set opcache_max_files_set
        local opcache_validate_set opcache_revalidate_freq_set opcache_save_comments_set opcache_jit_set opcache_interned_set opcache_override_set
        local pcre_jit_set

        er_set=$(grep -q "^${desired_error_reporting}$" "$ini_file" && echo true || echo false)
        de_set=$(grep -q "^${desired_display_errors}$" "$ini_file" && echo true || echo false)
        le_set=$(grep -q "^${desired_log_errors}$" "$ini_file" && echo true || echo false)
        pms_set=$(grep -q "^${desired_post_max_size}$" "$ini_file" && echo true || echo false)
        umf_set=$(grep -q "^${desired_upload_max_filesize}$" "$ini_file" && echo true || echo false)
        ml_set=$(grep -q "^${desired_memory_limit}$" "$ini_file" && echo true || echo false)
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
        opcache_revalidate_freq_set=$(grep -q "^${desired_opcache_revalidate_freq}$" "$ini_file" && echo true || echo false)
        opcache_save_comments_set=$(grep -q "^${desired_opcache_save_comments}$" "$ini_file" && echo true || echo false)
        opcache_jit_set=$(grep -q "^${desired_opcache_jit}$" "$ini_file" && echo true || echo false)
        opcache_interned_set=$(grep -q "^${desired_opcache_interned}$" "$ini_file" && echo true || echo false)
        opcache_override_set=$(grep -q "^${desired_opcache_override}$" "$ini_file" && echo true || echo false)
        pcre_jit_set=$(grep -q "^${desired_pcre_jit}$" "$ini_file" && echo true || echo false)

        # If ANY are missing, we need to rewrite
        if [ "$er_set" = false ] || [ "$de_set" = false ] || [ "$le_set" = false ] || [ "$pms_set" = false ] || [ "$umf_set" = false ] || [ "$ml_set" = false ] || [ "$sm_set" = false ] \
            || [ "$sid_len_set" = false ] || [ "$sid_bits_set" = false ] || [ "$gc_maxlifetime_set" = false ] \
            || [ "$expose_set" = false ] \
            || [ "$opcache_enable_set" = false ] || [ "$opcache_enable_cli_set" = false ] || [ "$opcache_memory_set" = false ] \
            || [ "$opcache_max_files_set" = false ] || [ "$opcache_validate_set" = false ] || [ "$opcache_revalidate_freq_set" = false ] || [ "$opcache_save_comments_set" = false ] || [ "$opcache_jit_set" = false ] \
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
                elif [[ "$line" =~ ^[[:space:]]*memory_limit[[:space:]]*= ]] && [ "$ml_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_memory_limit" >>"$temp_file"; ml_set=true
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
                elif [[ "$line" =~ ^[[:space:]]*opcache\.revalidate_freq[[:space:]]*= ]] && [ "$opcache_revalidate_freq_set" = false ]; then
                    echo ";$line" >>"$temp_file"; echo "$desired_opcache_revalidate_freq" >>"$temp_file"; opcache_revalidate_freq_set=true
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
            [ "$ml_set" = true ] || echo "$desired_memory_limit" >>"$temp_file"
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
            [ "$opcache_revalidate_freq_set" = true ] || echo "$desired_opcache_revalidate_freq" >>"$temp_file"
            [ "$opcache_save_comments_set" = true ] || echo "$desired_opcache_save_comments" >>"$temp_file"
            [ "$opcache_jit_set" = true ] || echo "$desired_opcache_jit" >>"$temp_file"
            [ "$opcache_interned_set" = true ] || echo "$desired_opcache_interned" >>"$temp_file"
            [ "$opcache_override_set" = true ] || echo "$desired_opcache_override" >>"$temp_file"
            [ "$pcre_jit_set" = true ] || echo "$desired_pcre_jit" >>"$temp_file"

            # mktemp creates its file 0600 root:root, and mv carries those bits
            # onto the destination — so a plain mv here quietly turns a 0644
            # php.ini into a root-only one. Nothing appears to break: Apache
            # opens its ini as root at startup and keeps serving. What breaks is
            # every other PHP on the box. `php -i` as www-data then reports
            # "Loaded Configuration File => (none)", so the cron jobs, bin/
            # scripts and composer migrations that run as www-data execute on
            # PHP's built-in defaults instead of these settings — and preflight,
            # which reads the Apache ini off disk as www-data, falls through to
            # whichever older version's ini is still readable and reports the
            # wrong PHP version for the whole instance.
            chmod --reference="$ini_file" "$temp_file" 2>/dev/null || chmod 0644 "$temp_file"
            chown --reference="$ini_file" "$temp_file" 2>/dev/null || true
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
            # Repair a box the old mv already narrowed to 0600. The rewrite below
            # is skipped entirely once every setting is correct, so without this
            # the permissions are never revisited and an instance stays in that
            # state through every future upgrade.
            case "$(stat -c %a "$phpini" 2>/dev/null || echo '')" in
            *[4567][4567]) : ;;
            *)
                chmod 0644 "$phpini" && print info "Restored read permissions on $phpini"
                ;;
            esac

            _update_php_ini_file "$phpini"
        else
            print warning "PHP configuration file not found: $phpini"
        fi
    done
}

# ===========================================================================
# Interactive prompt layer
#
# Every question setup.sh and upgrade.sh ask goes through here, for one reason:
# a lab once answered "Enter domain name" with their STS URL. Setup wrote
# "127.0.0.1 sts.example.org" into /etc/hosts and the machine spent weeks
# syncing to itself. Nothing in the prompt said whose domain it wanted, nothing
# validated the answer, and nothing afterwards noticed.
#
# The fix is question design, not decoration: an identity question is a menu, so
# a URL cannot be typed into it; the STS URL is asked before the local name, so
# the two can be compared; and free text is validated in a loop instead of
# silently falling back to a default the operator never saw.
#
# Rendering is a separate concern. ask_* speaks plain text everywhere, and
# upgrades itself to gum when gum happens to be installed. Nothing here may
# depend on gum being present: Docker builds, CI and `bash setup.sh </dev/null`
# have no terminal at all, and those runs must still reach a sane default.
# Set INTELIS_UI=plain to force the plain renderer.
# ===========================================================================

# Whether there is anyone there to answer.
#
# Every unattended path in this project runs through here: cron-driven and
# remote-triggered upgrades, Docker builds, CI, `bash setup.sh </dev/null`. All
# of them must reach a default without a prompt and without a spinner, so this
# is checked before any question is asked and before gum is even considered.
#
# A terminal on both stdin and stdout is the real test; the environment
# variables are belt and braces for the cases where a tty exists but nobody is
# watching it.
ui_interactive() {
    [ -n "${INTELIS_UNATTENDED:-}" ] && return 1
    [ -n "${CI:-}" ] && return 1
    [ "${DEBIAN_FRONTEND:-}" = "noninteractive" ] && return 1
    [ -t 0 ] && [ -t 1 ]
}

ui_renderer() {
    if [ "${INTELIS_UI:-}" = "plain" ] || ! ui_interactive; then
        printf 'plain'
    elif command -v gum >/dev/null 2>&1; then
        printf 'gum'
    else
        printf 'plain'
    fi
}

# gum is a single static binary and a nicety, never a requirement — a lab on a
# bad link must not have its install blocked by it. Anything that goes wrong here
# leaves the plain renderer in place and says nothing.
#
# Installed from Charm's apt repo rather than a pinned .deb so it upgrades with
# everything else on the machine. The apt update is scoped to just that list
# file: refreshing every index on a lab link costs minutes, and nothing here is
# worth minutes.
ensure_gum() {
    command -v gum >/dev/null 2>&1 && return 0
    ui_interactive || return 1
    [ "${INTELIS_UI:-}" = "plain" ] && return 1
    [ "${INTELIS_SKIP_GUM:-}" = "1" ] && return 1
    [ "$(id -u)" -eq 0 ] || return 1

    local keyring="/etc/apt/keyrings/charm.gpg"
    local list="/etc/apt/sources.list.d/charm.list"

    # Say so before going quiet. prepare_system has just finished its own apt
    # work, so the install below routinely waits on the dpkg lock while
    # apt-daily finishes — and a silent wait immediately before the first
    # question is indistinguishable from a hang.
    print info "Preparing the installer (skip with INTELIS_UI=plain)..."

    # Every apt call is bounded twice: DPkg::Lock::Timeout so it gives up on a
    # held lock instead of blocking forever, and an outer timeout so a stalled
    # transfer cannot hold the install either. Neither failure matters — this is
    # cosmetic, and the plain prompts are already there.
    local apt_opts=(-o DPkg::Lock::Timeout=20)
    local runner=()
    command -v timeout >/dev/null 2>&1 && runner=(timeout 90)

    if [ ! -s "$keyring" ]; then
        mkdir -p /etc/apt/keyrings || return 1
        if ! curl -fsSL --max-time 20 https://repo.charm.sh/apt/gpg.key 2>/dev/null |
            gpg --dearmor -o "$keyring" 2>/dev/null; then
            rm -f "$keyring"
            print info "Continuing with plain prompts."
            return 1
        fi
        chmod 0644 "$keyring"
    fi

    if [ ! -s "$list" ]; then
        printf 'deb [signed-by=%s] https://repo.charm.sh/apt/ * *\n' "$keyring" >"$list" || return 1
    fi

    # The index refresh is scoped to this one list file on purpose: refreshing
    # every index on a lab link costs minutes, and nothing here is worth minutes.
    #
    # On any failure the repo is removed again rather than left behind. An
    # unreachable third-party repo makes every later `apt-get update` on the
    # machine noisy and non-zero — including the ones that run unattended, with
    # nobody there to read the error — and gum is not worth that.
    if ! DEBIAN_FRONTEND=noninteractive "${runner[@]}" apt-get update "${apt_opts[@]}" \
        -o Dir::Etc::sourcelist="$list" \
        -o Dir::Etc::sourceparts=- \
        -o APT::Get::List-Cleanup=0 >/dev/null 2>&1; then
        rm -f "$list" "$keyring"
        print info "Continuing with plain prompts."
        return 1
    fi

    if ! DEBIAN_FRONTEND=noninteractive "${runner[@]}" apt-get install -y \
        --no-install-recommends "${apt_opts[@]}" gum >/dev/null 2>&1; then
        rm -f "$list" "$keyring"
        print info "Continuing with plain prompts."
        return 1
    fi

    command -v gum >/dev/null 2>&1
}

# --- Step header ------------------------------------------------------------
# "Step 3 of 7" is not decoration. It tells an operator part-way through a long
# install whether they are nearly done, which is the difference between reading
# the question and hitting enter to get past it.
ui_step() {
    local current="$1" total="$2" title="$3"
    if [ "$(ui_renderer)" = "gum" ]; then
        gum style --foreground 51 --bold "── Step ${current} of ${total} · ${title} " >&2
    else
        printf '\n\033[1;96m── Step %s of %s · %s\033[0m\n' "$current" "$total" "$title" >&2
    fi
}

# Step numbering, counted rather than hard-coded. Questions that turn out not
# to apply are not asked, so the numbers have to be assigned as the run goes:
# a hard-coded "Step 5 of 6" is a promise the flow cannot keep once a step is
# skipped, and skipping steps is the point.
ui_steps_total() {
    _ui_step_total="$1"
    _ui_step_n=0
}

ui_step_next() {
    _ui_step_n=$((${_ui_step_n:-0} + 1))
    ui_step "$_ui_step_n" "${_ui_step_total:-?}" "$1"
}

# Supporting text under a question. Always shown, never abbreviated: the whole
# point is that the operator learns what the question means without asking.
ui_note() {
    local line
    for line in "$@"; do
        printf '   \033[2m%s\033[0m\n' "$line" >&2
    done
}

# --- Menu -------------------------------------------------------------------
# ask_choice <outvar> <default-key> <question> <key:label:description> ...
#
# Menus exist so that the answer space is closed. A free-text question about
# what kind of machine this is can be answered with a URL; a menu cannot.
ask_choice() {
    local __outvar="$1" default_key="$2" question="$3"
    shift 3

    local keys=() labels=() descs=() entry
    for entry in "$@"; do
        keys+=("${entry%%:*}")
        local rest="${entry#*:}"
        labels+=("${rest%%:*}")
        descs+=("${rest#*:}")
    done

    local count=${#keys[@]} i default_index=1
    for ((i = 0; i < count; i++)); do
        [ "${keys[$i]}" = "$default_key" ] && default_index=$((i + 1))
    done

    if ! ui_interactive; then
        printf -v "$__outvar" '%s' "$default_key"
        log_action "Non-interactive: ${question} -> ${default_key}"
        return 0
    fi

    printf '\n \033[1m%s\033[0m\n\n' "$question" >&2

    if [ "$(ui_renderer)" = "gum" ]; then
        local options=() chosen
        for ((i = 0; i < count; i++)); do
            options+=("${labels[$i]} — ${descs[$i]}")
        done
        chosen=$(gum choose --height "$((count + 2))" \
            --selected "${labels[$((default_index - 1))]} — ${descs[$((default_index - 1))]}" \
            "${options[@]}" 2>/dev/null) || chosen=""
        for ((i = 0; i < count; i++)); do
            if [ "$chosen" = "${labels[$i]} — ${descs[$i]}" ]; then
                printf -v "$__outvar" '%s' "${keys[$i]}"
                log_action "${question} -> ${keys[$i]}"
                return 0
            fi
        done
        printf -v "$__outvar" '%s' "$default_key"
        log_action "${question} -> ${default_key} (default)"
        return 0
    fi

    for ((i = 0; i < count; i++)); do
        printf '   \033[1;96m%d)\033[0m %s\n' "$((i + 1))" "${labels[$i]}" >&2
        printf '      \033[2m%s\033[0m\n' "${descs[$i]}" >&2
    done
    echo >&2

    # No timeout. A question about what this machine IS must not answer itself
    # because the operator took a minute to think about it.
    local reply
    while true; do
        printf ' Choose 1-%d [%d]: ' "$count" "$default_index" >&2
        read -r reply || reply=""
        reply="${reply// /}"
        if [ -z "$reply" ]; then
            printf -v "$__outvar" '%s' "${keys[$((default_index - 1))]}"
            log_action "${question} -> ${keys[$((default_index - 1))]} (default)"
            return 0
        fi
        if [[ "$reply" =~ ^[0-9]+$ ]] && [ "$reply" -ge 1 ] && [ "$reply" -le "$count" ]; then
            printf -v "$__outvar" '%s' "${keys[$((reply - 1))]}"
            log_action "${question} -> ${keys[$((reply - 1))]}"
            return 0
        fi
        print error "Enter a number between 1 and ${count}."
    done
}

# --- Multiple choice --------------------------------------------------------
# ask_multi <question> <item> ...
#
# Prints the chosen items to stdout one per line; everything it shows the
# operator goes to stderr, so a caller can read the answer straight out of a
# command substitution. Read it with mapfile, since an item may contain spaces.
#
# An empty answer means everything. Both callers already meant that by it, and
# it is the safe reading of "I have not decided": an upgrade of all instances
# is what the same command does without -i.
#
# Typed numbers are validated rather than filtered. The pickers this replaces
# built their list by silently dropping anything out of range, so asking for
# "1,3" of two instances updated instance 1 and said nothing about 3 — the
# operator watched an upgrade run and had no reason to doubt it covered what
# they asked for.
ask_multi() {
    local question="$1"
    shift

    local items=("$@")
    local count=${#items[@]}
    [ "$count" -gt 0 ] || return 0

    if ! _ui_can_ask; then
        printf '%s\n' "${items[@]}"
        return 0
    fi

    if [ "$(ui_renderer)" = "gum" ]; then
        local chosen
        chosen=$(gum choose --no-limit --height "$((count + 2))" \
            --header "$question" "${items[@]}" 2>/dev/null) || chosen=""
        if [ -z "$chosen" ]; then
            printf '%s\n' "${items[@]}"
        else
            printf '%s\n' "$chosen"
        fi
        return 0
    fi

    printf '\n \033[1m%s\033[0m\n\n' "$question" >&2
    local i
    for ((i = 0; i < count; i++)); do
        printf '   \033[1;96m%d)\033[0m %s\n' "$((i + 1))" "${items[$i]}" >&2
    done
    echo >&2

    local reply nums=() n picked=() bad seen
    while true; do
        printf ' Numbers separated by commas, or enter for all: ' >&2
        _ui_read_line reply || reply=""

        reply="${reply// /}"
        if [ -z "$reply" ] || [ "$reply" = "all" ]; then
            printf '%s\n' "${items[@]}"
            return 0
        fi

        picked=()
        bad=""
        seen=""
        IFS=',' read -ra nums <<< "$reply"
        for n in "${nums[@]}"; do
            if [[ "$n" =~ ^[0-9]+$ ]] && [ "$n" -ge 1 ] && [ "$n" -le "$count" ]; then
                # "1,1" is a slip, not a request to do it twice.
                case " ${seen} " in
                    *" ${n} "*) continue ;;
                esac
                seen="${seen} ${n}"
                picked+=("${items[$((n - 1))]}")
            else
                bad="$n"
                break
            fi
        done

        # >&2, like every other line this function shows. stdout is the answer
        # here — the caller reads it out of a command substitution — so a
        # complaint printed there would come back as a selected item.
        if [ -n "$bad" ]; then
            print error "'${bad}' is not one of 1-${count}." >&2
            continue
        fi
        if [ ${#picked[@]} -eq 0 ]; then
            print error "Nothing selected." >&2
            continue
        fi

        printf '%s\n' "${picked[@]}"
        return 0
    done
}

# ask_multi is reached from paths the operator asked for explicitly (-i, or
# answering yes to "run maintenance scripts?"), and one of them has to survive
# `curl | bash` — where stdin is the script and ui_interactive is therefore
# false, but a terminal is still right there. The unattended flags still win:
# a cron or remote-triggered run must never stop on a question.
_ui_can_ask() {
    [ -n "${INTELIS_UNATTENDED:-}" ] && return 1
    [ -n "${CI:-}" ] && return 1
    [ "${DEBIAN_FRONTEND:-}" = "noninteractive" ] && return 1
    ui_interactive && return 0
    [ -r /dev/tty ] && [ -w /dev/tty ]
}

_ui_read_line() {
    if [ -t 0 ]; then
        read -r "$1"
    else
        read -r "$1" < /dev/tty
    fi
}

# --- Free text --------------------------------------------------------------
# ask_text <outvar> <default> <question> [validator-fn] [placeholder]
#
# The validator is a function name taking the candidate answer; it prints its
# own complaint and returns non-zero to re-ask. An invalid answer is never
# silently swapped for the default — that is how an operator ends up believing
# they configured something they did not.
ask_text() {
    local __outvar="$1" default="$2" question="$3" validator="${4:-}" placeholder="${5:-}"

    if ! ui_interactive; then
        printf -v "$__outvar" '%s' "$default"
        log_action "Non-interactive: ${question} -> ${default}"
        return 0
    fi

    local reply
    while true; do
        if [ "$(ui_renderer)" = "gum" ]; then
            printf '\n \033[1m%s\033[0m\n' "$question" >&2
            reply=$(gum input --placeholder "${placeholder:-$default}" --value "" 2>/dev/null) || reply=""
        else
            printf '\n \033[1m%s\033[0m\n' "$question" >&2
            [ -n "$default" ] && printf '   \033[2mpress enter for: %s\033[0m\n' "$default" >&2
            printf ' > ' >&2
            read -r reply || reply=""
        fi

        reply="$(printf '%s' "$reply" | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//')"
        [ -z "$reply" ] && reply="$default"

        if [ -n "$validator" ] && ! "$validator" "$reply"; then
            continue
        fi

        printf -v "$__outvar" '%s' "$reply"
        return 0
    done
}

# --- Password ---------------------------------------------------------------
# ask_password <outvar> <question> [confirm-question]
#
# Typed twice and compared, because the MySQL root password is being CHOSEN at
# this prompt rather than recalled, and a typo is only discovered later by
# whoever tries to restore a backup.
#
# Pass an empty confirm-question to ask once. That is for the other case: a
# password that already exists and is being RECALLED, where there is nothing to
# protect against — the wrong answer is rejected by MySQL a second later, and
# asking twice only invites the operator to type their typo twice.
ask_password() {
    local __outvar="$1" question="$2" confirm_question="${3-Confirm password}"
    local first second

    if ! ui_interactive; then
        printf -v "$__outvar" '%s' ''
        return 1
    fi

    while true; do
        if [ "$(ui_renderer)" = "gum" ]; then
            first=$(gum input --password --header "$question" 2>/dev/null) || first=""
            if [ -n "$confirm_question" ]; then
                second=$(gum input --password --header "$confirm_question" 2>/dev/null) || second=""
            else
                second="$first"
            fi
        else
            printf '\n \033[1m%s\033[0m\n > ' "$question" >&2
            read -rs first || first=""
            if [ -n "$confirm_question" ]; then
                printf '\n \033[1m%s\033[0m\n > ' "$confirm_question" >&2
                read -rs second || second=""
            else
                second="$first"
            fi
            echo >&2
        fi

        if [ -z "$first" ]; then
            print error "The password cannot be empty."
            continue
        fi
        if [ "$first" != "$second" ]; then
            print error "The two entries do not match. Try again."
            continue
        fi

        printf -v "$__outvar" '%s' "$first"
        return 0
    done
}

# --- Recap ------------------------------------------------------------------
# ui_recap "Label: value" ...
#
# Everything collected, on one screen, before the unattended phase starts. This
# is the last moment at which a wrong answer is free to fix, and it is the only
# place the operator sees their answers next to each other — which is what makes
# "Domain name: sts.example.org" next to "STS URL: https://sts.example.org"
# look as wrong as it is.
ui_recap() {
    print header "Please confirm before setup begins"
    local line
    for line in "$@"; do
        printf '   \033[1;96m%-24s\033[0m %s\n' "${line%%:*}" "${line#*: }"
    done
    echo
}

# ===========================================================================
# Hostnames and /etc/hosts
#
# A name typed at the domain prompt lands in two places: an Apache ServerName,
# where a wrong value is merely cosmetic, and /etc/hosts, where a wrong value
# takes a real server off the air for this machine. Everything below guards the
# second one.
# ===========================================================================

# Reduce whatever was typed to a bare hostname: scheme, credentials, port, path
# and trailing dot removed, lowercased. Prints nothing when nothing survives.
normalize_hostname_input() {
    local raw="$1"
    raw="$(printf '%s' "$raw" | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//')"
    raw="$(printf '%s' "$raw" | sed -E 's|^[A-Za-z][A-Za-z0-9+.-]*://||')"
    raw="${raw%%\?*}"
    raw="${raw%%/*}"
    raw="${raw##*@}"
    raw="${raw%%:*}"
    raw="${raw%.}"
    printf '%s' "$raw" | tr '[:upper:]' '[:lower:]'
}

is_valid_hostname() {
    local name="$1"
    [ -n "$name" ] || return 1
    [ ${#name} -le 253 ] || return 1
    [[ "$name" == *..* ]] && return 1
    [[ "$name" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$ ]] || return 1
    return 0
}

# The address /etc/hosts maps a name to, if any. Comments and trailing comments
# are stripped and the first match wins, which is what the resolver does.
hosts_file_lookup() {
    local name
    name="$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')"
    [ -r /etc/hosts ] || return 0
    awk -v want="$name" '
        { sub(/#.*/, "") }
        NF < 2 { next }
        {
            for (i = 2; i <= NF; i++) {
                if (tolower($i) == want) { print $1; exit }
            }
        }
    ' /etc/hosts 2>/dev/null
}

# Every address this machine answers on, loopback included.
local_ip_addresses() {
    printf '127.0.0.1\n::1\n0.0.0.0\n'
    if command -v ip >/dev/null 2>&1; then
        ip -o addr show 2>/dev/null |
            awk '{for (i = 1; i <= NF; i++) if ($i == "inet" || $i == "inet6") {split($(i+1), a, "/"); print a[1]}}'
    fi
}

ip_is_local() {
    local candidate="$1" addr
    [ -n "$candidate" ] || return 1
    while read -r addr; do
        [ "$addr" = "$candidate" ] && return 0
    done < <(local_ip_addresses)
    return 1
}

# What DNS says a name is. This consults /etc/hosts too, which is correct at the
# moment of asking: the entry under consideration has not been written yet, so a
# hit here means some other machine already owns the name.
dns_resolve_host() {
    local name="$1"
    command -v getent >/dev/null 2>&1 || return 0
    getent ahostsv4 "$name" 2>/dev/null | awk 'NR==1 {print $1}'
}

# Does /etc/hosts point a name at this machine when the name belongs elsewhere?
# This is the check that finds an already-broken box, so it deliberately asks
# only about /etc/hosts and not about DNS: once the bad line is in place, DNS
# lookups return 127.0.0.1 too and the evidence disappears.
hosts_file_shadows() {
    local name="$1" mapped
    mapped="$(hosts_file_lookup "$name")"
    [ -n "$mapped" ] || return 1
    ip_is_local "$mapped"
}

# Add "127.0.0.1 <name>" — but only when doing so cannot steal a name from a
# real server. Returns 0 when the entry is present, 1 when it was refused.
hosts_file_add_local() {
    local name="$1" mapped resolved

    if ! is_valid_hostname "$name"; then
        print warning "Not adding '${name}' to /etc/hosts: that is not a valid hostname."
        log_action "Refused /etc/hosts entry for invalid name: ${name}"
        return 1
    fi

    mapped="$(hosts_file_lookup "$name")"
    if [ -n "$mapped" ]; then
        print info "${name} is already in /etc/hosts (${mapped})."
        return 0
    fi

    resolved="$(dns_resolve_host "$name")"
    if [ -n "$resolved" ] && ! ip_is_local "$resolved"; then
        print warning "'${name}' already resolves to ${resolved}, which is not this machine."
        print warning "Not adding it to /etc/hosts. Pointing it here would make this server answer for ${name} and cut this machine off from the real one."
        log_action "Refused /etc/hosts entry for ${name}: resolves to ${resolved}"
        return 1
    fi

    printf '127.0.0.1 %s # added by intelis setup\n' "$name" >>/etc/hosts
    print info "Added ${name} to /etc/hosts"
    log_action "Added ${name} to hosts file"
    return 0
}
