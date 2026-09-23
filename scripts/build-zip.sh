#!/usr/bin/env bash
# Builds dist/rating-glance.zip, ready for Plugins → Add New → Upload Plugin.
# Usage: scripts/build-zip.sh [expected-version]
set -euo pipefail

cd "$(dirname "$0")/.."
slug=rating-glance

version=$(sed -n 's/^ \* Version: *//p' "$slug.php" | tr -d '[:space:]')
if [[ -z "$version" ]]; then
	echo "Could not read Version from $slug.php" >&2
	exit 1
fi
if [[ $# -gt 0 && "$1" != "$version" ]]; then
	echo "Version mismatch: $slug.php says $version, expected $1" >&2
	exit 1
fi

rm -rf dist
mkdir -p "dist/$slug"
rsync -a --exclude-from=.distignore ./ "dist/$slug/"
sed -i.bak "s/^Stable tag: .*/Stable tag: $version/" "dist/$slug/readme.txt" && rm "dist/$slug/readme.txt.bak"

(cd dist && zip -qr "$slug.zip" "$slug")
echo "Built dist/$slug.zip (version $version)"
