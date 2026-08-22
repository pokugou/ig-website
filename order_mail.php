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

// 通常行（値が空なら非表示）
function mrow($label, $val) {
    if ($val === '' || $val === null) return '';
    $L = 'padding:3px 8px;font-size:10px;color:#999;width:36%;background:#1c1c1c;border-bottom:1px solid #252525;vertical-align:top;';
    $V = 'padding:3px 8px;font-size:10px;color:#ebebeb;background:#141414;border-bottom:1px solid #252525;vertical-align:top;';
    return '<tr><td style="'.$L.'">'.h($label).'</td><td style="'.$V.'">'.h($val).'</td></tr>';
}

// 料金行（空・¥0は非表示）
function prow($label, $val) {
    if ($val === '' || $val === null) return '';
    if (strpos($val, '¥0') !== false) return '';
    $L = 'padding:3px 8px;font-size:10px;color:#999;width:36%;background:#1c1c1c;border-bottom:1px solid #252525;';
    $V = 'padding:3px 8px;font-size:10px;color:#ebebeb;background:#141414;border-bottom:1px solid #252525;text-align:right;';
    return '<tr><td style="'.$L.'">'.h($label).'</td><td style="'.$V.'">'.h($val).'</td></tr>';
}

// 区切り行（グループ見出し）
function msep($label) {
    return '<tr><td colspan="2" style="padding:2px 8px;font-size:9px;font-weight:700;letter-spacing:0.1em;'
         . 'color:#cc2200;background:#181818;border-bottom:1px solid #252525;border-top:2px solid #252525;">'
         . h($label) . '</td></tr>';
}

function build_confirm_html($name, $email, $tel, $model,
    $blank_color, $tip_color, $reel_color,
    $thread_main, $thread_tip, $thread_pin,
    $guide, $grip, $name_custom,
    $shipping, $address, $payment, $note,
    $p_base, $p_guide, $p_grip, $p_blank_color, $p_tip_color, $p_name,
    $p_subtotal, $p_total, $is_customer = false) {

    $title = $is_customer ? 'オーダーを受け付けました' : h($name).' 様より新規オーダー';

    $rows =
        msep('基本情報')
        . mrow('お客様名',      $name)
        . mrow('メールアドレス', $email)
        . mrow('電話番号',      $tel)
        . msep('ベースモデル')
        . mrow('モデル',        $model)
        . msep('カラーカスタム')
        . mrow('ブランクスカラー',     $blank_color)
        . mrow('ティップカラー',       $tip_color)
        . mrow('リールシートカラー',   $reel_color)
        . mrow('メインスレッド',       $thread_main)
        . mrow('ティップ部分スレッド', $thread_tip)
        . mrow('ピンラインスレッド',   $thread_pin)
        . msep('カスタムオプション')
        . mrow('ガイド仕様',   $guide)
        . mrow('グリップ仕様', $grip)
        . mrow('ネーム仕様',   $name_custom)
        . msep('配送・お支払い')
        . mrow('送料',         $shipping)
        . mrow('送り先住所',   $address)
        . mrow('お支払い方法', $payment)
        . ($note ? mrow('備考', $note) : '')
        . msep('料金詳細')
        . prow('基本料金',        $p_base)
        . prow('ガイド仕様',      $p_guide)
        . prow('グリップ仕様',    $p_grip)
        . prow('ブランクスカラー', $p_blank_color)
        . prow('ティップカラー',   $p_tip_color)
        . prow('ネーム仕様',       $p_name)
        . '<tr>'
          . '<td style="padding:4px 8px;font-size:10px;font-weight:700;color:#f0f0f0;background:#222;border-bottom:1px solid #333;">合計金額（税抜）</td>'
          . '<td style="padding:4px 8px;font-size:10px;font-weight:700;color:#f0f0f0;background:#222;border-bottom:1px solid #333;text-align:right;">'.h($p_subtotal).'</td>'
        . '</tr>'
        . '<tr>'
          . '<td style="padding:6px 8px;font-size:11px;font-weight:700;color:#c8a44a;background:#1a1609;">税込合計金額</td>'
          . '<td style="padding:6px 8px;font-size:14px;font-weight:900;color:#c8a44a;background:#1a1609;text-align:right;">'.h($p_total).'</td>'
        . '</tr>';

    return '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">'
         . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
         . '<body style="margin:0;padding:8px;background:#0a0a0a;font-family:\'Helvetica Neue\',Arial,\'Noto Sans JP\',sans-serif;">'
         . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:500px;margin:0 auto;border:1px solid #2a2a2a;">'
         . '<tr><td style="padding:7px 8px 6px;background:#111;border-bottom:2px solid #cc2200;">'
           . '<div style="font-size:8px;font-weight:700;letter-spacing:0.15em;color:#cc2200;">IG Rod Planning</div>'
           . '<div style="font-size:13px;font-weight:900;color:#f8f8f8;margin-top:1px;">'.$title.'</div>'
         . '</td></tr>'
         . '<tr><td style="padding:0;">'
           . '<table width="100%" cellpadding="0" cellspacing="0">'.$rows.'</table>'
         . '</td></tr>'
         . '<tr><td style="padding:5px 10px;font-size:9px;color:#444;background:#0f0f0f;border-top:1px solid #222;">'
           . 'ig-rod.jp'
         . '</td></tr>'
         . '</table></body></html>';
}

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

$admin_subject  = '【オーダーフォーム】' . $name . ' 様';
$reply_subject  = '【IG Rod Planning】オーダーを受け付けました';
$result = send_html_mail($to, $admin_subject, $html, 'go@ig-rod.jp', $email);
send_html_mail($email, $reply_subject, $customer_html, 'IG Rod Planning <go@ig-rod.jp>');

echo json_encode($result ? ['ok' => true] : ['ok' => false, 'error' => 'mail failed']);
