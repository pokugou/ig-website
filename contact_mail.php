<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

mb_language('Japanese');
mb_internal_encoding('UTF-8');

$to      = 'go@ig-rod.jp';
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// ---- 管理者宛メール ----
$admin_subject = '【お問い合わせ】' . $subject . ' ／ ' . $name . ' 様';
$admin_body = <<<EOT
■ お問い合わせ内容

お名前：{$name}
メールアドレス：{$email}
件名：{$subject}

内容：
{$message}
EOT;
$admin_headers  = 'From: go@ig-rod.jp' . "\r\n";
$admin_headers .= 'Reply-To: ' . $email . "\r\n";
$admin_headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

$result = mb_send_mail($to, $admin_subject, $admin_body, $admin_headers);

// ---- お客様への自動返信 ----
$reply_subject = '【IG Rod Planning】お問い合わせを受け付けました';
$reply_body = <<<EOT
{$name} 様

お問い合わせ内容を受け付けました。
通常2〜3営業日以内にご返信いたします。しばらくお待ちください。

※ お急ぎの場合は LINE公式アカウント よりご連絡ください。
https://lin.ee/8eONfci

──────────────────────
IG Rod Planning
https://ig-rod.jp
──────────────────────
EOT;
$reply_headers  = 'From: IG Rod Planning <go@ig-rod.jp>' . "\r\n";
$reply_headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

mb_send_mail($email, $reply_subject, $reply_body, $reply_headers);

echo json_encode(['ok' => (bool)$result]);
