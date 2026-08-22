<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$to    = 'go@ig-rod.jp';
$name  = trim($_POST['name']  ?? '');
$email = trim($_POST['email'] ?? '');
$img   = trim($_POST['order_img'] ?? ''); // base64 JPEG (data URL の prefix なし)

// 画像なし fallback 用テキスト
$p_total = trim($_POST['p_total'] ?? '');
$model   = trim($_POST['model']   ?? '');

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// CID埋め込み画像メール
function send_image_mail($to, $subject, $img_b64, $name, $from, $reply_to = '') {
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $cid = 'orderimg_' . md5(uniqid());
    $rb  = 'rb_' . md5(uniqid()); // related boundary

    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/related; boundary="' . $rb . '"' . "\r\n";
    $headers .= 'From: ' . $from . "\r\n";
    if ($reply_to) $headers .= 'Reply-To: ' . $reply_to . "\r\n";

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
          . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
          . '<body style="margin:0;padding:0;background:#0a0a0a;">'
          . '<img src="cid:' . $cid . '" style="max-width:100%;display:block;" alt="' . h($name) . ' オーダー内容">'
          . '</body></html>';

    $body  = '--' . $rb . "\r\n";
    $body .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $body .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
    $body .= chunk_split(base64_encode($html)) . "\r\n";
    $body .= '--' . $rb . "\r\n";
    $body .= 'Content-Type: image/jpeg' . "\r\n";
    $body .= 'Content-Transfer-Encoding: base64' . "\r\n";
    $body .= 'Content-ID: <' . $cid . '>' . "\r\n";
    $body .= 'Content-Disposition: inline; filename="order.jpg"' . "\r\n\r\n";
    $body .= chunk_split($img_b64) . "\r\n";
    $body .= '--' . $rb . '--';

    return mail($to, $encoded_subject, $body, $headers);
}

// 画像が届いた場合 → 画像メール送信
if ($img !== '') {
    $admin_subject = '【オーダーフォーム】' . $name . ' 様';
    $reply_subject = '【IG Rod Planning】オーダーを受け付けました';

    $result = send_image_mail($to, $admin_subject, $img, $name, 'go@ig-rod.jp', $email);
    send_image_mail($email, $reply_subject, $img, $name, 'IG Rod Planning <go@ig-rod.jp>');

    echo json_encode($result ? ['ok' => true] : ['ok' => false, 'error' => 'mail failed']);
    exit;
}

// 画像なし fallback → テキストメール
function send_text_mail($to, $subject, $text, $from, $reply_to = '') {
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
    $headers .= 'Content-Transfer-Encoding: base64' . "\r\n";
    $headers .= 'From: ' . $from . "\r\n";
    if ($reply_to) $headers .= 'Reply-To: ' . $reply_to . "\r\n";
    return mail($to, $encoded_subject, chunk_split(base64_encode($text)), $headers);
}

$fields = ['name','email','tel','model','guide','grip','blank-color','tip-color','reel-color',
           'thread-main','thread-tip','thread-pin','name-custom','shipping','address','payment','note',
           'p_base','p_guide','p_grip','p_subtotal','p_total'];
$lines = [];
foreach ($fields as $f) {
    $v = trim($_POST[$f] ?? '');
    if ($v !== '') $lines[] = $f . ': ' . $v;
}
$body_text = implode("\n", $lines);

$result = send_text_mail($to, '【オーダーフォーム】'.$name.' 様', $body_text, 'go@ig-rod.jp', $email);
send_text_mail($email, '【IG Rod Planning】オーダーを受け付けました', $body_text, 'IG Rod Planning <go@ig-rod.jp>');

echo json_encode($result ? ['ok' => true] : ['ok' => false, 'error' => 'mail failed']);
