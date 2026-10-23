<?php
/**
 * デモ（https://proto.exbridge.jp/kanketo/）の設定。架空のお店。
 * heteml から社内の gemma4 には届かないので、当社サーバーの共通の gemma4 中継（kaima/relay・:18343）を通す。
 * 合言葉は scripts/deploy_demo.sh が kaima/.env の RELAY_CLIENT_KANKETO から差し込む（リポジトリに置かない）。
 * デモで DeepSeek など有料のAPIは使わない。
 */
define('KA_SHOP_NAME', 'カフェ くらげ（デモ）');
define('KA_TEMPLATE', 'restaurant');
define('KA_SPOTS', array('t1' => '1番テーブル', 't2' => '2番テーブル', 'counter' => 'カウンター', 'staff-sato' => '担当：佐藤'));
define('KA_GOOGLE_PLACE_ID', '');
define('KA_AI_DRAFT', true);
define('KA_API_BASE', 'http://exbridge.ddns.net:18343/v1');
define('KA_API_KEY', '__KA_API_KEY__');
define('KA_MODEL', 'gemma4');
define('KA_ADMIN_PASSWORD', 'demo');
define('KA_DEMO', true);
define('KA_RATE_SUBMIT', 15);
define('KA_RATE_DRAFT', 10);
