#!/usr/bin/env bash
# 配布用の zip を作る（設定の見本・説明書つき。合言葉・データ・デモの設定は入れない）
set -euo pipefail
cd "$(dirname "$0")/.."
out=outputs/kurage-okyakusama-anketo.zip; tmp=$(mktemp -d); d="$tmp/kurage-okyakusama-anketo"
mkdir -p "$d/docs"
cp public/kanketo.php public/kanketo_config.php.example README.md LICENSE "$d/"
cp docs/01_setup.md "$d/docs/"
grep -q "__KA_API_KEY__\|RELAY_CLIENT" "$d"/* && { echo "合言葉の痕跡がある。止める" >&2; exit 1; } || true
rm -f "$out"; (cd "$tmp" && zip -qr - kurage-okyakusama-anketo) > "$out"; rm -rf "$tmp"
unzip -l "$out"
