#!/bin/bash

backup_and_replace() {
  local file="$1"
  local backup_file="${file}.$(date +"%Y%m%d%H%M%S").bak"

  # Create a timestamped backup
  cp "$file" "$backup_file"

  # Replace the string in the file
  sed -i "s/MIRRORING_PACKAGE_DOWNLOAD_TIMEOUT = '300'/MIRRORING_PACKAGE_DOWNLOAD_TIMEOUT = '3600'/" "$file"
}

# List of files to process
files=(
  "/var/www/repomanager/controllers/App/Config/Settings.php"
  "/var/www/repomanager/controllers/Settings.php"
  "/var/www/repomanager/update/database/3.7.4.php"
)

# Iterate over the files and perform the backup and replacement
for file in "${files[@]}"; do
  if [ -e "$file" ]; then
    backup_and_replace "$file"
    echo "Backup and replacement completed for: $file"
  else
    echo "File not found: $file"
  fi
done
