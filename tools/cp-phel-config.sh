#!/bin/bash

echo "Copying phel-config..."

current_dir="$(cd "$(dirname "$0")" && pwd)"
phel_config_path="$current_dir/../phel-config.php"

if [[ -e "$phel_config_path" ]]; then
    cp "$phel_config_path" .
fi

echo "Done"
