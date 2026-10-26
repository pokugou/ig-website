# TODO通知サーバーの設定（Cloudflare・最初の1回だけ）

1. https://dash.cloudflare.com → **Workers & Pages** → **Create** → **Create Worker**
   名前を `ig-push` にして **Deploy**（そのまま）。
2. 作ったWorkerの **Edit code** を開き、中身を全部消して `worker.js` の内容を貼り付け → **Deploy**。
3. 左メニュー **Storage & Databases → KV → Create** で、名前 `ig-push-kv` のKVを作る。
4. `ig-push` の **Settings → Bindings → Add → KV namespace** で、
   Variable name に `PUSH_KV`、KV namespace に `ig-push-kv` を選んで保存。
5. `ig-push` の **Settings → Trigger Events → Add → Cron Triggers** に `* * * * *`（毎分）を追加。
6. ブラウザで https://ig-push.pokugou.workers.dev/vapid を開き、`{"publicKey":"…"}` と出れば完了。
