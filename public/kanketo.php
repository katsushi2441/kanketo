<?php
/**
 * Kurage お客様アンケート（kanketo）— 1ファイルの来店アンケート
 *
 * お客様：QRコード（またはNFCタグ）から開く → タップで答える → ひと言（任意）→ 送信
 *         → 全員に同じ「Googleに書く」案内。AIの下書きは、お客様が書いたひと言だけを整える（任意・お店が設定でオン/オフ）。
 * お店　：?p=admin で回答一覧・CSV・QRコード印刷。低い評価はその場でメール。
 *
 * Google の投稿コンテンツのポリシーを守る作り（2026-10-23 決定）
 *  - 評価の高い低いで、Google への案内を出し分けない（全員に同じボタン）。
 *  - 口コミと引き換えの特典は付けない（画面に明記）。
 *  - AIは、お客様が書いた言葉だけを整える。ほめ言葉・数字・お店の狙う言葉を足さない。不満は残す。
 *    整えた文がもとの言葉から離れすぎたら出さず、書いたひと言をそのまま返す（ka_draft_ok）。
 *
 * 設定は同じフォルダの kanketo_config.php。データは kanketo_data/（Web から読めないようにする）。
 * MIT License / 株式会社エクスブリッジ
 */

declare(strict_types=1);
const KA_VERSION = '1.0.0';

$cfg = __DIR__ . '/kanketo_config.php';
if (is_file($cfg)) { require $cfg; }
function ka_def(string $k, $v): void { if (!defined($k)) { define($k, $v); } }
ka_def('KA_SHOP_NAME', 'お店の名前');
ka_def('KA_TEMPLATE', 'restaurant');          // restaurant / beauty / clinic / hotel
ka_def('KA_QUESTIONS', null);                 // 雛形の代わりに自分の設問を使うとき（形は ka_templates() と同じ）
ka_def('KA_SPOTS', array());                  // 'QRの記号' => '席や担当の名前'（例 't1' => '1番テーブル'）
ka_def('KA_GOOGLE_PLACE_ID', '');             // Google の場所ID。空なら Google への案内を出さない
ka_def('KA_AI_DRAFT', true);                  // ひと言を整えるAIの下書きを使うか
ka_def('KA_API_BASE', '');                    // OpenAI 互換の API（例 https://api.openai.com/v1）
ka_def('KA_API_KEY', '');
ka_def('KA_MODEL', 'gpt-4o-mini');
ka_def('KA_NOTIFY_EMAIL', '');                // 低い評価を知らせる宛先
ka_def('KA_NOTIFY_FROM', '');
ka_def('KA_LOW_SCORE', 2);                    // 総合がこの星の数以下なら知らせる
ka_def('KA_ADMIN_PASSWORD', '');              // 空のあいだは管理画面に入れない
ka_def('KA_BRAND_COLOR', '#0a9a8f');
ka_def('KA_DATA_DIR', __DIR__ . '/kanketo_data');
ka_def('KA_DEMO', false);
ka_def('KA_RATE_SUBMIT', 20);                 // 1時間に1つの回線から送れる回数
ka_def('KA_RATE_DRAFT', 20);

