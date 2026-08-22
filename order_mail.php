<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://ig-rod.jp');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$to = 'go@ig-rod.jp';

$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$tel     = trim($_POST['tel']     ?? '');
$model   = trim($_POST['model']   ?? '');
$guide   = trim($_POST['guide']   ?? '');
$grip    = trim($_POST['grip']    ?? '');
$shipping = trim($_POST['shipping'] ?? '');
$address = trim($_POST['address'] ?? '');
$payment = trim($_POST['payment'] ?? '');
$note    = trim($_POST['note']    ?? '');

$blank_color  = trim($_POST['blank-color']  ?? '');
$tip_color    = trim($_POST['tip-color']    ?? '');
$thread_main  = trim($_POST['thread-main']  ?? '');
$thread_tip   = trim($_POST['thread-tip']   ?? '');
$thread_pin   = trim($_POST['thread-pin']   ?? '');
$name_custom  = trim($_POST['name-custom']  ?? '');

$p_base     = trim($_POST['p_base']     ?? '');
$p_guide    = trim($_POST['p_guide']    ?? '');
$p_grip     = trim($_POST['p_grip']     ?? '');
$p_subtotal = trim($_POST['p_subtotal'] ?? '');
$p_total    = trim($_POST['p_total']    ?? '');

$subject = '【オーダーフォーム】' . $name . ' 様';

$body = <<<EOT
■ お客様情報
お客様名：{$name}
メールアドレス：{$email}
電話番号：{$tel}

■ オーダー内容
モデル：{$model}
ブランクスカラー：{$blank_color}
ティップカラー：{$tip_color}
メインスレッド：{$thread_main}
ティップ部分スレッド：{$thread_tip}
ピンライン：{$thread_pin}
ガイド仕様：{$guide}
グリップ仕様：{$grip}
ネーム仕様：{$name_custom}

■ 配送・支払
送料：{$shipping}
送り先住所：{$address}
お支払い方法：{$payment}

■ 料金
ベースモデル：{$p_base}
ガイド仕様：{$p_guide}
グリップ仕様：{$p_grip}
合計（税抜）：{$p_subtotal}
税込合計：{$p_total}

■ 備考
{$note}
EOT;

$headers  = 'From: go@ig-rod.jp' . "\r\n";
$headers .= 'Reply-To: ' . $email . "\r\n";
$headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

mb_language('Japanese');
mb_internal_encoding('UTF-8');

$result = mb_send_mail($to, $subject, $body, $headers);

// ---- お客様への自動返信 ----
$reply_subject = '【IG Rod Planning】オーダーを受け付けました';
$reply_body = <<<EOT
{$name} 様

この度はIG Rod Planningへオーダーいただき、誠にありがとうございます。
以下の内容でオーダーを受け付けました。

━━━━━━━━━━━━━━━━━━━━━━
■ オーダー内容
モデル：{$model}
ブランクスカラー：{$blank_color}
ティップカラー：{$tip_color}
メインスレッド：{$thread_main}
ティップ部分スレッド：{$thread_tip}
ピンライン：{$thread_pin}
ガイド仕様：{$guide}
グリップ仕様：{$grip}
ネーム仕様：{$name_custom}

■ 配送・支払
送料：{$shipping}
送り先住所：{$address}
お支払い方法：{$payment}

■ 料金
ベースモデル：{$p_base}
ガイド仕様：{$p_guide}
グリップ仕様：{$p_grip}
合計（税抜）：{$p_subtotal}
税込合計：{$p_total}

■ 備考
{$note}
━━━━━━━━━━━━━━━━━━━━━━

担当者よりご連絡いたします。
ご不明な点は go@ig-rod.jp またはLINE公式アカウントまでお気軽にお問い合わせください。
https://lin.ee/8eONfci

※ このメールは自動送信です。このメールへの返信は受け付けておりません。

──────────────────────
IG Rod Planning
https://ig-rod.jp
──────────────────────
EOT;
$reply_headers  = 'From: IG Rod Planning <go@ig-rod.jp>' . "\r\n";
$reply_headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

mb_send_mail($email, $reply_subject, $reply_body, $reply_headers);

if ($result) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mail failed']);
}
