<?php
// デモのトップ：Kurage お客様アンケート の説明。お客様の画面は kanketo.php、お店の画面は kanketo.php?p=admin
$url = 'https://proto.exbridge.jp/kanketo/';
$title = 'Kurage お客様アンケート｜QRで来店アンケート（デモ）';
$desc = '飲食店・美容室・クリニック・宿のお客様アンケートを、QRコードから1分で。日本語・英語・中国語・韓国語に対応し、低い評価はすぐメールで届きます。Googleマップへの案内は、評価に関係なく全員に同じように出す作りです。';
$faq = array(
    array('お客様は、アプリを入れる必要がありますか？', 'いりません。席やレジに置いたQRコードをスマホで読むか、NFCタグにかざすと、ブラウザでアンケートが開きます。名前や連絡先は聞きません。'),
    array('Googleの口コミを増やすためのシステムですか？', 'お客様の声を集めて、お店の改善に使うためのシステムです。Googleマップへの案内は、評価の高い低いに関係なく、答えた人全員に同じように出します。口コミと引き換えの特典も付けません。Googleのポリシーが、肯定的な口コミだけを選んで集めることや、特典と引き換えに口コミを頼むことを禁じているためです。'),
    array('AIが口コミの文章を作るのですか？', 'お客様が自分で書いたひと言を、読みやすく整えるだけです。書いていないほめ言葉や数字は足さず、不満が書かれていればそのまま残します。整えた文がもとの言葉から離れすぎたときは出さず、書いたひと言をそのまま返します。使うかどうかはお店が決められます。'),
    array('席ごとや担当者ごとに分けて集められますか？', 'できます。席や担当ごとに別のQRコードを印刷でき、どのQRコードから答えたかが回答の一覧に出ます。'),
    array('どんなお店で使えますか？', '飲食店・美容室・クリニック・宿泊の設問の雛形があります。設問は設定ファイルで自由に変えられます。'),
);
$ld = array('@context' => 'https://schema.org', '@graph' => array(
    array('@type' => 'WebPage', 'name' => $title, 'url' => $url, 'description' => $desc, 'inLanguage' => 'ja'),
    array('@type' => 'SoftwareApplication', 'name' => 'Kurage お客様アンケート', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web（PHP）',
          'url' => $url, 'description' => $desc, 'publisher' => array('@type' => 'Organization', 'name' => '株式会社エクスブリッジ', 'url' => 'https://exbridge.jp/')),
    array('@type' => 'FAQPage', 'mainEntity' => array_map(fn($q) => array('@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => array('@type' => 'Answer', 'text' => $q[1])), $faq)),
    array('@type' => 'BreadcrumbList', 'itemListElement' => array(
        array('@type' => 'ListItem', 'position' => 1, 'name' => 'デモ一覧', 'item' => 'https://proto.exbridge.jp/'),
        array('@type' => 'ListItem', 'position' => 2, 'name' => 'Kurage お客様アンケート', 'item' => $url))),
));
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="ja"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($url) ?>">
<meta name="robots" content="index,follow,max-image-preview:large">
<meta property="og:type" content="website"><meta property="og:site_name" content="株式会社エクスブリッジ">
<meta property="og:title" content="<?= e($title) ?>"><meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($url) ?>"><meta property="og:image" content="<?= e($url) ?>kanketo-ogp.png"><meta property="og:locale" content="ja_JP">
<meta name="twitter:card" content="summary_large_image">
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<style>
:root{--b:#0a9a8f;--bd:#076f67;--ink:#12202f;--mut:#5d6b7a;--line:#dbe5e9;--paper:#f4f8f8}
*{box-sizing:border-box}html,body{overflow-x:hidden;max-width:100%}
body{margin:0;background:var(--paper);color:var(--ink);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Hiragino Sans","Noto Sans JP",sans-serif;line-height:1.8}
img{max-width:100%;height:auto}
.wrap{width:min(1060px,calc(100% - 32px));margin:0 auto}
header{background:#fff;border-bottom:1px solid var(--line)}
header .wrap{display:flex;align-items:center;gap:10px;padding:12px 0}
header a.brand{display:flex;align-items:center;gap:9px;color:var(--ink);text-decoration:none;font-weight:900}
.brand-mark{width:32px;height:32px;object-fit:contain}
.hero{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,.8fr);gap:28px;align-items:center;padding:36px 0 18px}
.hero>*{min-width:0}
.eyebrow{display:inline-block;background:#e2f6f3;color:var(--bd);border-radius:99px;padding:4px 12px;font-size:12.5px;font-weight:900}
h1{font-size:clamp(25px,5.4vw,40px);line-height:1.35;margin:12px 0}
.lead{color:var(--mut);font-size:16.5px;margin:0 0 18px}
.mascot{width:240px;height:auto;object-fit:contain;justify-self:center}
.btns{display:flex;gap:10px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:6px;background:var(--b);color:#fff;font-weight:900;text-decoration:none;padding:13px 24px;border-radius:999px}
.btn.ghost{background:#fff;color:var(--bd);border:1.5px solid var(--b)}
.hint{font-size:13px;color:var(--mut);margin-top:8px}
section{padding:22px 0}
h2{font-size:23px;margin:0 0 12px}
.grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px 20px;box-shadow:0 1px 2px rgba(18,32,47,.04);min-width:0}
.card h3{margin:0 0 6px;font-size:17px}.card p{margin:0;font-size:15px;color:#33454f}
.tag{display:inline-block;font-size:12px;font-weight:900;color:var(--bd);background:#e2f6f3;border-radius:99px;padding:2px 10px;margin-bottom:8px}
.rule{border-left:6px solid var(--b)}
.steps{counter-reset:s;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.steps .card::before{counter-increment:s;content:counter(s);display:inline-flex;width:30px;height:30px;border-radius:50%;background:var(--b);color:#fff;font-weight:900;align-items:center;justify-content:center;margin-bottom:8px}
details{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 16px;margin:0 0 10px}
summary{font-weight:800;cursor:pointer}details p{margin:8px 0 0;color:#33454f}
footer{padding:26px 0 36px;text-align:center;font-size:13px;color:var(--mut)}footer a{color:var(--mut)}
@media(max-width:760px){.hero,.grid3,.grid2,.steps{grid-template-columns:1fr}.mascot{width:170px}}
</style></head><body>
<header><div class="wrap"><a class="brand" href="./"><img class="brand-mark" src="https://exbridge.jp/images/logo-mark-128.png" width="32" height="32" alt="株式会社エクスブリッジ">Kurage お客様アンケート</a></div></header>
<main class="wrap">
<div class="hero">
  <div>
    <span class="eyebrow">飲食店・美容室・クリニック・宿のための来店アンケート</span>
    <h1>お客様の声を、QRコードから1分で集める。</h1>
    <p class="lead">席やレジにQRコードを置くだけ。お客様はタップで答えて、ひと言を書くだけです。日本語・英語・中国語・韓国語に切り替わり、低い評価はその場でお店にメールで届きます。</p>
    <div class="btns"><a class="btn" href="kanketo.php?s=t1">お客様の画面を試す</a><a class="btn ghost" href="kanketo.php?p=admin">お店の管理画面を見る</a></div>
    <p class="hint">デモのお店は「カフェ くらげ」です。管理画面のパスワードは <b>demo</b>。送った回答は管理画面にそのまま並びます。</p>
  </div>
  <img class="mascot" src="https://kurage.exbridge.jp/images/kurage-mascot-cutout.png" width="240" height="240" alt="Kurage のマスコット">
</div>

<section><h2>できること</h2><div class="grid3">
  <div class="card"><span class="tag">お客様</span><h3>アプリ不要、1分で答えられる</h3><p>QRコードかNFCタグから開いて、星をタップ。ひと言はキーボードのマイクで話しても書けます。名前や連絡先は聞きません。</p></div>
  <div class="card"><span class="tag">外国のお客様</span><h3>4つの言語に自動で切り替わる</h3><p>スマホの言語設定を見て、日本語・英語・中国語・韓国語で表示します。回答の一覧には、どの言語で答えたかも出ます。</p></div>
  <div class="card"><span class="tag">お店</span><h3>低い評価は、すぐメールで届く</h3><p>総合の評価が低いと、その場でお店にメールが届きます。お客様が帰る前、その日のうちに手を打てます。</p></div>
  <div class="card"><span class="tag">お店</span><h3>席ごと・担当ごとに分けて集める</h3><p>席や担当ごとにQRコードを印刷できます。どの席の、誰の接客への声かが一覧に出ます。CSVでも書き出せます。</p></div>
  <div class="card"><span class="tag">Google</span><h3>Googleマップへの案内も、全員に同じように</h3><p>答え終わった人に「よろしければGoogleにも」と案内します。評価の高い低いで出し分けません。</p></div>
  <div class="card"><span class="tag">AI</span><h3>書いたひと言を、読みやすく整える</h3><p>お客様が書いた言葉だけを使って整えます。ほめ言葉も数字も足さず、不満はそのまま残します。使うかどうかはお店が選べます。</p></div>
</div></section>

<section><h2>Googleのポリシーを守る作り</h2><div class="grid2">
  <div class="card rule"><h3>良い評価の人だけを、Googleに案内しない</h3><p>Googleは、肯定的な口コミだけを選んで集めることを禁じています。このシステムは、評価に関係なく全員に同じ案内を出します。</p></div>
  <div class="card rule"><h3>特典と引き換えにしない・中身に口を出さない</h3><p>口コミと引き換えの特典は付けません（画面にも書いてあります）。お店が口コミに入れてほしい言葉を指定する機能もありません。</p></div>
</div></section>

<section><h2>使い方</h2><div class="steps">
  <div class="card"><h3>設定を書く</h3><p>お店の名前、設問の雛形（飲食・美容・クリニック・宿泊）、席や担当、通知先のメールを設定ファイルに書きます。AIに頼んで書き換えられます。</p></div>
  <div class="card"><h3>QRコードを置く</h3><p>管理画面からQRコードを印刷して、席・レジ・会計のトレーに置きます。NFCタグに書き込んでも使えます。</p></div>
  <div class="card"><h3>声を読んで、直す</h3><p>回答の一覧と低い評価のメールを見て、その日のうちに手を打ちます。CSVで書き出して、月ごとに見返せます。</p></div>
</div></section>

<section><h2>よくあるご質問</h2>
<?php foreach ($faq as $q): ?><details><summary><?= e($q[0]) ?></summary><p><?= e($q[1]) ?></p></details>
<?php endforeach; ?>
</section>

<section><div class="card" style="text-align:center"><h2 style="margin-bottom:6px">自分のお店で使いたい方へ</h2>
<p>PHPが動くレンタルサーバーに置くだけで動く、1ファイルのシステムです。ソースコードごと買い切りで、月額はかかりません。お店に合わせた設問づくりや設置のご相談も承ります。</p>
<p class="btns" style="justify-content:center;margin-top:12px"><a class="btn" href="https://kappstore.exbridge.jp/app.php?id=0ef9191df8969b56#ref=kanketo-demo">導入版を見る（税込110,000円）</a><a class="btn ghost" href="https://exbridge.jp/contact.php?ref=kanketo-demo&amp;subject=<?= rawurlencode('「Kurage お客様アンケート」の導入について') ?>#form">導入について相談する</a></p></div></section>
</main>
<footer><a href="https://proto.exbridge.jp/">デモ一覧</a>　｜　<a href="https://exbridge.jp/">株式会社エクスブリッジ</a>
<p style="margin:6px 0">これはデモです。</p><script src=https://kurage.exbridge.jp/partner-bar.js defer></script></footer>
</body></html>
