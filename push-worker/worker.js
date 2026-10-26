// IG TODO 通知サーバー（Cloudflare Worker）
// iPhone などの「ホーム画面アプリ」へ、TODO の期限になったら Web Push で通知を送る。
// 保存先は KV（バインディング名 PUSH_KV）。1分ごとの Cron で期限を確認する。
// 暗号鍵（VAPID）は初回に自動で作って KV に保存するので、秘密の値を自分で設定する必要はない。

const enc = new TextEncoder();
const SITE = 'https://pokugou.github.io/ig-website/';
const CORS = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
  'Access-Control-Allow-Headers': 'Content-Type',
};

export function b64uEnc(buf) {
  var s = '', b = new Uint8Array(buf);
  for (var i = 0; i < b.length; i++) s += String.fromCharCode(b[i]);
  return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}
export function b64uDec(s) {
  s = s.replace(/-/g, '+').replace(/_/g, '/');
  while (s.length % 4) s += '=';
  var bin = atob(s), out = new Uint8Array(bin.length);
  for (var i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
  return out;
}
function concat() {
  var n = 0, i;
  for (i = 0; i < arguments.length; i++) n += arguments[i].length;
  var out = new Uint8Array(n), o = 0;
  for (i = 0; i < arguments.length; i++) { out.set(arguments[i], o); o += arguments[i].length; }
  return out;
}
async function hkdf(salt, ikm, info, len) {
  var key = await crypto.subtle.importKey('raw', ikm, 'HKDF', false, ['deriveBits']);
  return new Uint8Array(await crypto.subtle.deriveBits({ name: 'HKDF', hash: 'SHA-256', salt: salt, info: info }, key, len * 8));
}

// RFC 8291 / RFC 8188（aes128gcm）で通知の中身を暗号化する
export async function encryptPayload(sub, payloadBytes) {
  var uaPub = b64uDec(sub.keys.p256dh), auth = b64uDec(sub.keys.auth);
  var eph = await crypto.subtle.generateKey({ name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveBits']);
  var asPub = new Uint8Array(await crypto.subtle.exportKey('raw', eph.publicKey));
  var uaKey = await crypto.subtle.importKey('raw', uaPub, { name: 'ECDH', namedCurve: 'P-256' }, false, []);
  var ecdh = new Uint8Array(await crypto.subtle.deriveBits({ name: 'ECDH', public: uaKey }, eph.privateKey, 256));
  var ikm = await hkdf(auth, ecdh, concat(enc.encode('WebPush: info\0'), uaPub, asPub), 32);
  var salt = crypto.getRandomValues(new Uint8Array(16));
  var cek = await hkdf(salt, ikm, enc.encode('Content-Encoding: aes128gcm\0'), 16);
  var nonce = await hkdf(salt, ikm, enc.encode('Content-Encoding: nonce\0'), 12);
  var aes = await crypto.subtle.importKey('raw', cek, 'AES-GCM', false, ['encrypt']);
  var ct = new Uint8Array(await crypto.subtle.encrypt({ name: 'AES-GCM', iv: nonce }, aes, concat(payloadBytes, new Uint8Array([2]))));
  return concat(salt, new Uint8Array([0, 0, 0x10, 0]), new Uint8Array([65]), asPub, ct);
}

export async function getVapid(env) {
  var v = await env.PUSH_KV.get('vapid', 'json');
  if (v && v.publicKey && v.privateJwk) return v;
  var kp = await crypto.subtle.generateKey({ name: 'ECDSA', namedCurve: 'P-256' }, true, ['sign', 'verify']);
  v = {
    publicKey: b64uEnc(await crypto.subtle.exportKey('raw', kp.publicKey)),
    privateJwk: await crypto.subtle.exportKey('jwk', kp.privateKey),
  };
  await env.PUSH_KV.put('vapid', JSON.stringify(v));
  return v;
}

export async function vapidHeader(env, endpoint) {
  var v = await getVapid(env);
  var key = await crypto.subtle.importKey('jwk', v.privateJwk, { name: 'ECDSA', namedCurve: 'P-256' }, false, ['sign']);
  var head = b64uEnc(enc.encode(JSON.stringify({ typ: 'JWT', alg: 'ES256' })));
  var body = b64uEnc(enc.encode(JSON.stringify({ aud: new URL(endpoint).origin, exp: Math.floor(Date.now() / 1000) + 12 * 3600, sub: SITE })));
  var sig = await crypto.subtle.sign({ name: 'ECDSA', hash: 'SHA-256' }, key, enc.encode(head + '.' + body));
  return 'vapid t=' + head + '.' + body + '.' + b64uEnc(sig) + ', k=' + v.publicKey;
}

export async function sendPush(env, sub, payload) {
  var body = await encryptPayload(sub, enc.encode(JSON.stringify(payload)));
  return fetch(sub.endpoint, {
    method: 'POST',
    headers: {
      'Content-Encoding': 'aes128gcm',
      'Content-Type': 'application/octet-stream',
      'TTL': '86400',
      'Urgency': 'high',
      'Authorization': await vapidHeader(env, sub.endpoint),
    },
    body: body,
  });
}

async function idOf(endpoint) {
  var h = await crypto.subtle.digest('SHA-256', enc.encode(endpoint));
  return Array.prototype.map.call(new Uint8Array(h), function (b) { return b.toString(16).padStart(2, '0'); }).join('').slice(0, 32);
}
// 「2026-10-26」＋「14:30」（時刻なしは 09:00）を日本時間として扱い、ミリ秒にする
export function fireAtOf(due, time) {
  var p = String(due || '').split('-'), t = String(time || '09:00').split(':');
  if (p.length !== 3) return 0;
  return Date.UTC(+p[0], +p[1] - 1, +p[2], (+t[0] || 0) - 9, +t[1] || 0);
}
export function payloadOf(it) {
  return {
    title: (it.star ? '★ ' : '') + (it.text || 'TODO'),
    body: (it.time ? it.time + ' ' : '') + '期限です' + (it.note ? '\n' + it.note : ''),
    url: 'todo.html',
    tag: it.id,
  };
}

async function getIndex(env) { return (await env.PUSH_KV.get('index', 'json')) || []; }
async function addIndex(env, id) { var ix = await getIndex(env); if (ix.indexOf(id) < 0) { ix.push(id); await env.PUSH_KV.put('index', JSON.stringify(ix)); } }
async function dropIndex(env, id) {
  var ix = (await getIndex(env)).filter(function (x) { return x !== id; });
  await env.PUSH_KV.put('index', JSON.stringify(ix));
  await env.PUSH_KV.delete('sub:' + id);
}

function json(obj, status) {
  return new Response(JSON.stringify(obj), { status: status || 200, headers: Object.assign({ 'Content-Type': 'application/json' }, CORS) });
}

async function handle(request, env) {
  var url = new URL(request.url), path = url.pathname.replace(/\/+$/, '');
  if (request.method === 'OPTIONS') return new Response(null, { status: 204, headers: CORS });
  if (request.method === 'GET' && path === '/vapid') return json({ publicKey: (await getVapid(env)).publicKey });
  if (request.method === 'GET' && (path === '' || path === '/')) return json({ ok: true, name: 'ig-push' });
  if (request.method !== 'POST') return json({ error: 'not found' }, 404);
  var data;
  try { data = await request.json(); } catch (e) { return json({ error: 'bad json' }, 400); }

  if (path === '/subscribe') {
    var sub = data.subscription;
    if (!sub || !sub.endpoint || !sub.keys || !sub.keys.p256dh || !sub.keys.auth) return json({ error: 'bad subscription' }, 400);
    var id = await idOf(sub.endpoint), prev = await env.PUSH_KV.get('sub:' + id, 'json');
    await env.PUSH_KV.put('sub:' + id, JSON.stringify({ subscription: sub, items: prev ? prev.items : [], fired: prev ? prev.fired : {}, updatedAt: Date.now() }));
    await addIndex(env, id);
    return json({ ok: true });
  }
  if (!data.endpoint) return json({ error: 'endpoint required' }, 400);
  var sid = await idOf(data.endpoint), rec = await env.PUSH_KV.get('sub:' + sid, 'json');
  if (!rec) return json({ error: 'not subscribed' }, 404);

  if (path === '/unsubscribe') { await dropIndex(env, sid); return json({ ok: true }); }
  if (path === '/test') {
    var r = await sendPush(env, rec.subscription, { title: '通知テスト', body: 'このように、TODOの期限に通知が届きます。', url: 'todo.html', tag: 'test' });
    return json({ ok: r.ok, status: r.status }, r.ok ? 200 : 502);
  }
  if (path === '/sync') {
    var items = (data.items || []).map(function (it) {
      return { id: String(it.id), text: String(it.text || '').slice(0, 200), note: String(it.note || '').slice(0, 300), star: !!it.star, time: it.time || '', fireAt: fireAtOf(it.due, it.time) };
    }).filter(function (it) { return it.fireAt > 0; }).slice(0, 500);
    var fired = {};
    items.forEach(function (it) { var k = it.id + '|' + it.fireAt; if (rec.fired && rec.fired[k]) fired[k] = rec.fired[k]; });
    rec.items = items; rec.fired = fired; rec.updatedAt = Date.now();
    await env.PUSH_KV.put('sub:' + sid, JSON.stringify(rec));
    return json({ ok: true, count: items.length });
  }
  return json({ error: 'not found' }, 404);
}

// 期限が来た TODO を通知する（Cron から 1 分ごとに呼ばれる）
export async function runDue(env, now) {
  var sent = 0, ix = await getIndex(env);
  for (var n = 0; n < ix.length; n++) {
    var id = ix[n], rec = await env.PUSH_KV.get('sub:' + id, 'json');
    if (!rec) continue;
    var dirty = false, gone = false;
    for (var i = 0; i < rec.items.length && !gone; i++) {
      var it = rec.items[i], key = it.id + '|' + it.fireAt;
      if (it.fireAt > now || it.fireAt < now - 36 * 3600 * 1000 || rec.fired[key]) continue;
      var r = await sendPush(env, rec.subscription, payloadOf(it));
      if (r.status === 404 || r.status === 410) { gone = true; break; }
      if (r.ok || (r.status >= 400 && r.status < 500 && r.status !== 429)) { rec.fired[key] = now; dirty = true; if (r.ok) sent++; }
    }
    if (gone) await dropIndex(env, id);
    else if (dirty) await env.PUSH_KV.put('sub:' + id, JSON.stringify(rec));
  }
  return sent;
}

export default {
  fetch: function (request, env) { return handle(request, env).catch(function (e) { return json({ error: String(e && e.message || e) }, 500); }); },
  scheduled: function (event, env, ctx) { ctx.waitUntil(runDue(env, Date.now())); },
};