session_name('kanketo');
session_start();
mb_internal_encoding('UTF-8');

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ka_self(): string { return strtok($_SERVER['REQUEST_URI'] ?? 'kanketo.php', '?') ?: 'kanketo.php'; }
function ka_ip(): string { return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|kanketo'); }

/* ---------- 言語 ---------- */
const KA_LANGS = array('ja' => '日本語', 'en' => 'English', 'zh' => '中文', 'ko' => '한국어');
function ka_lang(): string {
    $l = $_GET['lang'] ?? $_POST['lang'] ?? '';
    if (isset(KA_LANGS[$l])) { return $l; }
    $al = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'ja');
    foreach (array('ja', 'en', 'zh', 'ko') as $c) { if (strpos($al, $c) === 0) { return $c; } }
    return 'ja';
}
function t(string $k): string {
    static $T = array(
        'title'      => array('アンケート', 'Survey', '问卷', '설문'),
        'intro'      => array('1分で終わります。いただいた声は、お店づくりに使います。', 'It takes about a minute. Your feedback helps us improve.', '大约1分钟。您的意见将用于改进我们的服务。', '1분이면 끝납니다. 보내주신 의견은 가게 운영에 활용합니다.'),
        'required'   => array('必須', 'Required', '必填', '필수'),
        'comment'    => array('ひと言あれば（任意）', 'Any comments? (optional)', '有什么想说的吗？（选填）', '한마디 있으시면 (선택)'),
        'comment_ph' => array('良かったこと、気になったこと、なんでも', 'What you liked, what could be better — anything', '满意的地方、在意的地方，什么都可以', '좋았던 점, 아쉬웠던 점 무엇이든'),
        'voice'      => array('キーボードのマイクを押すと、話して入力できます。', 'Tap the mic on your keyboard to dictate.', '点击键盘上的麦克风即可语音输入。', '키보드의 마이크를 누르면 말로 입력할 수 있습니다.'),
        'submit'     => array('送信する', 'Send', '提交', '보내기'),
        'need_score' => array('総合の星を選んでください。', 'Please choose an overall rating.', '请选择总体评分。', '종합 별점을 선택해 주세요.'),
        'thanks'     => array('ありがとうございました', 'Thank you!', '非常感谢！', '감사합니다'),
        'thanks_sub' => array('いただいた声は、お店で読ませていただきます。', 'We will read every response.', '我们会认真阅读您的意见。', '보내주신 의견은 가게에서 꼼꼼히 읽겠습니다.'),
        'g_lead'     => array('よろしければ、Googleマップにも感想をお書きください（任意です）。', 'If you like, you can also leave a review on Google Maps (optional).', '如果愿意，也可以在谷歌地图上留下评价（自愿）。', '괜찮으시다면 Google 지도에도 후기를 남겨 주세요 (선택).'),
        'g_btn'      => array('Googleに書く', 'Write on Google', '在谷歌上撰写', 'Google에 작성'),
        'no_reward'  => array('口コミの投稿と引き換えの特典はありません。書くかどうか、何を書くかは、ご自由です。', 'There is no reward for posting a review. Whether and what you write is entirely up to you.', '发布评价没有任何奖励。是否撰写、写什么，完全由您决定。', '후기 작성에 대한 혜택은 없습니다. 쓸지, 무엇을 쓸지는 자유입니다.'),
        'd_btn'      => array('書いたひと言を、文章に整える（AI）', 'Tidy up my comment (AI)', '用AI整理我的留言', '내가 쓴 한마디를 문장으로 다듬기 (AI)'),
        'd_note'     => array('あなたが書いた言葉だけを使って整えます。言葉を足したり、良い方に変えたりはしません。直してからお使いください。', 'Only your own words are used. Nothing is added or made more positive. Please edit before using.', '只使用您写的内容进行整理，不会添加内容或美化评价。请修改后再使用。', '직접 쓰신 말만 사용해 다듬습니다. 말을 더하거나 좋게 바꾸지 않습니다. 고친 뒤 사용해 주세요.'),
        'd_wait'     => array('整えています…', 'Working…', '整理中…', '다듬는 중…'),
        'd_fail'     => array('うまく整えられなかったので、書いたひと言をそのまま入れました。', 'Could not tidy it up, so your original comment is shown as is.', '未能整理，已原样显示您的留言。', '다듬지 못해 쓰신 한마디를 그대로 넣었습니다.'),
        'copy'       => array('コピーする', 'Copy', '复制', '복사'),
        'copied'     => array('コピーしました。Googleの画面に貼り付けられます。', 'Copied. You can paste it on Google.', '已复制，可粘贴到谷歌页面。', '복사했습니다. Google 화면에 붙여넣을 수 있습니다.'),
        'demo_g'     => array('デモのため、Googleには移動しません。実際のお店では、ここからそのお店の口コミを書く画面が開きます。', 'Demo only: this does not open Google. In a real shop, the review page for that shop opens here.', '演示版不会跳转到谷歌。实际店铺会在此打开该店的评价页面。', '데모라 Google로 이동하지 않습니다. 실제 가게에서는 여기서 그 가게의 후기 작성 화면이 열립니다.'),
        'too_many'   => array('短い時間に何度も送られたため、しばらくしてからお試しください。', 'Too many requests. Please try again later.', '请求过多，请稍后再试。', '요청이 너무 많습니다. 잠시 후 다시 시도해 주세요.'),
        'again'      => array('もう一度答える', 'Answer again', '重新填写', '다시 답하기'),
    );
    $i = array_search(ka_lang(), array_keys(KA_LANGS), true);
    return $T[$k][$i] ?? $T[$k][0];
}

/* ---------- 設問の雛形（1問目は必ず総合の星） ---------- */
function ka_templates(): array {
    $again = array('id' => 'again', 'type' => 'choice',
        'q' => array('また来たいと思いますか？', 'Would you come again?', '您还想再来吗？', '다시 오고 싶으신가요?'),
        'opts' => array(array('はい', 'Yes', '想', '네'), array('たぶん', 'Maybe', '也许', '아마도'), array('いいえ', 'No', '不想', '아니요')));
    return array(
        'restaurant' => array(
            array('id' => 'overall', 'type' => 'stars', 'q' => array('今日のご来店は、全体としていかがでしたか？', 'How was your visit overall?', '本次用餐整体感觉如何？', '오늘 방문은 전체적으로 어떠셨나요?')),
            array('id' => 'food', 'type' => 'stars', 'q' => array('お料理の味は？', 'How was the food?', '菜品的味道如何？', '음식 맛은 어떠셨나요?')),
            array('id' => 'service', 'type' => 'stars', 'q' => array('スタッフの接客は？', 'How was our service?', '服务人员的接待如何？', '직원 응대는 어떠셨나요?')),
            $again),
        'beauty' => array(
            array('id' => 'overall', 'type' => 'stars', 'q' => array('今日の仕上がりと接客、全体ではいかがでしたか？', 'How was your visit overall?', '本次整体感觉如何？', '오늘 시술과 응대, 전체적으로 어떠셨나요?')),
            array('id' => 'finish', 'type' => 'stars', 'q' => array('仕上がりは？', 'How was the result?', '效果如何？', '결과물은 어떠셨나요?')),
            array('id' => 'counsel', 'type' => 'stars', 'q' => array('ご希望の聞き取り（カウンセリング）は？', 'Did we understand what you wanted?', '我们是否充分了解了您的需求？', '원하시는 바를 잘 들어드렸나요?')),
            $again),
        'clinic' => array(
            array('id' => 'overall', 'type' => 'stars', 'q' => array('本日の受診は、全体としていかがでしたか？', 'How was your visit overall?', '本次就诊整体感觉如何？', '오늘 진료는 전체적으로 어떠셨나요?')),
            array('id' => 'explain', 'type' => 'stars', 'q' => array('説明は分かりやすかったですか？', 'Were the explanations clear?', '说明是否易懂？', '설명은 이해하기 쉬우셨나요?')),
            array('id' => 'wait', 'type' => 'stars', 'q' => array('待ち時間は？', 'How was the waiting time?', '等待时间如何？', '대기 시간은 어떠셨나요?')),
            array('id' => 'staff', 'type' => 'stars', 'q' => array('受付やスタッフの対応は？', 'How was the reception and staff?', '前台和工作人员的接待如何？', '접수와 직원 응대는 어떠셨나요?'))),
        'hotel' => array(
            array('id' => 'overall', 'type' => 'stars', 'q' => array('ご滞在は、全体としていかがでしたか？', 'How was your stay overall?', '本次入住整体感觉如何？', '숙박은 전체적으로 어떠셨나요?')),
            array('id' => 'room', 'type' => 'stars', 'q' => array('お部屋は？', 'How was your room?', '房间如何？', '객실은 어떠셨나요?')),
            array('id' => 'staff', 'type' => 'stars', 'q' => array('スタッフの対応は？', 'How was our staff?', '工作人员的服务如何？', '직원 응대는 어떠셨나요?')),
            array('id' => 'again', 'type' => 'choice', 'q' => array('また泊まりたいと思いますか？', 'Would you stay with us again?', '您还想再次入住吗？', '다시 묵고 싶으신가요?'), 'opts' => $again['opts'])),
    );
}
function ka_questions(): array {
    if (is_array(KA_QUESTIONS)) { return KA_QUESTIONS; }
    $all = ka_templates();
    return $all[KA_TEMPLATE] ?? $all['restaurant'];
}
function ka_tr(array $a): string { $i = array_search(ka_lang(), array_keys(KA_LANGS), true); return (string)($a[$i] ?? $a[0]); }

