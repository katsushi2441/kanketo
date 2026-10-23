# 置き方と設定（Kurage お客様アンケート）

## 必要なもの
- PHP 8.0 以上が動くサーバー（レンタルサーバーで可）。拡張は pdo_sqlite・mbstring・curl
- データベースのサーバーは不要（SQLite のファイルを kanketo_data/ に自動で作ります）
- AI の下書きを使うなら、OpenAI 互換の API（OpenAI・社内の Ollama など）のキー

## 手順
1. `kanketo.php` と `kanketo_config.php.example` をサーバーの同じフォルダに置く
2. `kanketo_config.php.example` を `kanketo_config.php` に名前を変えて、お店の名前・設問の雛形・管理画面のパスワード・通知先のメールを書く
3. ブラウザで `kanketo.php` を開き、アンケートが出ることを確かめる
4. `kanketo.php?p=admin` に入り、「QRコードの印刷」から QR コードを印刷して、席やレジに置く
5. Google への案内を出すときは、`KA_GOOGLE_PLACE_ID` にお店の場所ID（Place ID）を入れる

`kanketo_data/` には `.htaccess`（Require all denied）を自動で置きます。Apache 以外のサーバーでは、このフォルダを Web から読めないように設定してください。

## AI（Claude Code など）に頼んで変える例
- 「設問に『店内の清潔さ』を星で足して」→ `KA_QUESTIONS` に雛形の形で設問を書く
- 「席を5番テーブルまで増やして」→ `KA_SPOTS` に足す
- 「ブランドの色を紺にして」→ `KA_BRAND_COLOR`

## 守っていること（変えないでください）
- 評価の高い低いで、Google への案内を出し分けない
- 口コミと引き換えの特典を付けない
- AI はお客様が書いた言葉だけを整える（ほめ言葉・数字・お店の狙う言葉を足さない）

Google のポリシーで禁じられている行為（肯定的な口コミだけを選んで集める・特典と引き換えに頼む・中身を指定する）にあたらないための作りです。変えると、口コミの削除やビジネスプロフィールの停止につながるおそれがあります。
