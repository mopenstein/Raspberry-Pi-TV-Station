#!/bin/bash

# Ensure root privileges
if [ "$EUID" -ne 0 ]; then
    exec sudo "$0" "$@"
fi

# Update these two variables with your repository details
GITHUB_USER="mopenstein"
REPO_NAME="raspberry_pi_tv_station"
BRANCH="main"

GITHUB_RAW="https://raw.githubusercontent.com/$GITHUB_USER/$REPO_NAME/$BRANCH"
TMP_MANIFEST=$(mktemp)

echo "Checking network connectivity to GitHub..."
if ! curl -s --connect-timeout 4 -I "https://raw.githubusercontent.com" >/dev/null 2>&1; then
    echo "Error: Cannot reach GitHub. Check your network connection. Aborting."
    rm -f "$TMP_MANIFEST"
    exit 1
fi

echo "Fetching latest update manifest..."
# Using cache-buster parameter to bypass CDN delay
if ! curl -s -f -L "$GITHUB_RAW/assets/manifest.txt?cache=$(date +%s)" -o "$TMP_MANIFEST"; then
    echo "Error: Could not download manifest.txt from repository. Aborting."
    rm -f "$TMP_MANIFEST"
    exit 1
fi

# ==============================================================================
# Dynamic Manifest-Driven Backup
# ==============================================================================
BACKUP_DIR="/home/pi/station_backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
mkdir -p "$BACKUP_DIR"

echo "Scanning manifest for existing files to back up..."

