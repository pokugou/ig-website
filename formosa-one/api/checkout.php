<?php
// Creates a Stripe Checkout session from the cart and returns its URL.
// Prices always come from products.json on the server, never from the browser.
declare(strict_types=1);
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['error' => 'POST only'], 405);
}

$body  = json_decode((string)file_get_contents('php://input'), true);
$items = $body['items'] ?? null;
if (!is_array($items) || count($items) === 0 || count($items) > 50) {
    json_out(['error' => '買い物かごの内容が正しくありません'], 400);
}

$cfg     = config();
$catalog = load_catalog();
$lineItems = [];
$cartMeta  = [];
$subtotal  = 0;

foreach ($items as $it) {
    $id  = (int)($it['id'] ?? 0);
    $qty = (int)($it['qty'] ?? 0);
    $p   = $catalog['products'][$id] ?? null;
    if ($p === null || $qty < 1 || $qty > 99) {
        json_out(['error' => '買い物かごに販売していない商品が含まれています。かごを確認してください'], 400);
    }
    $stock = (int)($p['stock'] ?? 0);
    if ($qty > $stock) {
        $msg = $stock > 0
            ? "「{$p['name']}」の在庫は残り{$stock}点です。数量を変更してください"
            : "「{$p['name']}」は在庫切れになりました。かごから削除してください";
        json_out(['error' => $msg], 409);
    }
    $price = (int)$p['price'];
    $subtotal += $price * $qty;
    $lineItems[] = [
        'quantity'   => $qty,
        'price_data' => [
            'currency'     => 'jpy',
            'unit_amount'  => $price,
            'product_data' => [
                'name'     => $p['name'],
                'metadata' => ['product_id' => (string)$id],
            ],
        ],
    ];
    $cartMeta[] = $id . 'x' . $qty;
}

$fee = $subtotal >= (int)$catalog['shipping']['freeOver'] ? 0 : (int)$catalog['shipping']['fee'];
$site = rtrim($cfg['site_url'], '/');

$params = [
    'mode'        => 'payment',
    'locale'      => 'ja',
    'line_items'  => $lineItems,
    'shipping_address_collection' => ['allowed_countries' => ['JP']],
    'phone_number_collection'     => ['enabled' => 'true'],
    'shipping_options' => [[
        'shipping_rate_data' => [
            'type'         => 'fixed_amount',
            'display_name' => $fee === 0 ? '送料無料' : '送料',
            'fixed_amount' => ['amount' => $fee, 'currency' => 'jpy'],
        ],
    ]],
    // product id x quantity, read by webhook.php to reduce stock
    'metadata'    => ['cart' => implode(',', $cartMeta)],
    'success_url' => $site . '/success.html?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'  => $site . '/?canceled=1',
];

[$status, $session] = stripe('POST', 'checkout/sessions', $params);
if ($status !== 200 || empty($session['url'])) {
    error_log('[formosa-one] Checkout session error: ' . json_encode($session['error'] ?? $session, JSON_UNESCAPED_UNICODE));
    json_out(['error' => '決済画面を開けませんでした。時間をおいてお試しください'], 502);
}

json_out(['url' => $session['url']]);
