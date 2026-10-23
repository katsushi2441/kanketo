# Kurage お客様アンケート（kanketo）

飲食店・美容室・クリニック・宿のための、1ファイルの来店アンケート。
デモ：https://proto.exbridge.jp/kanketo/ （お店の管理画面のパスワードは `demo`）

## できること
- 席やレジの QR コード（NFC タグも可）から、アプリなしで1分で答えられる。名前や連絡先は聞かない
- 日本語・英語・中国語・韓国語に自動で切り替わる
- 設問の雛形：飲食（restaurant）・美容（beauty）・クリニック（clinic）・宿泊（hotel）。設定で自由に変えられる
- 席ごと・担当ごとの QR コードを印刷でき、どこから答えたかが一覧に出る
- 低い評価（総合が KA_LOW_SCORE 以下）は、その場でお店にメール
- 回答の一覧と CSV の書き出し
- Google マップへの案内と、お客様が書いたひと言を AI で読みやすく整える下書き（任意）

## Google のポリシーを守る作り
- 評価の高い低いで、Google への案内を出し分けない（全員に同じボタン）
- 口コミと引き換えの特典は付けない（画面に明記）
- AI は、お客様が書いた言葉だけを整える。ほめ言葉・数字・お店が狙う言葉を足さない。不満は残す
- 整えた文がもとの言葉から離れすぎたら（長さ・数字・ほめ言葉・言葉の重なりで判定）出さず、書いたひと言をそのまま返す

## 置き方
1. `public/kanketo.php` を PHP 8 が動くサーバーに置く（pdo_sqlite・mbstring・curl を使う）
2. `public/kanketo_config.php.example` を `kanketo_config.php` にコピーして書き換える
3. `kanketo_data/` は自動で作られ、Web から読めないように `.htaccess` が置かれる（nginx の場合は別途拒否する）
4. `kanketo.php?p=admin` からQRコードを印刷して、席に置く

## デモの公開（当社用）
`bash scripts/deploy_demo.sh` — AI は社内の gemma4（共通の中継 :18343、合言葉は kaima/.env の RELAY_CLIENT_KANKETO）。

MIT License / 株式会社エクスブリッジ
