<?php
// ============================================================
// Formosa One 決済設定
// このファイルを「config.php」という名前でコピーし、値を入れてください。
// config.php は秘密情報なので Git にコミットしないでください（.gitignore 済み）。
// ============================================================
return [
    // Stripe ダッシュボード → 開発者 → APIキー → シークレットキー
    // テスト中は sk_test_… 、本番公開時に sk_live_… に差し替える
    'stripe_secret_key' => 'ここにシークレットキーを貼り付け',

    // Stripe ダッシュボード → 開発者 → Webhook → エンドポイントを追加
    //   URL:    https://（サイトのURL）/api/webhook.php
    //   イベント: checkout.session.completed
    //            checkout.session.async_payment_succeeded
    // 追加後に表示される「署名シークレット」(whsec_…) を入れる
    'stripe_webhook_secret' => 'ここに署名シークレットを貼り付け',

    // サイトの公開URL（最後の / は付けない）。決済後の戻り先に使う
    'site_url' => 'https://example.com',

    // 注文通知を受け取るお店のメールアドレス
    'shop_email' => 'order@example.com',

    // お客様へのメールの差出人（サイトと同じドメインのアドレスにすると迷惑メールになりにくい）
    'from_email' => 'no-reply@example.com',
    'from_name'  => 'Formosa One',

    // 注文完了メールの末尾に入れるお店の情報
    'shop_signature' => "Formosa One（フォルモサワン）\nhttps://example.com\nお問い合わせ：order@example.com",
];