# Extract unique local destination file paths from the manifest
BACKUP_ITEMS=()
while IFS= read -r line || [ -n "$line" ]; do
    line=$(echo "$line" | tr -d '\r' | xargs)
    [[ -z "$line" || "$line" =~ ^# ]] && continue

    TARGET=""
    if [[ "$line" =~ ^(sync|update|create): ]]; then
        PAYLOAD="${line#*:}"
        TARGET="${PAYLOAD##*->}"
    elif [[ "$line" =~ ^remove: ]]; then
        TARGET="${line#remove:}"
    fi

    # If the target file currently exists on the machine, queue it for backup
    if [ -n "$TARGET" ] && [ -e "$TARGET" ]; then
        BACKUP_ITEMS+=("$TARGET")
    fi
done < "$TMP_MANIFEST"

# Deduplicate list and create backup archive if matching files exist
if [ ${#BACKUP_ITEMS[@]} -gt 0 ]; then
    # Sort and remove duplicate paths
    readarray -t UNIQUE_BACKUP_ITEMS < <(printf '%s\n' "${BACKUP_ITEMS[@]}" | sort -u)

    echo "Creating safety backup of ${#UNIQUE_BACKUP_ITEMS[@]} existing manifest target(s)..."
    tar -czf "$BACKUP_DIR/backup_$TIMESTAMP.tar.gz" "${UNIQUE_BACKUP_ITEMS[@]}" 2>/dev/null
    chown -R pi:pi "$BACKUP_DIR"
    chmod 600 "$BACKUP_DIR/backup_$TIMESTAMP.tar.gz"
    echo "Backup saved: $BACKUP_DIR/backup_$TIMESTAMP.tar.gz"

    # Retention Policy: Keep only the 5 most recent backups
    ls -1t "$BACKUP_DIR"/backup_*.tar.gz 2>/dev/null | tail -n +6 | xargs rm -f -- 2>/dev/null
else
    echo "No matching existing files found to back up."
fi
# ==============================================================================

echo "Applying updates..."

# Process the manifest line by line
while IFS= read -r line || [ -n "$line" ]; do
    # Strip carriage returns and leading/trailing whitespace
    line=$(echo "$line" | tr -d '\r' | xargs)

    # Skip empty lines and comment lines
    [[ -z "$line" || "$line" =~ ^# ]] && continue

    # Capture and save the manifest release/build date
    if [[ "$line" =~ ^date: ]]; then
        VERSION_DATE="${line#date:}"
        echo "Manifest date: $VERSION_DATE"
        echo "$VERSION_DATE" > /home/pi/Desktop/.manifest_version
        chown pi:pi /home/pi/Desktop/.manifest_version
        chmod 644 /home/pi/Desktop/.manifest_version
        continue
    fi

    # Generic status or release message
    if [[ "$line" =~ ^msg: ]]; then
        echo "${line#msg:}"
        continue
    fi

    # 1. Handle file deletions (remove:/path/to/file)
    if [[ "$line" =~ ^remove: ]]; then
        TARGET="${line#remove:}"
        if [ -e "$TARGET" ]; then
            echo "Removing: $TARGET"
            rm -rf "$TARGET"
        fi
        continue
    fi

    # 2. Handle file sync/downloads with hash/byte check and permission preservation
    if [[ "$line" =~ ^sync: ]] || [[ "$line" =~ ^update: ]] || [[ "$line" =~ ^create: ]]; then
        PAYLOAD="${line#*:}"
        REMOTE_SRC="${PAYLOAD%%->*}"
        LOCAL_DEST="${PAYLOAD##*->}"

        # Block the updater from modifying or replacing itself
        if [ "$(readlink -f "$LOCAL_DEST" 2>/dev/null)" = "$(readlink -f "$0")" ] || [[ "$LOCAL_DEST" == *"update_station.sh" ]]; then
            echo "Skipping self-update: $LOCAL_DEST is protected."
            continue
        fi

        # Ensure target directory tree exists
        mkdir -p "$(dirname "$LOCAL_DEST")"

        # Capture existing metadata if the destination file already exists
        PREV_OWNER=""
        PREV_PERM=""
        if [ -e "$LOCAL_DEST" ]; then
            PREV_OWNER=$(stat -c "%u:%g" "$LOCAL_DEST" 2>/dev/null)
            PREV_PERM=$(stat -c "%a" "$LOCAL_DEST" 2>/dev/null)
        fi

        TMP_DOWNLOAD=$(mktemp)
        if curl -s -f -L "$GITHUB_RAW/$REMOTE_SRC?cache=$(date +%s)" -o "$TMP_DOWNLOAD"; then
            # Compare temp download against existing destination file
            if [ -f "$LOCAL_DEST" ] && cmp -s "$TMP_DOWNLOAD" "$LOCAL_DEST"; then
                echo "Unchanged: $LOCAL_DEST"
                rm -f "$TMP_DOWNLOAD"
            else
                echo "Updating: $REMOTE_SRC -> $LOCAL_DEST"
                mv "$TMP_DOWNLOAD" "$LOCAL_DEST"

                # Restore previous permissions/ownership if it existed
                if [ -n "$PREV_OWNER" ] && [ -n "$PREV_PERM" ]; then
                    chown "$PREV_OWNER" "$LOCAL_DEST"
                    chmod "$PREV_PERM" "$LOCAL_DEST"
                else
                    # Fallback for brand-new files: assign based on path and filetype
                    if [[ "$LOCAL_DEST" == /home/pi/* ]]; then
                        chown pi:pi "$LOCAL_DEST"
                    elif [[ "$LOCAL_DEST" == /var/www/* ]]; then
                        chown www-data:www-data "$LOCAL_DEST"
                    fi

                    # Grant 755 to scripts so both pi and www-data can execute
                    if [[ "$LOCAL_DEST" =~ \.(py|sh|bin)$ ]]; then
                        chmod 755 "$LOCAL_DEST"
                    else
                        chmod 644 "$LOCAL_DEST"
                    fi
                fi
            fi
        else
            echo "Warning: Failed to fetch $REMOTE_SRC"
            rm -f "$TMP_DOWNLOAD"
        fi
        continue
    fi

    # 3. Handle shell/CLI execution (run:command)
    if [[ "$line" =~ ^run: ]]; then
        CMD="${line#run:}"
        echo "Running task: $CMD"
        eval "$CMD"
        continue
    fi

done < "$TMP_MANIFEST"

# Clean up temporary manifest file
rm -f "$TMP_MANIFEST"

echo "Update process finished successfully."