/* ---------- データ ---------- */
function ka_db(): PDO {
    static $db = null;
    if ($db) { return $db; }
    if (!is_dir(KA_DATA_DIR)) { @mkdir(KA_DATA_DIR, 0700, true); }
    $ht = KA_DATA_DIR . '/.htaccess';
    if (!is_file($ht)) { @file_put_contents($ht, "Require all denied\n"); }
    $db = new PDO('sqlite:' . KA_DATA_DIR . '/kanketo.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE IF NOT EXISTS answers (id INTEGER PRIMARY KEY, token TEXT UNIQUE, created_at TEXT, spot TEXT, lang TEXT,
               score INTEGER, answers TEXT, comment TEXT, draft_used INTEGER DEFAULT 0, google_click INTEGER DEFAULT 0, notified INTEGER DEFAULT 0)');
    $db->exec('CREATE TABLE IF NOT EXISTS rl (ip TEXT, kind TEXT, ts INTEGER)');
    if (KA_DEMO && (int)$db->query('SELECT COUNT(*) FROM answers')->fetchColumn() === 0) { ka_seed_demo($db); }
    return $db;
}
function ka_rate_ok(string $kind, int $limit): bool {
    $db = ka_db(); $now = time();
    $db->prepare('DELETE FROM rl WHERE ts < ?')->execute(array($now - 3600));
    $st = $db->prepare('SELECT COUNT(*) FROM rl WHERE ip = ? AND kind = ?'); $st->execute(array(ka_ip(), $kind));
    if ((int)$st->fetchColumn() >= $limit) { return false; }
    $db->prepare('INSERT INTO rl VALUES (?,?,?)')->execute(array(ka_ip(), $kind, $now));
    return true;
}
function ka_spot(): string { $s = (string)($_GET['s'] ?? $_POST['s'] ?? ''); return isset(KA_SPOTS[$s]) ? $s : ''; }

/* ---------- 低い評価の通知 ---------- */
function ka_notify(array $row): bool {
    if (KA_DEMO || KA_NOTIFY_EMAIL === '') { return false; }
    mb_language('Japanese');
    $spot = $row['spot'] !== '' ? (KA_SPOTS[$row['spot']] ?? $row['spot']) : '（指定なし）';
    $body = KA_SHOP_NAME . " のアンケートに、低い評価が届きました。\n\n"
          . '総合：' . str_repeat('★', (int)$row['score']) . str_repeat('☆', 5 - (int)$row['score']) . "\n"
          . '場所・担当：' . $spot . "\n日時：" . $row['created_at'] . "\n\n";
    foreach (json_decode($row['answers'], true) as $k => $v) { $body .= $k . '：' . $v . "\n"; }
    $body .= "\nひと言：\n" . ($row['comment'] !== '' ? $row['comment'] : '（なし）') . "\n";
    $head = KA_NOTIFY_FROM !== '' ? 'From: ' . KA_NOTIFY_FROM : '';
    return (bool)@mb_send_mail(KA_NOTIFY_EMAIL, '【お客様アンケート】低い評価が届きました（総合 ' . $row['score'] . '）', $body, $head);
}

