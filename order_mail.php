<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$to = 'go@ig-rod.jp';

$name         = trim($_POST['name']          ?? '');
$email        = trim($_POST['email']         ?? '');
$tel          = trim($_POST['tel']           ?? '');
$model        = trim($_POST['model']         ?? '');
$guide        = trim($_POST['guide']         ?? '');
$grip         = trim($_POST['grip']          ?? '');
$shipping     = trim($_POST['shipping']      ?? '');
$address      = trim($_POST['address']       ?? '');
$payment      = trim($_POST['payment']       ?? '');
$note         = trim($_POST['note']          ?? '');
$blank_color  = trim($_POST['blank-color']   ?? '');
$tip_color    = trim($_POST['tip-color']     ?? '');
$reel_color   = trim($_POST['reel-color']    ?? '');
$thread_main  = trim($_POST['thread-main']   ?? '');
$thread_tip   = trim($_POST['thread-tip']    ?? '');
$thread_pin   = trim($_POST['thread-pin']    ?? '');
$name_custom  = trim($_POST['name-custom']   ?? '');
$p_base       = trim($_POST['p_base']        ?? '');
$p_guide      = trim($_POST['p_guide']       ?? '');
$p_grip       = trim($_POST['p_grip']        ?? '');
$p_blank_color= trim($_POST['p_blank_color'] ?? '');
$p_tip_color  = trim($_POST['p_tip_color']   ?? '');
$p_subtotal   = trim($_POST['p_subtotal']    ?? '');
$p_total      = trim($_POST['p_total']       ?? '');

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function h2($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function row2($label, $value, $ls, $vs) {
    if ($value === '' || $value === '—') return '';
    return '<tr><td style="'.$ls.'">'.h2($label).'</td><td style="'.$vs.'">'.h2($value).'</td></tr>';
}

function send_html_mail($to, $subject, $html_body, $from, $reply_to = '') {
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $boundary = '==boundary_' . md5(uniqid());
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
    $headers .= 'From: ' . $from . "\r\n";
    if ($reply_to) $headers .= 'Reply-To: ' . $reply_to . "\r\n";
    $body  = '--' . $boundary . "\r\n";
    $body .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $body .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
    $body .= chunk_split(base64_encode($html_body)) . "\r\n";
    $body .= '--' . $boundary . '--';
    return mail($to, $encoded_subject, $body, $headers);
}

// ---- 確認メール HTML生成（共通テンプレート） ----
function build_confirm_html($name, $email, $tel, $model, $blank_color, $tip_color, $reel_color,
    $thread_main, $thread_tip, $thread_pin, $guide, $grip, $name_custom,
    $shipping, $address, $payment, $note,
    $p_base, $p_guide, $p_grip, $p_blank_color, $p_tip_color, $p_subtotal, $p_total,
    $is_customer = false) {

    $label_style  = 'padding:3px 0;font-size:10px;color:#888;width:110px;vertical-align:top;';
    $value_style  = 'padding:3px 0;font-size:12px;color:#f0f0f0;font-weight:500;vertical-align:top;';
    $sec_style    = 'font-size:8px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#cc2200;padding:10px 0 5px;border-top:1px solid #2a2a2a;';
    $first_sec    = 'font-size:8px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#cc2200;padding:0 0 5px;';

    $order_rows = '
        <tr><td style="'.$first_sec.'" colspan="2">CUSTOMER</td></tr>'
        . row2('お客様名', $name, $label_style, $value_style)
        . row2('メール', $email, $label_style, $value_style)
        . row2('電話番号', $tel, $label_style, $value_style)
        . '<tr><td style="'.$sec_style.'" colspan="2">MODEL</td></tr>'
        . row2('モデル', $model, $label_style, $value_style)
        . '<tr><td style="'.$sec_style.'" colspan="2">COLOR CUSTOM</td></tr>'
        . row2('ブランクスカラー', $blank_color, $label_style, $value_style)
        . row2('ティップカラー', $tip_color, $label_style, $value_style)
        . ($reel_color ? row2('リールシートカラー', $reel_color, $label_style, $value_style) : '')
        . row2('メインスレッド', $thread_main, $label_style, $value_style)
        . row2('ティップスレッド', $thread_tip, $label_style, $value_style)
        . row2('ピンライン', $thread_pin, $label_style, $value_style)
        . '<tr><td style="'.$sec_style.'" colspan="2">OPTIONS</td></tr>'
        . row2('ガイド仕様', $guide, $label_style, $value_style)
        . row2('グリップ仕様', $grip, $label_style, $value_style)
        . row2('ネーム仕様', $name_custom, $label_style, $value_style)
        . '<tr><td style="'.$sec_style.'" colspan="2">SHIPPING</td></tr>'
        . row2('送料', $shipping, $label_style, $value_style)
        . row2('送り先住所', $address, $label_style, $value_style)
        . row2('お支払い', $payment, $label_style, $value_style);

    $prow = 'padding:4px 0;font-size:11px;color:#aaa;border-bottom:1px solid #222;';
    $pval = 'padding:4px 0;font-size:11px;color:#f0f0f0;font-weight:600;text-align:right;border-bottom:1px solid #222;';

    $price_rows =
        '<tr><td style="'.$prow.'">ベースモデル</td><td style="'.$pval.'">'.h2($p_base).'</td></tr>'
        .'<tr><td style="'.$prow.'">ガイド仕様</td><td style="'.$pval.'">'.h2($p_guide).'</td></tr>'
        .'<tr><td style="'.$prow.'">グリップ仕様</td><td style="'.$pval.'">'.h2($p_grip).'</td></tr>'
        .($p_blank_color ? '<tr><td style="'.$prow.'">ブランクスカラー</td><td style="'.$pval.'">'.h2($p_blank_color).'</td></tr>' : '')
        .($p_tip_color   ? '<tr><td style="'.$prow.'">ティップカラー</td><td style="'.$pval.'">'.h2($p_tip_color).'</td></tr>' : '')
        .'<tr><td style="'.$prow.'">合計（税抜）</td><td style="'.$pval.'">'.h2($p_subtotal).'</td></tr>'
        .'<tr><td style="padding:8px 0 2px;font-size:12px;font-weight:700;color:#f8f8f8;">税込合計</td>'
        .'<td style="padding:8px 0 2px;font-size:18px;font-weight:900;color:#c8a44a;text-align:right;font-family:\'DM Sans\',Arial,sans-serif;">'.h2($p_total).'</td></tr>';

    $note_block = $note ? '
        <tr><td colspan="2" style="padding-top:10px;">
          <div style="font-size:8px;color:#888;font-weight:700;letter-spacing:0.15em;margin-bottom:4px;">NOTES</div>
          <div style="font-size:11px;color:#e0e0e0;line-height:1.6;white-space:pre-wrap;background:#0d0d0d;padding:8px;border:1px solid #2a2a2a;">'.h2($note).'</div>
        </td></tr>' : '';

    $header_label = $is_customer
        ? '<div style="font-size:9px;color:#cc2200;font-weight:700;letter-spacing:0.2em;margin-bottom:4px;">ORDER RECEIVED</div><div style="font-size:16px;font-weight:900;color:#f8f8f8;margin-bottom:14px;">オーダーを受け付けました</div>'
        : '<div style="font-size:9px;color:#cc2200;font-weight:700;letter-spacing:0.2em;margin-bottom:4px;">ORDER CONFIRMATION</div><div style="font-size:16px;font-weight:900;color:#f8f8f8;margin-bottom:14px;">'.h2($name).' 様より新規オーダー</div>';

    return '<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:14px;background:#0d0d0d;font-family:\'Helvetica Neue\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;">
<tr><td style="background:#111;border:1px solid #2a2a2a;padding:18px 20px 16px;">

  ' . $header_label . '

  <table width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <!-- 左：オーダー詳細 -->
    <td style="width:55%;vertical-align:top;padding-right:16px;border-right:1px solid #2a2a2a;">
      <table width="100%" cellpadding="0" cellspacing="0">
        ' . $order_rows . '
        ' . $note_block . '
      </table>
    </td>

    <!-- 右：料金サマリー -->
    <td style="width:45%;vertical-align:top;padding-left:16px;">
      <div style="font-size:8px;font-weight:700;letter-spacing:0.15em;color:#888;padding-bottom:6px;border-bottom:1px solid #2a2a2a;">PRICE SUMMARY</div>
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:4px;">
        ' . $price_rows . '
      </table>
    </td>
  </tr>
  </table>

  <div style="padding-top:12px;border-top:1px solid #2a2a2a;margin-top:12px;font-size:10px;color:#555;">
    IG Rod Planning — https://ig-rod.jp
  </div>

</td></tr>
</table>
</body>
</html>';
}

$html = build_confirm_html(
    $name, $email, $tel, $model, $blank_color, $tip_color, $reel_color,
    $thread_main, $thread_tip, $thread_pin, $guide, $grip, $name_custom,
    $shipping, $address, $payment, $note,
    $p_base, $p_guide, $p_grip, $p_blank_color, $p_tip_color, $p_subtotal, $p_total,
    false
);

$customer_html = build_confirm_html(
    $name, $email, $tel, $model, $blank_color, $tip_color, $reel_color,
    $thread_main, $thread_tip, $thread_pin, $guide, $grip, $name_custom,
    $shipping, $address, $payment, $note,
    $p_base, $p_guide, $p_grip, $p_blank_color, $p_tip_color, $p_subtotal, $p_total,
    true
);

// ---- 管理者宛 ----
$admin_subject = '【オーダーフォーム】' . $name . ' 様';
$result = send_html_mail($to, $admin_subject, $html, 'go@ig-rod.jp', $email);

// ---- お客様宛 ----
$reply_subject = '【IG Rod Planning】オーダーを受け付けました';
send_html_mail($email, $reply_subject, $customer_html, 'IG Rod Planning <go@ig-rod.jp>');

if ($result) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mail failed']);
}
