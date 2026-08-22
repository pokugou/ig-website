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

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function send_html_mail($to, $subject, $html_body, $from, $reply_to = '') {
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $boundary = '==boundary_' . md5(uniqid());
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
    $headers .= 'From: ' . $from . "\r\n";
    if ($reply_to) {
        $headers .= 'Reply-To: ' . $reply_to . "\r\n";
    }
    $body  = '--' . $boundary . "\r\n";
    $body .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $body .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
    $body .= chunk_split(base64_encode($html_body)) . "\r\n";
    $body .= '--' . $boundary . '--';
    return mail($to, $encoded_subject, $body, $headers);
}

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

function row($label, $value) {
    return '
    <tr>
      <td style="padding:10px 14px;color:#999;font-size:12px;width:140px;vertical-align:top;border-bottom:1px solid #2a2a2a;">' . h($label) . '</td>
      <td style="padding:10px 14px;color:#f8f8f8;font-size:13px;font-weight:500;border-bottom:1px solid #2a2a2a;">' . h($value) . '</td>
    </tr>';
}

function section_label($text) {
    return '
    <tr>
      <td colspan="2" style="padding:20px 14px 8px;font-size:10px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:#cc2200;border-bottom:1px solid #2a2a2a;">' . $text . '</td>
    </tr>';
}

// ---- 管理者宛 HTML メール ----
$admin_subject = '【オーダーフォーム】' . $name . ' 様';

$admin_html = '<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:20px;background:#0d0d0d;font-family:\'Helvetica Neue\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:680px;margin:0 auto;">
  <tr>
    <td style="background:#111;border:1px solid #2a2a2a;">

      <!-- ヘッダー -->
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="padding:24px 24px 16px;border-bottom:1px solid #2a2a2a;">
            <div style="font-size:10px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:#cc2200;margin-bottom:6px;">ORDER CONFIRMATION</div>
            <div style="font-size:22px;font-weight:900;color:#f8f8f8;letter-spacing:-0.02em;">オーダー内容確認</div>
          </td>
        </tr>
        <tr>
          <td style="padding:10px 24px;font-size:12px;color:#888;border-bottom:1px solid #2a2a2a;">
            ' . h($name) . ' 様より新しいオーダーが届きました
          </td>
        </tr>
      </table>

      <!-- 詳細テーブル -->
      <table width="100%" cellpadding="0" cellspacing="0" style="padding:0 10px;">
        ' . section_label('CUSTOMER INFO — お客様情報') . '
        ' . row('お客様名', $name) . '
        ' . row('メールアドレス', $email) . '
        ' . row('電話番号', $tel) . '

        ' . section_label('ORDER — オーダー内容') . '
        ' . row('モデル', $model) . '
        ' . row('ブランクスカラー', $blank_color) . '
        ' . row('ティップカラー', $tip_color) . '
        ' . row('メインスレッド', $thread_main) . '
        ' . row('ティップ部分スレッド', $thread_tip) . '
        ' . row('ピンライン', $thread_pin) . '
        ' . row('ガイド仕様', $guide) . '
        ' . row('グリップ仕様', $grip) . '
        ' . row('ネーム仕様', $name_custom) . '

        ' . section_label('SHIPPING & PAYMENT — 配送・支払') . '
        ' . row('送料', $shipping) . '
        ' . row('送り先住所', $address) . '
        ' . row('お支払い方法', $payment) . '
      </table>

      <!-- 料金ボックス -->
      <table width="100%" cellpadding="0" cellspacing="0" style="padding:16px 20px;">
        <tr>
          <td>
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#0d0d0d;border:1px solid #2a2a2a;padding:0;">
              <tr><td colspan="2" style="padding:14px 16px 8px;font-size:10px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:#888;">PRICE — 料金</td></tr>
              <tr>
                <td style="padding:8px 16px;color:#aaa;font-size:13px;border-bottom:1px solid #222;">ベースモデル</td>
                <td style="padding:8px 16px;color:#f8f8f8;font-weight:700;font-size:13px;border-bottom:1px solid #222;text-align:right;">' . h($p_base) . '</td>
              </tr>
              <tr>
                <td style="padding:8px 16px;color:#aaa;font-size:13px;border-bottom:1px solid #222;">ガイド仕様</td>
                <td style="padding:8px 16px;color:#f8f8f8;font-weight:700;font-size:13px;border-bottom:1px solid #222;text-align:right;">' . h($p_guide) . '</td>
              </tr>
              <tr>
                <td style="padding:8px 16px;color:#aaa;font-size:13px;border-bottom:1px solid #222;">グリップ仕様</td>
                <td style="padding:8px 16px;color:#f8f8f8;font-weight:700;font-size:13px;border-bottom:1px solid #222;text-align:right;">' . h($p_grip) . '</td>
              </tr>
              <tr>
                <td style="padding:8px 16px;color:#aaa;font-size:13px;border-bottom:1px solid #2a2a2a;">合計（税抜）</td>
                <td style="padding:8px 16px;color:#f8f8f8;font-weight:700;font-size:13px;border-bottom:1px solid #2a2a2a;text-align:right;">' . h($p_subtotal) . '</td>
              </tr>
              <tr>
                <td style="padding:14px 16px;font-size:14px;font-weight:700;color:#f8f8f8;">税込合計</td>
                <td style="padding:14px 16px;font-size:22px;font-weight:900;color:#c8a44a;text-align:right;font-family:\'DM Sans\',\'Helvetica Neue\',Arial,sans-serif;">' . h($p_total) . '</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- 備考 -->
      ' . (!empty($note) ? '
      <table width="100%" cellpadding="0" cellspacing="0" style="padding:0 20px 16px;">
        <tr>
          <td style="background:#0d0d0d;border:1px solid #2a2a2a;padding:14px 16px;">
            <div style="font-size:10px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:#888;margin-bottom:8px;">NOTES — 備考</div>
            <div style="font-size:13px;color:#f8f8f8;line-height:1.7;white-space:pre-wrap;">' . h($note) . '</div>
          </td>
        </tr>
      </table>' : '') . '

      <!-- フッター -->
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="padding:14px 24px;border-top:1px solid #2a2a2a;">
            <div style="font-size:11px;color:#555;">IG Rod Planning — https://ig-rod.jp</div>
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>
</body>
</html>';

$result = send_html_mail($to, $admin_subject, $admin_html, 'go@ig-rod.jp', $email);

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