/* ---------- AIの下書き（お客様の言葉を整えるだけ） ---------- */
function ka_bigrams(string $s): array {
    $s = preg_replace('/[\s\p{P}\p{S}]+/u', '', mb_strtolower($s));
    $out = array(); $n = mb_strlen($s);
    for ($i = 0; $i < $n - 1; $i++) { $out[] = mb_substr($s, $i, 2); }
    return $out;
}
function ka_words(string $s): array { return array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s)), 'strlen')); }
/** 整えた文が、もとの言葉から離れすぎていないか。離れていたら出さない */
function ka_draft_ok(string $src, string $draft, string $lang): bool {
    $ls = mb_strlen($src); $ld = mb_strlen($draft);
    if ($ld === 0 || $ld > max($ls * 2.2, $ls + 60)) { return false; }
    preg_match_all('/\d+/', $draft, $nd); preg_match_all('/\d+/', $src, $ns);
    foreach ($nd[0] as $n) { if (!in_array($n, $ns[0], true)) { return false; } }          // 数字を足さない
    foreach (array('おすすめ', 'オススメ', '最高', '絶対', '間違いな', 'リピート確定', '星5', '★5', '神対応', 'recommend', 'best', 'must', 'amazing', '推荐', '强烈', '최고', '강추', '추천') as $w) {
        if (mb_stripos($draft, $w) !== false && mb_stripos($src, $w) === false) { return false; }   // ほめ言葉を足さない
    }
    if ($lang === 'en') {
        $a = ka_words($draft); $b = array_flip(ka_words($src));
        $hit = 0; foreach ($a as $w) { if (isset($b[$w])) { $hit++; } }
        return count($a) > 0 && $hit / count($a) >= 0.4;
    }
    $a = ka_bigrams($draft); $b = array_flip(ka_bigrams($src));
    if (count($a) === 0) { return false; }
    $hit = 0; foreach ($a as $g) { if (isset($b[$g])) { $hit++; } }
    return $hit / count($a) >= 0.35;
}
function ka_ai_draft(string $src, string $lang): ?string {
    if (KA_API_BASE === '' || KA_API_KEY === '') { return null; }
    $sys = 'あなたは校正係です。お客様が書いた感想を、口コミとして読みやすい文章に整えるだけです。'
         . '規則：書かれていない事実・感想・ほめ言葉・評価・数字を足さない。不満や否定的な内容は弱めずに、そのまま残す。'
         . 'お店の宣伝や、検索を意識した言葉を入れない。入力と同じ言語で書く。1〜3文。整えた文章だけを出力する。';
    $payload = json_encode(array('model' => KA_MODEL, 'temperature' => 0.2, 'max_tokens' => 400,
        'messages' => array(array('role' => 'system', 'content' => $sys),
            // 指定しないと英語の感想を日本語に訳してしまう（2026-10-23 実測）。出力の言語を明示する
            array('role' => 'user', 'content' => '出力の言語：' . array('ja' => '日本語', 'en' => 'English', 'zh' => '中文（简体）', 'ko' => '한국어')[$lang] . "（訳さない）\n---\n" . $src))),
        JSON_UNESCAPED_UNICODE);
    $ch = curl_init(rtrim(KA_API_BASE, '/') . '/chat/completions');
    curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . KA_API_KEY)));
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($res === false || $code !== 200) { return null; }
    $j = json_decode((string)$res, true);
    $out = trim((string)($j['choices'][0]['message']['content'] ?? ''));
    $out = (string)preg_replace('/^[\s"「『]+|[\s"」』]+$/u', '', $out);   // trim() はバイト単位なので日本語の括弧に使わない
    return ka_draft_ok($src, $out, $lang) ? $out : null;
}
function ka_json(array $a, int $code = 200): void {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a, JSON_UNESCAPED_UNICODE); exit;
}

/* ---------- デモの見本データ ---------- */
function ka_seed_demo(PDO $db): void {
    $rows = array(
        array(5, array('food' => 5, 'service' => 5, 'again' => 'はい'), 'ランチの日替わりがおいしかったです。また来ます。', 'ja', 't1'),
        array(4, array('food' => 4, 'service' => 5, 'again' => 'はい'), '店員さんが席を案内してくれて助かりました。', 'ja', 't2'),
        array(2, array('food' => 3, 'service' => 2, 'again' => 'たぶん'), '注文してから料理が来るまで、かなり待ちました。', 'ja', 't1'),
        array(5, array('food' => 5, 'service' => 4, 'again' => 'Yes'), 'Great coffee and a quiet place to work.', 'en', ''),
        array(3, array('food' => 3, 'service' => 4, 'again' => 'たぶん'), '', 'ja', 'counter'),
        array(4, array('food' => 4, 'service' => 4, 'again' => '想'), '咖啡很好喝，店员很亲切。', 'zh', 't2'),
        array(1, array('food' => 2, 'service' => 1, 'again' => 'いいえ'), '会計で声をかけても、しばらく気づいてもらえませんでした。', 'ja', 'counter'),
        array(5, array('food' => 5, 'service' => 5, 'again' => '네'), '디저트가 맛있었어요.', 'ko', ''),
    );
    $st = $db->prepare('INSERT INTO answers (token, created_at, spot, lang, score, answers, comment) VALUES (?,?,?,?,?,?,?)');
    foreach ($rows as $i => $r) {
        $st->execute(array(bin2hex(random_bytes(12)), date('Y-m-d H:i', time() - (count($rows) - $i) * 7200), $r[4], $r[3], $r[0],
            json_encode($r[1], JSON_UNESCAPED_UNICODE), $r[2]));
    }
}

/* ============ ルーティング ============ */
$p = (string)($_GET['p'] ?? '');

