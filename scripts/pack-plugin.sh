#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="tabletop-events-tournaments"
OUT="${ROOT}/dist"
mkdir -p "${OUT}"

MAIN_FILE="${ROOT}/${SLUG}.php"
VERSION="$(php -r "preg_match('/Version:\s*([^\s]+)/', file_get_contents('${MAIN_FILE}'), \$m); echo \$m[1] ?? '0.0.0';")"
ZIP="${OUT}/${SLUG}-${VERSION}.zip"

rm -f "${ZIP}"

# Zipped via a symlink named after the plugin slug (not the repo root
# directly) so the archive contains one top-level folder matching the
# slug, e.g. tabletop-events-tournaments/tabletop-events-tournaments.php rather than loose
# files at the zip root, or GitHub's own "Download ZIP" folder name
# (tabletop-events-tournaments-main/). WordPress needs that single top-level
# folder to install to a stable, predictable directory name; without
# it, every version bump lands in its own version-suffixed folder
# instead of upgrading the existing install in place.
STAGE="$(mktemp -d)"
trap 'rm -rf "${STAGE}"' EXIT
ln -s "${ROOT}" "${STAGE}/${SLUG}"

cd "${STAGE}"
zip -r "${ZIP}" "${SLUG}/" \
	-x "${SLUG}/.git/*" \
	-x "${SLUG}/.github/*" \
	-x "${SLUG}/scripts/*" \
	-x "${SLUG}/dist/*" \
	-x "*.DS_Store" \
	-x "__MACOSX/*"

echo "Built: ${ZIP}"
echo "Drop this straight into wp-admin → Plugins → Add New → Upload Plugin, or unzip it into wp-content/plugins/."
