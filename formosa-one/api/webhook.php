<?php
// Receives Stripe events. When a payment succeeds it:
//   1. saves the order to api/data/orders/
//   2. reduces stock in products.json
//   3. emails the customer and the shop
// Stripe retries an event until it gets HTTP 200, so each order is handled only once.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$payload = (string)file_get_contents('php://input');
$cfg = config();

// ---- verify the Stripe signature ----
$header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$ts = null;
$sigs = [];
foreach (explode(',', $header) as $part) {
    [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
    if ($k === 't') $ts = (int)$v;
    if ($k === 'v1') $sigs[] = $v;
}
$expected = hash_hmac('sha256', $ts . '.' . $payload, $cfg['stripe_webhook_secret']);
$valid = $ts !== null && abs(time() - $ts) <= 300;
$matched = false;
foreach ($sigs as $s) {
    if (hash_equals($expected, $s)) $matched = true;
}
if (!$valid || !$matched) {
    json_out(['error' => 'invalid signature'], 400);
}

$event = json_decode($payload, true);
$type  = $event['type'] ?? '';
$session = $event['data']['object'] ?? [];

// Card payments arrive as "completed" + paid.
// Konbini / bank transfer arrive later as "async_payment_succeeded".
$isPaid = ($type === 'checkout.session.completed' && ($session['payment_status'] ?? '') === 'paid')
       || $type === 'checkout.session.async_payment_succeeded';
if (!$isPaid) {
    json_out(['received' => true]);
}

$sessionId = (string)($session['id'] ?? '');
if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
    json_out(['error' => 'bad session id'], 400);
}

$ordersDir = DATA_DIR . '/orders';
if (!is_dir($ordersDir) && !mkdir($ordersDir, 0750, true) && !is_dir($ordersDir)) {
    error_log('[formosa-one] cannot create ' . $ordersDir);
    json_out(['error' => 'storage'], 500);
}
$orderFile = $ordersDir . '/' . $sessionId . '.json';

// Claim this session atomically so a retried event never sends mail twice.
$fh = @fopen($orderFile, 'x');
if ($fh === false) {
    json_out(['received' => true, 'duplicate' => true]);
}

// ---- gather order details ----
[$st, $li] = stripe('GET', "checkout/sessions/{$sessionId}/line_items", ['limit' => 100]);
if ($st !== 200) {
    fclose($fh);
    unlink($orderFile); // let Stripe retry
    error_log('[formosa-one] line_items fetch failed for ' . $sessionId);
    json_out(['error' => 'stripe'], 502);
}

$customer = $session['customer_details'] ?? [];
$ship = $session['shipping_details'] ?? ($session['collected_information']['shipping_details'] ?? []);
$addr = $ship['address'] ?? [];
$orderNo = 'FO-' . date('ymd-His') . '-' . strtoupper(substr(md5($sessionId), 0, 4));

$lines = [];
foreach ($li['data'] ?? [] as $row) {
    $lines[] = sprintf('・%s × %d　%s', $row['description'], $row['quantity'], yen((int)$row['amount_total']));
}
$shippingFee = (int)($session['total_details']['amount_shipping'] ?? 0);
$total = (int)($session['amount_total'] ?? 0);
$address = trim(sprintf(
    "〒%s\n%s%s%s %s",
    $addr['postal_code'] ?? '',
    $addr['state'] ?? '',
    $addr['city'] ?? '',
    $addr['line1'] ?? '',
    $addr['line2'] ?? ''
));

$order = [
    'order_no'   => $orderNo,
    'session_id' => $sessionId,
    'created_at' => date('c'),
    'name'       => $ship['name'] ?? ($customer['name'] ?? ''),
    'email'      => $customer['email'] ?? '',
    'phone'      => $customer['phone'] ?? '',
    'address'    => $address,
    'items'      => $li['data'] ?? [],
    'shipping'   => $shippingFee,
    'total'      => $total,
    'cart'       => $session['metadata']['cart'] ?? '',
];
fwrite($fh, json_encode($order, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fclose($fh);

// ---- reduce stock ----
$pf = fopen(PRODUCTS_FILE, 'c+');
if ($pf && flock($pf, LOCK_EX)) {
    $catalog = json_decode(stream_get_contents($pf), true);
    $qtyById = [];
    foreach (array_filter(explode(',', $order['cart'])) as $pair) {
        [$id, $q] = array_map('intval', explode('x', $pair));
        $qtyById[$id] = $q;
    }
    foreach ($catalog['products'] as &$p) {
        if (isset($qtyById[(int)$p['id']])) {
            $p['stock'] = max(0, (int)$p['stock'] - $qtyById[(int)$p['id']]);
        }
    }
    unset($p);
    ftruncate($pf, 0);
    rewind($pf);
    fwrite($pf, json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($pf);
    flock($pf, LOCK_UN);
    fclose($pf);
} else {
    error_log('[formosa-one] could not update stock for ' . $orderNo);
}

// ---- emails ----
$itemsText = implode("\n", $lines);
$summary = <<<TXT
ご注文番号：{$orderNo}
ご注文日時：{$order['created_at']}

【ご注文内容】
{$itemsText}

送料：{$shippingFee}円
合計（税込）：{$total}円

【お届け先】
{$order['name']} 様
{$address}
TEL：{$order['phone']}
TXT;

$fromHeader = 'From: ' . mb_encode_mimeheader($cfg['from_name']) . ' <' . $cfg['from_email'] . '>';

if ($order['email'] !== '') {
    $customerBody = "{$order['name']} 様\n\nこのたびは Formosa One をご利用いただき、誠にありがとうございます。\n"
        . "以下の内容でご注文を承りました。発送の準備が整いましたら、あらためてご連絡いたします。\n\n"
        . $summary . "\n\n――――――――――――\n" . $cfg['shop_signature'];
    mb_send_mail($order['email'], '【Formosa One】ご注文ありがとうございます（' . $orderNo . '）', $customerBody,
        $fromHeader . "\r\nReply-To: " . $cfg['shop_email']);
}

$shopBody = "新しい注文が入りました。\n\n" . $summary . "\n\nメール：{$order['email']}\nStripe：https://dashboard.stripe.com/payments（セッション {$sessionId}）";
mb_send_mail($cfg['shop_email'], '【新規注文】' . $orderNo . '　' . yen($total), $shopBody, $fromHeader);

json_out(['received' => true]);