// 送信
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $p === 'send') {
    if (!empty($_POST['website'])) { header('Location: ' . ka_self()); exit; }   // 機械の送信よけ
    $score = (int)($_POST['q_overall'] ?? 0);
    if ($score < 1 || $score > 5) { header('Location: ' . ka_self() . '?lang=' . ka_lang() . '&err=score'); exit; }
    if (!ka_rate_ok('send', KA_RATE_SUBMIT)) { http_response_code(429); $p = 'busy'; }
    else {
        $ans = array();
        foreach (ka_questions() as $q) {
            if ($q['id'] === 'overall') { continue; }
            $v = (string)($_POST['q_' . $q['id']] ?? '');
            if ($q['type'] === 'stars') { $v = (string)max(0, min(5, (int)$v)); if ($v === '0') { $v = ''; } }
            else { $ok = array(); foreach ($q['opts'] as $o) { $ok[] = ka_tr($o); } if (!in_array($v, $ok, true)) { $v = ''; } }
            $ans[$q['id']] = $v;
        }
        $comment = mb_substr(trim((string)($_POST['comment'] ?? '')), 0, 600);
        $row = array('token' => bin2hex(random_bytes(12)), 'created_at' => date('Y-m-d H:i'), 'spot' => ka_spot(), 'lang' => ka_lang(),
            'score' => $score, 'answers' => json_encode($ans, JSON_UNESCAPED_UNICODE), 'comment' => $comment);
        ka_db()->prepare('INSERT INTO answers (token, created_at, spot, lang, score, answers, comment) VALUES (?,?,?,?,?,?,?)')
            ->execute(array_values($row));
        if ($score <= KA_LOW_SCORE && ka_notify($row)) {
            ka_db()->prepare('UPDATE answers SET notified = 1 WHERE token = ?')->execute(array($row['token']));
        }
        header('Location: ' . ka_self() . '?p=thanks&t=' . $row['token'] . '&lang=' . ka_lang()); exit;
    }
}

// AIの下書き
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $p === 'draft') {
    if (!KA_AI_DRAFT) { ka_json(array('ok' => false, 'error' => 'disabled'), 403); }
    $st = ka_db()->prepare('SELECT comment, lang FROM answers WHERE token = ?'); $st->execute(array((string)($_POST['t'] ?? '')));
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r || trim((string)$r['comment']) === '') { ka_json(array('ok' => false, 'error' => 'not_found'), 404); }
    if (!ka_rate_ok('draft', KA_RATE_DRAFT)) { ka_json(array('ok' => false, 'error' => 'rate'), 429); }
    $d = ka_ai_draft((string)$r['comment'], (string)$r['lang']);
    ka_db()->prepare('UPDATE answers SET draft_used = ? WHERE token = ?')->execute(array($d !== null ? 1 : 0, (string)($_POST['t'] ?? '')));
    ka_json(array('ok' => true, 'used' => $d !== null ? 'ai' : 'original', 'text' => $d ?? (string)$r['comment']));
}

// Google へ（押した数を数えてから移動。行き先は設定の場所IDだけで組む）
if ($p === 'go') {
    $tok = (string)($_GET['t'] ?? '');
    ka_db()->prepare('UPDATE answers SET google_click = 1 WHERE token = ?')->execute(array($tok));
    if (!KA_DEMO && KA_GOOGLE_PLACE_ID !== '') {
        header('Location: https://search.google.com/local/writereview?placeid=' . rawurlencode(KA_GOOGLE_PLACE_ID)); exit;
    }
    header('Location: ' . ka_self() . '?p=thanks&t=' . rawurlencode($tok) . '&lang=' . ka_lang() . '&demo_g=1'); exit;
}

// 管理画面
if (in_array($p, array('admin', 'csv', 'qr', 'logout'), true)) {
    if ($p === 'logout') { unset($_SESSION['ka_ok']); header('Location: ' . ka_self() . '?p=admin'); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (KA_ADMIN_PASSWORD !== '' && hash_equals(KA_ADMIN_PASSWORD, (string)$_POST['password'])) { $_SESSION['ka_ok'] = 1; session_regenerate_id(true); }
        else { $login_err = true; }
    }
    if (empty($_SESSION['ka_ok'])) { ka_page_login($login_err ?? false); exit; }
    if ($p === 'csv') { ka_csv(); exit; }
    if ($p === 'qr') { ka_page_qr(); exit; }
    ka_page_admin(); exit;
}

if ($p === 'thanks') { ka_page_thanks(); exit; }
if ($p === 'busy') { ka_head(t('title')); echo '<main class="card"><p>' . h(t('too_many')) . '</p></main>'; ka_foot(); exit; }
ka_page_survey(); exit;

