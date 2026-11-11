<?php
// Shared helpers for checkout.php and webhook.php. Not meant to be requested directly.
declare(strict_types=1);

mb_language('Japanese');
mb_internal_encoding('UTF-8');

const PRODUCTS_FILE = __DIR__ . '/../products.json';
const DATA_DIR      = __DIR__ . '/data';

function json_out(array $body, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            error_log('[formosa-one] api/config.php is missing');
            json_out(['error' => '決済の準備中です。しばらくしてからお試しください'], 503);
        }
        $cfg = require $file;
    }
    return $cfg;
}

/** @return array{shipping: array, products: array<int, array>} products keyed by id */
function load_catalog(): array
{
    $data = json_decode((string)file_get_contents(PRODUCTS_FILE), true);
    if (!is_array($data) || !isset($data['products'])) {
        error_log('[formosa-one] products.json could not be read');
        json_out(['error' => '商品情報を読み込めませんでした'], 500);
    }
    $byId = [];
    foreach ($data['products'] as $p) {
        $byId[(int)$p['id']] = $p;
    }
    return ['shipping' => $data['shipping'], 'products' => $byId];
}

/**
 * Call the Stripe REST API.
 * @return array{0:int,1:array} [HTTP status, decoded body]
 */
function stripe(string $method, string $path, array $params = []): array
{
    $url = 'https://api.stripe.com/v1/' . $path;
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $ch = curl_init();
    $opts = [
        CURLOPT_USERPWD        => config()['stripe_secret_key'] . ':',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Stripe-Version: 2024-06-20'],
    ];
    if ($method === 'GET') {
        $opts[CURLOPT_URL] = $query === '' ? $url : $url . '?' . $query;
    } else {
        $opts[CURLOPT_URL] = $url;
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $query;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        error_log('[formosa-one] Stripe request failed: ' . curl_error($ch));
        $raw = '{}';
        $status = 502;
    }
    curl_close($ch);
    return [$status, json_decode($raw, true) ?: []];
}

function yen(int $n): string
{
    return number_format($n) . '円';
}
