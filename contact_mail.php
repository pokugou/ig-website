<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$to      = 'go@ig-rod.jp';
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

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
$admin_subject = '【お問い合わせ】' . $subject . ' / ' . $name . ' 様';
$admin_body = <<<EOT
お名前：{$name}
メールアドレス：{$email}
件名：{$subject}

お問い合わせ内容：
{$message}
EOT;

$result = send_utf8_mail($to, $admin_subject, $admin_body, 'go@ig-rod.jp', $email);

// ---- お客様への自動返信 ----
$reply_subject = '【IG Rod Planning】お問い合わせを受け付けました';
$reply_body = <<<EOT
{$name} 様

お問い合わせ内容を受け付けました。
通常2〜3営業日以内にご返信いたします。しばらくお待ちください。
※タイミング次第ではLINEの方が早く返信できますのでお急ぎの方はLINEから返信お願い致します。
━━━━━━━━━━━━━━━━━━━━━━
件名：{$subject}
お問い合わせ内容：
{$message}
━━━━━━━━━━━━━━━━━━━━━━
※ お急ぎの場合は LINE公式アカウント よりご連絡ください。
https://lin.ee/8eONfci

IG Rod Planning
https://ig-rod.jp
EOT;

send_utf8_mail($email, $reply_subject, $reply_body, 'IG Rod Planning <go@ig-rod.jp>');

echo json_encode(['ok' => (bool)$result]);