/* ============ 画面 ============ */
function ka_head(string $title, bool $admin = false): void {
    $c = h(KA_BRAND_COLOR);
    echo '<!doctype html><html lang="' . h(ka_lang()) . '"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
       . '<title>' . h($title) . '</title><style>'
       . ':root{--b:' . $c . ';--ink:#12202f;--mut:#5d6b7a;--line:#dbe5e9;--paper:#f4f8f8}'
       . '*{box-sizing:border-box}html,body{overflow-x:hidden;max-width:100%}'
       . 'body{margin:0;background:var(--paper);color:var(--ink);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Hiragino Sans","Noto Sans JP",sans-serif;line-height:1.7}'
       . '.wrap{max-width:' . ($admin ? '1100' : '560') . 'px;margin:0 auto;padding:16px}'
       . 'header.top{background:#fff;border-bottom:3px solid var(--b)}header.top .wrap{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 16px;flex-wrap:wrap}'
       . 'header.top b{font-size:17px}.langs{display:flex;gap:6px;flex-wrap:wrap}.langs a{font-size:12.5px;padding:3px 9px;border:1px solid var(--line);border-radius:99px;text-decoration:none;color:var(--mut);background:#fff}'
       . '.langs a.on{border-color:var(--b);color:var(--b);font-weight:700}'
       . '.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;margin:14px 0;box-shadow:0 1px 2px rgba(18,32,47,.04)}'
       . 'h1{font-size:21px;margin:4px 0 6px}.mut{color:var(--mut);font-size:14px}'
       . '.q{margin:18px 0 6px;font-weight:700}.req{font-size:11px;color:#fff;background:var(--b);border-radius:6px;padding:1px 6px;margin-left:6px;vertical-align:middle}'
       . '.stars{display:flex;gap:6px;direction:rtl;justify-content:flex-end}.stars input{display:none}'
       . '.stars label{font-size:38px;line-height:1;color:#cfd8dc;cursor:pointer;user-select:none}'
       . '.stars input:checked~label,.stars label:hover,.stars label:hover~label{color:#f2b01e}'
       . '.choice{display:flex;gap:8px;flex-wrap:wrap}.choice input{display:none}.choice label{border:1.5px solid var(--line);border-radius:99px;padding:8px 18px;cursor:pointer;background:#fff}'
       . '.choice input:checked+label{border-color:var(--b);background:var(--b);color:#fff;font-weight:700}'
       . 'textarea{width:100%;min-height:96px;border:1.5px solid var(--line);border-radius:12px;padding:10px 12px;font-size:16px;font-family:inherit}'
       . 'textarea:focus{outline:none;border-color:var(--b)}'
       . '.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;background:var(--b);color:#fff;border:none;border-radius:999px;padding:13px 26px;font-size:16px;font-weight:800;cursor:pointer;text-decoration:none;font-family:inherit}'
       . '.btn.ghost{background:#fff;color:var(--b);border:1.5px solid var(--b)}.btn.full{width:100%}'
       . '.note{font-size:12.5px;color:var(--mut);margin-top:8px}.err{background:#fdeaea;border:1px solid #eab6b6;color:#8a2f2f;border-radius:10px;padding:8px 12px;font-size:14px}'
       . '.demo{background:#fff8e6;border:1px solid #e3b440;color:#6b4d00;border-radius:10px;padding:8px 12px;font-size:13px}'
       . '.scroll-x{overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%}table{border-collapse:collapse;width:100%;font-size:13.5px}'
       . 'th,td{border-bottom:1px solid var(--line);padding:8px 10px;text-align:left;vertical-align:top;word-break:break-word}th{background:#f1f6f6;white-space:nowrap}'
       . 'td.c{min-width:220px}.low{background:#fff4f2}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}'
       . '.qrs{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}.qrs>div{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px;text-align:center;min-width:0}'
       . '.qrs canvas,.qrs img{width:180px;height:180px}.url{font-size:11.5px;color:var(--mut);word-break:break-all}'
       . '@media print{header.top,.tabs,.noprint{display:none}.qrs>div{break-inside:avoid}}'
       . 'footer{text-align:center;font-size:12px;color:var(--mut);padding:18px 0 28px}footer a{color:var(--mut)}'
       . '</style></head><body>';
}
function ka_foot(): void {
    echo '<footer><div class="wrap">Kurage お客様アンケート</div>';
    if (($_SERVER['HTTP_HOST'] ?? '') === 'proto.exbridge.jp') {
        echo '<p style="text-align:center;font-size:13px;margin:6px 0;color:#5d6b7a">これはデモです。<a href="https://proto.exbridge.jp/kanketo/#ref=kanketo-demo-foot">製品の説明を見る</a></p>'
           . '<script src=https://kurage.exbridge.jp/partner-bar.js defer></script>';
    }
    echo '</footer></body></html>';
}
function ka_langbar(string $extra = ''): string {
    $s = '<div class="langs">';
    foreach (KA_LANGS as $k => $v) { $s .= '<a class="' . ($k === ka_lang() ? 'on' : '') . '" href="?lang=' . $k . $extra . '">' . h($v) . '</a>'; }
    return $s . '</div>';
}

function ka_page_survey(): void {
    $spot = ka_spot(); $sx = $spot !== '' ? '&s=' . rawurlencode($spot) : '';
    ka_head(KA_SHOP_NAME . '｜' . t('title'));
    echo '<header class="top"><div class="wrap"><b>' . h(KA_SHOP_NAME) . '</b>' . ka_langbar($sx) . '</div></header><div class="wrap">';
    if (KA_DEMO) { echo '<p class="demo">デモのお店です。送った内容は、デモの管理画面にそのまま表示されます（名前や連絡先は聞きません）。</p>'; }
    echo '<form class="card" method="post" action="?p=send"><h1>' . h(t('title')) . '</h1><p class="mut">' . h(t('intro')) . '</p>';
    if (($_GET['err'] ?? '') === 'score') { echo '<p class="err">' . h(t('need_score')) . '</p>'; }
    echo '<input type="hidden" name="lang" value="' . h(ka_lang()) . '"><input type="hidden" name="s" value="' . h($spot) . '">'
       . '<div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>';
    foreach (ka_questions() as $q) {
        $req = $q['id'] === 'overall';
        echo '<p class="q">' . h(ka_tr($q['q'])) . ($req ? '<span class="req">' . h(t('required')) . '</span>' : '') . '</p>';
        if ($q['type'] === 'stars') {
            echo '<div class="stars" role="radiogroup">';
            for ($i = 5; $i >= 1; $i--) {
                $id = 'q_' . $q['id'] . '_' . $i;
                echo '<input type="radio" id="' . $id . '" name="q_' . h($q['id']) . '" value="' . $i . '"' . ($req && $i === 5 ? ' required' : '') . '>'
                   . '<label for="' . $id . '" aria-label="' . $i . '">★</label>';
            }
            echo '</div>';
        } else {
            echo '<div class="choice">';
            foreach ($q['opts'] as $j => $o) {
                $id = 'q_' . $q['id'] . '_' . $j; $v = ka_tr($o);
                echo '<input type="radio" id="' . $id . '" name="q_' . h($q['id']) . '" value="' . h($v) . '"><label for="' . $id . '">' . h($v) . '</label>';
            }
            echo '</div>';
        }
    }
    echo '<p class="q">' . h(t('comment')) . '</p><textarea name="comment" maxlength="600" placeholder="' . h(t('comment_ph')) . '"></textarea>'
       . '<p class="note">🎤 ' . h(t('voice')) . '</p><p style="margin-top:18px"><button class="btn full" type="submit">' . h(t('submit')) . '</button></p></form></div>';
    ka_foot();
}

