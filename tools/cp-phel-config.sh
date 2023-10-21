#!/bin/bash

current_dir="$(cd "$(dirname "$0")" && pwd)"
vendor_dir="$current_dir/../vendor"
phel_vendor_dir="$vendor_dir/phel-lang/phel-lang"

cp "$phel_vendor_dir/phel-config.php" $current_dir
