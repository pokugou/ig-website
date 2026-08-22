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

$mail_subject = '【お問い合わせ】' . $subject . ' ／ ' . $name . ' 様';

$body = <<<EOT
■ お問い合わせ内容

お名前：{$name}
メールアドレス：{$email}
件名：{$subject}

内容：
{$message}
EOT;

$headers  = 'From: noreply@ig-rod.jp' . "\r\n";
$headers .= 'Reply-To: ' . $email . "\r\n";
$headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

mb_language('Japanese');
mb_internal_encoding('UTF-8');

$result = mb_send_mail($to, $mail_subject, $body, $headers);

echo json_encode(['ok' => (bool)$result]);
