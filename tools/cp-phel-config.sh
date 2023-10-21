#!/bin/bash

current_dir="$(cd "$(dirname "$0")" && pwd)"
vendor_dir="$current_dir/../vendor"
phel_vendor_dir="$vendor_dir/phel-lang/phel-lang"
phel_config_path="$phel_vendor_dir/phel-config.php"

if [[ -e "$phel_config_path" ]]; then
    cp "$phel_config_path" "$current_dir"
fi
