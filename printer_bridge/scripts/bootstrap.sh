#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"

cleanup() {
  rm -rf "$TMP"
}
trap cleanup EXIT

command -v flutter >/dev/null 2>&1 || {
  echo "Flutter is not installed or is not available in PATH." >&2
  exit 1
}

flutter create   --platforms=android,ios,windows   --org com.w68autoparts   --project-name w68_printer_bridge   "$TMP/generated"

for platform in android ios windows; do
  if [ ! -d "$ROOT/$platform" ]; then
    cp -R "$TMP/generated/$platform" "$ROOT/$platform"
    echo "Created $platform runner."
  else
    echo "$platform already exists; left unchanged."
  fi
done

cd "$ROOT"
flutter pub get

echo
echo "Flutter runners are ready."
echo "Apply the Android/iOS platform snippets in platform/ before building."
