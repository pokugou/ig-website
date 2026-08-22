<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$to = 'go@ig-rod.jp';

$name         = trim($_POST['name']         ?? '');
$email        = trim($_POST['email']        ?? '');
$tel          = trim($_POST['tel']          ?? '');
$model        = trim($_POST['model']        ?? '');
$guide        = trim($_POST['guide']        ?? '');
$grip         = trim($_POST['grip']         ?? '');
$shipping     = trim($_POST['shipping']     ?? '');
$address      = trim($_POST['address']      ?? '');
$payment      = trim($_POST['payment']      ?? '');
$note         = trim($_POST['note']         ?? '');
$blank_color  = trim($_POST['blank-color']  ?? '');
$tip_color    = trim($_POST['tip-color']    ?? '');
$thread_main  = trim($_POST['thread-main']  ?? '');
$thread_tip   = trim($_POST['thread-tip']   ?? '');
$thread_pin   = trim($_POST['thread-pin']   ?? '');
$name_custom  = trim($_POST['name-custom']  ?? '');
$p_base       = trim($_POST['p_base']       ?? '');
$p_guide      = trim($_POST['p_guide']      ?? '');
$p_grip       = trim($_POST['p_grip']       ?? '');
$p_subtotal   = trim($_POST['p_subtotal']   ?? '');
$p_total      = trim($_POST['p_total']      ?? '');

function send_utf8_mail($to, $subject, $body, $from, $reply_to = '') {
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
    $headers .= 'Content-Transfer-Encoding: base64' . "\r\n";
    $headers .= 'From: ' . $from . "\r\n";
    if ($reply_to) {
        $headers .= 'Reply-To: ' . $reply_to . "\r\n";
    }
    return mail($to, $encoded_subject, base64_encode($body), $headers);
}

// ---- 管理者宛メール ----
$admin_subject = '【オーダーフォーム】' . $name . ' 様';
$admin_body = <<<EOT
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

$result = send_utf8_mail($to, $admin_subject, $admin_body, 'go@ig-rod.jp', $email);

// ---- お客様への自動返信 ----
$reply_subject = '【IG Rod Planning】オーダーを受け付けました';
$reply_body = <<<EOT
{$name} 様

オーダーを受け付けました。
担当者よりご連絡いたします。しばらくお待ちください。

------------------------------------
モデル：{$model}
ガイド仕様：{$guide}
グリップ仕様：{$grip}
税込合計：{$p_total}
------------------------------------

ご不明な点は go@ig-rod.jp またはLINEよりお気軽にお問い合わせください。
https://lin.ee/8eONfci

IG Rod Planning
https://ig-rod.jp
EOT;

send_utf8_mail($email, $reply_subject, $reply_body, 'IG Rod Planning <go@ig-rod.jp>');

if ($result) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mail failed']);
}
