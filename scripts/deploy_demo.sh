#!/usr/bin/env bash
# デモの公開。https://proto.exbridge.jp/kanketo/
# デモの AI は gemma4。当社サーバーの共通の gemma4 中継（kaima/relay・:18343）の合言葉を kaima/.env から差し込む（リポジトリに置かない）。
# デモで DeepSeek など有料のAPIは使わない。
set -euo pipefail
cd "$(dirname "$0")/.."
set -a; . /home/kojima/work/aixec/.env; set +a
KEY=$(grep -m1 '^RELAY_CLIENT_KANKETO=' /home/kojima/work/kaima/.env | cut -d= -f2)
[ -n "$KEY" ] || { echo "中継の合言葉が見つからない（kaima/.env の RELAY_CLIENT_KANKETO）" >&2; exit 1; }
curl -s -m 10 http://127.0.0.1:18343/healthz | grep -q kanketo || { echo "共通の gemma4 中継(18343)が動いていない、または kanketo が登録されていない" >&2; exit 1; }
php -l public/kanketo.php >/dev/null && php -l demo/index.php >/dev/null
remote="/web/proto_exbridge_jp/kanketo"
up() { curl --fail --silent --show-error --ftp-create-dirs -T "$1" "ftp://${FTP_USER}:${FTP_PASS}@${FTP_HOST}${remote}/${2}"; echo "up: $2"; }
tmp=$(mktemp)
sed "s/__KA_API_KEY__/${KEY}/" demo/kanketo_config.php > "$tmp"
up public/kanketo.php kanketo.php
up "$tmp" kanketo_config.php
rm -f "$tmp"
up demo/index.php index.php
up demo/.htaccess .htaccess
printf 'Require all denied\n' > outputs/deny.htaccess
up outputs/deny.htaccess kanketo_data/.htaccess
up outputs/kanketo-ogp.png kanketo-ogp.png
echo "published: https://proto.exbridge.jp/kanketo/"
