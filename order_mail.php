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
$p_name       = trim($_POST['p_name']        ?? '');
$p_subtotal   = trim($_POST['p_subtotal']    ?? '');
$p_total      = trim($_POST['p_total']       ?? '');

// ---- ヘルパー関数（グローバルスコープ） ----

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

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

// カラー値（例: "005 イエロー"）から swatch 画像タグを生成
function swatch_img($val, $prefix) {
    if (!$val || $val === '—') return '';
    preg_match('/^(\d{3})/', trim($val), $m);
    if (!$m) return '';
    $url = 'https://ig-rod.jp/swatches/' . $prefix . $m[1] . '.jpg';
    return '<img src="' . $url . '" width="16" height="16" '
         . 'style="vertical-align:middle;border-radius:2px;border:1px solid rgba(255,255,255,0.2);margin-right:5px;object-fit:cover;">';
}

// セクション見出し行
function mail_sec($label) {
    $s = 'padding:6px 0 3px;font-size:8px;font-weight:700;letter-spacing:0.1em;color:#cc2200;border-top:1px solid #2a2a2a;';
    return '<tr><td colspan="2" style="' . $s . '">— ' . h($label) . '</td></tr>';
}

// データ行（値が空なら非表示）
function mail_row($label, $val, $img = '') {
    if ($val === '' || $val === null) return '';
    $ls = 'padding:4px 0;font-size:10px;color:#888;width:110px;vertical-align:middle;border-top:1px solid #1e1e1e;';
    $vs = 'padding:4px 0;font-size:11px;color:#f0f0f0;font-weight:600;vertical-align:middle;border-top:1px solid #1e1e1e;';
    return '<tr><td style="' . $ls . '">' . h($label) . '</td>'
         . '<td style="' . $vs . '">' . $img . h($val) . '</td></tr>';
}

// 価格行
function mail_prow($label, $val) {
    if ($val === '' || $val === null) return '';
    $ls = 'padding:4px 0;font-size:10px;color:#aaa;border-bottom:1px solid #222;';
    $vs = 'padding:4px 0;font-size:10px;color:#f0f0f0;font-weight:600;text-align:right;border-bottom:1px solid #222;';
    return '<tr><td style="' . $ls . '">' . h($label) . '</td><td style="' . $vs . '">' . h($val) . '</td></tr>';
}

// ---- メール HTML 生成 ----