function ka_page_thanks(): void {
    $tok = (string)($_GET['t'] ?? '');
    $st = ka_db()->prepare('SELECT comment FROM answers WHERE token = ?'); $st->execute(array($tok));
    $r = $st->fetch(PDO::FETCH_ASSOC);
    ka_head(KA_SHOP_NAME . '｜' . t('thanks'));
    echo '<header class="top"><div class="wrap"><b>' . h(KA_SHOP_NAME) . '</b></div></header><div class="wrap">'
       . '<div class="card"><h1>' . h(t('thanks')) . '</h1><p class="mut">' . h(t('thanks_sub')) . '</p></div>';
    $show_google = $r && (KA_DEMO || KA_GOOGLE_PLACE_ID !== '');
    if ($show_google) {
        // 評価に関係なく、全員に同じ案内を出す（評価で出し分けない）
        echo '<div class="card"><p style="margin-top:0">' . h(t('g_lead')) . '</p>';
        if (!empty($_GET['demo_g'])) { echo '<p class="demo">' . h(t('demo_g')) . '</p>'; }
        $has = trim((string)$r['comment']) !== '';
        if (KA_AI_DRAFT && $has) {
            echo '<p><button class="btn ghost full" type="button" id="dbtn">' . h(t('d_btn')) . '</button></p>'
               . '<p class="note">' . h(t('d_note')) . '</p>'
               . '<div id="dbox" style="display:none"><textarea id="dtext"></textarea><p class="note" id="dmsg"></p>'
               . '<p><button class="btn ghost" type="button" id="cbtn">' . h(t('copy')) . '</button> <span class="note" id="cmsg"></span></p></div>';
        } elseif ($has) {
            echo '<textarea id="dtext">' . h($r['comment']) . '</textarea><p><button class="btn ghost" type="button" id="cbtn">' . h(t('copy')) . '</button> <span class="note" id="cmsg"></span></p>';
        }
        echo '<p style="margin-top:14px"><a class="btn full" href="?p=go&amp;t=' . h($tok) . '&amp;lang=' . h(ka_lang()) . '">' . h(t('g_btn')) . '</a></p>'
           . '<p class="note">' . h(t('no_reward')) . '</p></div>';
        $js = array('t' => $tok, 'lang' => ka_lang(), 'wait' => t('d_wait'), 'fail' => t('d_fail'), 'copied' => t('copied'));
        echo '<script>const KA=' . json_encode($js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';'
           . 'const db=document.getElementById("dbtn"),cb=document.getElementById("cbtn"),dt=document.getElementById("dtext");'
           . 'if(db){db.onclick=async()=>{db.disabled=true;db.textContent=KA.wait;const f=new FormData();f.append("t",KA.t);'
           . 'try{const r=await fetch("?p=draft&lang="+KA.lang,{method:"POST",body:f});const j=await r.json();'
           . 'document.getElementById("dbox").style.display="block";dt.value=j.text||"";'
           . 'document.getElementById("dmsg").textContent=j.used==="ai"?"":KA.fail;}catch(e){document.getElementById("dbox").style.display="block";document.getElementById("dmsg").textContent=KA.fail;}'
           . 'db.style.display="none";};}'
           . 'if(cb){cb.onclick=async()=>{try{await navigator.clipboard.writeText(dt.value);}catch(e){dt.select();document.execCommand("copy");}document.getElementById("cmsg").textContent=KA.copied;};}'
           . '</script>';
    }
    echo '<p style="text-align:center"><a class="mut" href="?lang=' . h(ka_lang()) . '">' . h(t('again')) . '</a></p></div>';
    ka_foot();
}

function ka_page_login(bool $err): void {
    ka_head('管理画面｜' . KA_SHOP_NAME, true);
    echo '<div class="wrap" style="max-width:420px"><form class="card" method="post" action="?p=admin"><h1>管理画面</h1>';
    if (KA_ADMIN_PASSWORD === '') { echo '<p class="err">kanketo_config.php の KA_ADMIN_PASSWORD を決めると入れます。</p>'; }
    if ($err) { echo '<p class="err">パスワードが違います。</p>'; }
    if (KA_DEMO) { echo '<p class="demo">デモのパスワードは <b>demo</b> です。</p>'; }
    echo '<p><input type="password" name="password" style="width:100%;padding:12px;border:1.5px solid #dbe5e9;border-radius:10px;font-size:16px" autocomplete="current-password"></p>'
       . '<p><button class="btn full">入る</button></p></form></div>';
    ka_foot();
}

function ka_admin_tabs(string $on): string {
    $a = array('admin' => '回答の一覧', 'qr' => 'QRコードの印刷', 'csv' => 'CSVで書き出す', 'logout' => '出る');
    $s = '<div class="tabs noprint">';
    foreach ($a as $k => $v) { $s .= '<a class="btn ' . ($k === $on ? '' : 'ghost') . '" style="padding:8px 16px;font-size:14px" href="?p=' . $k . '">' . h($v) . '</a>'; }
    return $s . '</div>';
}
function ka_rows(): array { return ka_db()->query('SELECT * FROM answers ORDER BY id DESC LIMIT 1000')->fetchAll(PDO::FETCH_ASSOC); }

function ka_page_admin(): void {
    $rows = ka_rows(); $qs = ka_questions();
    $n = count($rows); $avg = $n ? array_sum(array_column($rows, 'score')) / $n : 0;
    $low = count(array_filter($rows, fn($r) => (int)$r['score'] <= KA_LOW_SCORE));
    $gc = count(array_filter($rows, fn($r) => (int)$r['google_click'] === 1));
    ka_head('管理画面｜' . KA_SHOP_NAME, true);
    echo '<header class="top"><div class="wrap"><b>' . h(KA_SHOP_NAME) . '　管理画面</b></div></header><div class="wrap">' . ka_admin_tabs('admin');
    if (KA_DEMO) { echo '<p class="demo">デモの管理画面です。見本の回答と、お客様の画面から送られた回答が並びます。低い評価のメールは、デモでは送りません。</p>'; }
    echo '<div class="card"><b>回答 ' . $n . '件</b>　総合の平均 ' . number_format($avg, 1) . '　低い評価（★' . KA_LOW_SCORE . '以下） ' . $low . '件　Googleのボタンを押した人 ' . $gc . '人</div>';
    echo '<div class="card scroll-x"><table><tr><th>日時</th><th>場所・担当</th><th>言語</th><th>総合</th>';
    foreach ($qs as $q) { if ($q['id'] !== 'overall') { echo '<th>' . h(mb_strimwidth($q['q'][0], 0, 18, '…')) . '</th>'; } }
    echo '<th>ひと言</th><th>Google</th></tr>';
    foreach ($rows as $r) {
        $a = json_decode((string)$r['answers'], true) ?: array();
        echo '<tr class="' . ((int)$r['score'] <= KA_LOW_SCORE ? 'low' : '') . '"><td style="white-space:nowrap">' . h($r['created_at']) . '</td>'
           . '<td>' . h($r['spot'] !== '' ? (KA_SPOTS[$r['spot']] ?? $r['spot']) : '—') . '</td><td>' . h($r['lang']) . '</td>'
           . '<td style="white-space:nowrap">' . str_repeat('★', (int)$r['score']) . '</td>';
        foreach ($qs as $q) { if ($q['id'] !== 'overall') { $v = (string)($a[$q['id']] ?? ''); echo '<td>' . h($q['type'] === 'stars' && $v !== '' ? $v . '/5' : ($v !== '' ? $v : '—')) . '</td>'; } }
        echo '<td class="c">' . nl2br(h($r['comment'])) . '</td><td>' . ((int)$r['google_click'] ? '押した' : '') . '</td></tr>';
    }
    echo '</table></div></div>';
    ka_foot();
}

function ka_csv(): void {
    $qs = ka_questions();
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="kanketo-' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    $head = array('日時', '場所・担当', '言語', '総合');
    foreach ($qs as $q) { if ($q['id'] !== 'overall') { $head[] = $q['q'][0]; } }
    fputcsv($o, array_merge($head, array('ひと言', 'AIの下書き', 'Googleのボタン')));
    foreach (ka_rows() as $r) {
        $a = json_decode((string)$r['answers'], true) ?: array();
        $line = array($r['created_at'], $r['spot'] !== '' ? (KA_SPOTS[$r['spot']] ?? $r['spot']) : '', $r['lang'], $r['score']);
        foreach ($qs as $q) { if ($q['id'] !== 'overall') { $line[] = $a[$q['id']] ?? ''; } }
        fputcsv($o, array_merge($line, array($r['comment'], $r['draft_used'] ? '使った' : '', $r['google_click'] ? '押した' : '')));
    }
    fclose($o);
}

function ka_page_qr(): void {
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ka_self();
    $list = array('' => '共通（席や担当を分けない）') + KA_SPOTS;
    ka_head('QRコード｜' . KA_SHOP_NAME, true);
    echo '<header class="top"><div class="wrap"><b>' . h(KA_SHOP_NAME) . '　QRコード</b></div></header><div class="wrap">' . ka_admin_tabs('qr')
       . '<p class="mut noprint">席ごと・担当ごとのQRコードです。どのQRコードから答えたかが、回答の一覧の「場所・担当」に出ます。NFCタグを使うときは、下のURLをタグに書き込んでください。席や担当は kanketo_config.php の KA_SPOTS で増やせます。</p>'
       . '<p class="noprint"><button class="btn" onclick="print()">印刷する</button></p><div class="qrs">';
    $i = 0;
    foreach ($list as $code => $label) {
        $url = $base . ($code !== '' ? '?s=' . rawurlencode((string)$code) : '');
        echo '<div><b>' . h($label) . '</b><div id="qr' . $i . '" data-u="' . h($url) . '" style="margin:10px auto"></div><p style="margin:0;font-weight:700">' . h(t('title')) . '</p><p class="url">' . h($url) . '</p></div>';
        $i++;
    }
    echo '</div></div><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>'
       . '<script>document.querySelectorAll("[data-u]").forEach(function(el){var q=qrcode(0,"M");q.addData(el.dataset.u);q.make();el.innerHTML=q.createSvgTag({cellSize:5,margin:2});var s=el.querySelector("svg");if(s){s.style.width="180px";s.style.height="180px";}});</script>';
    ka_foot();
}