function build_confirm_html($name, $email, $tel, $model,
    $blank_color, $tip_color, $reel_color,
    $thread_main, $thread_tip, $thread_pin,
    $guide, $grip, $name_custom,
    $shipping, $address, $payment, $note,
    $p_base, $p_guide, $p_grip, $p_blank_color, $p_tip_color, $p_name,
    $p_subtotal, $p_total, $is_customer = false) {

    $title   = $is_customer ? 'オーダーを受け付けました' : h($name) . ' 様より新規オーダー';
    $eyebrow = $is_customer ? 'ORDER RECEIVED' : 'ORDER CONFIRMATION';

    $order_rows =
        mail_sec('基本情報')
        . mail_row('お客様名',      $name)
        . mail_row('メールアドレス', $email)
        . mail_row('電話番号',      $tel)

        . mail_sec('ベースモデル')
        . mail_row('モデル', $model)

        . mail_sec('カラーカスタム')
        . mail_row('ブランクスカラー',    $blank_color, swatch_img($blank_color, 'b'))
        . mail_row('ティップカラー',      $tip_color,   swatch_img($tip_color,   'b'))
        . mail_row('リールシートカラー',  $reel_color,  swatch_img($reel_color,  'b'))
        . mail_row('メインスレッド',      $thread_main, swatch_img($thread_main, 't'))
        . mail_row('ティップ部分スレッド', $thread_tip,  swatch_img($thread_tip,  't'))
        . mail_row('ピンラインスレッド',   $thread_pin,  swatch_img($thread_pin,  't'))

        . mail_sec('カスタムオプション')
        . mail_row('ガイド仕様',  $guide)
        . mail_row('グリップ仕様', $grip)
        . mail_row('ネーム仕様',  $name_custom)

        . mail_sec('配送・お支払い')
        . mail_row('送料',         $shipping)
        . mail_row('送り先住所',   $address)
        . mail_row('お支払い方法', $payment)

        . ($note
            ? mail_sec('備考')
              . '<tr><td colspan="2" style="padding:7px 0;font-size:12px;color:#e0e0e0;line-height:1.7;white-space:pre-wrap;">' . h($note) . '</td></tr>'
            : '');

    $price_rows =
        mail_prow('ベースモデル',   $p_base)
        . mail_prow('ガイド仕様',   $p_guide)
        . mail_prow('グリップ仕様', $p_grip)
        . mail_prow('ブランクスカラー', $p_blank_color)
        . mail_prow('ティップカラー',   $p_tip_color)
        . mail_prow('ネーム仕様',       $p_name)
        . mail_prow('合計（税抜）', $p_subtotal);

    return '<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:10px;background:#0d0d0d;font-family:\'Helvetica Neue\',Arial,\'Noto Sans JP\',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;margin:0 auto;">
<tr><td style="background:#111;border:1px solid #2a2a2a;padding:14px 16px 16px;">

  <div style="font-size:8px;font-weight:700;letter-spacing:0.2em;color:#cc2200;margin-bottom:3px;">' . $eyebrow . '</div>
  <div style="font-size:15px;font-weight:900;color:#f8f8f8;margin-bottom:10px;">' . $title . '</div>

  <table width="100%" cellpadding="0" cellspacing="0">
    ' . $order_rows . '

    <tr><td colspan="2" style="padding:8px 0 4px;font-size:8px;font-weight:700;letter-spacing:0.1em;color:#cc2200;border-top:1px solid #2a2a2a;">— 料金確認</td></tr>
    <tr><td colspan="2" style="padding-bottom:4px;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#0d0d0d;border:1px solid #2a2a2a;padding:8px 10px;">
        ' . $price_rows . '
        <tr>
          <td style="padding:8px 0 2px;font-size:11px;font-weight:700;color:#f8f8f8;">税込合計</td>
          <td style="padding:8px 0 2px;font-size:18px;font-weight:900;color:#c8a44a;text-align:right;">' . h($p_total) . '</td>
        </tr>
      </table>
    </td></tr>
  </table>

  <div style="padding-top:8px;border-top:1px solid #2a2a2a;font-size:9px;color:#555;">
    IG Rod Planning — https://ig-rod.jp
  </div>

</td></tr>
</table>
</body>
</html>';
}

// ---- 送信 ----

$html = build_confirm_html(
    $name, $email, $tel, $model,
    $blank_color, $tip_color, $reel_color,
    $thread_main, $thread_tip, $thread_pin,
    $guide, $grip, $name_custom,
    $shipping, $address, $payment, $note,
    $p_base, $p_guide, $p_grip, $p_blank_color, $p_tip_color, $p_name,
    $p_subtotal, $p_total, false
);

$customer_html = build_confirm_html(
    $name, $email, $tel, $model,
    $blank_color, $tip_color, $reel_color,
    $thread_main, $thread_tip, $thread_pin,
    $guide, $grip, $name_custom,
    $shipping, $address, $payment, $note,
    $p_base, $p_guide, $p_grip, $p_blank_color, $p_tip_color, $p_name,
    $p_subtotal, $p_total, true
);

$admin_subject = '【オーダーフォーム】' . $name . ' 様';
$result = send_html_mail($to, $admin_subject, $html, 'go@ig-rod.jp', $email);

$reply_subject = '【IG Rod Planning】オーダーを受け付けました';
send_html_mail($email, $reply_subject, $customer_html, 'IG Rod Planning <go@ig-rod.jp>');

if ($result) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mail failed']);
}
