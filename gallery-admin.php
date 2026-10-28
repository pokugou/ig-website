<?php
session_start();

// ===== 設定 =====
define('ADMIN_PASSWORD', 'igrod2024');
define('GALLERY_HTML',   __DIR__ . '/gallery.html');
define('GALLERY_DATA',   __DIR__ . '/gallery-data.json');
define('UPLOAD_DIR',     __DIR__ . '/');
$TAGS = ['カラーカスタム', '釣果', 'ロッド', 'その他'];
// ================

function loadData() {
    if (!file_exists(GALLERY_DATA)) return [];
    return json_decode(file_get_contents(GALLERY_DATA), true) ?: [];
}
function saveData($data) {
    file_put_contents(GALLERY_DATA, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function regenerateGallery($data) {
    $html = file_get_contents(GALLERY_HTML);

    // フォトギャラリー
    $galleryGrid = '';
    foreach ($data as $item) {
        if (($item['section'] ?? 'gallery') !== 'gallery') continue;
        $cls = 'gallery-item';
        if (($item['type'] ?? '') === 'tall') $cls .= ' tall';
        if (($item['type'] ?? '') === 'wide') $cls .= ' wide';
        $f = htmlspecialchars($item['file']);
        $c = htmlspecialchars($item['caption'] ?? '');
        $t = htmlspecialchars($item['tag'] ?? '');
        $galleryGrid .= "    <div class=\"{$cls}\">\n";
        $galleryGrid .= "      <img class=\"gallery-img\" src=\"{$f}\" alt=\"{$c}\" loading=\"lazy\">\n";
        $galleryGrid .= "      <div class=\"gallery-overlay\"><div class=\"gallery-tag\">{$t}</div><div class=\"gallery-caption\">{$c}</div></div>\n";
        $galleryGrid .= "    </div>\n";
    }
    $html = preg_replace(
        '/<!-- GALLERY_START -->.*?<!-- GALLERY_END -->/s',
        "<!-- GALLERY_START -->\n{$galleryGrid}<!-- GALLERY_END -->",
        $html
    );

    // 制作実績
    $tagCatMap = ['カラーカスタム'=>'color','釣果'=>'catch','ロッド'=>'rod','その他'=>'other'];
    $buildGrid = '';
    foreach ($data as $item) {
        if (($item['section'] ?? 'gallery') !== 'build') continue;
        $cat  = $tagCatMap[$item['tag'] ?? ''] ?? 'other';
        $f    = htmlspecialchars($item['file']);
        $c    = htmlspecialchars($item['caption'] ?? '');
        $t    = htmlspecialchars($item['tag'] ?? '');
        $buildGrid .= "    <div class=\"build-card\" data-cat=\"{$cat}\">\n";
        $buildGrid .= "      <div class=\"build-card-line\"></div>\n";
        $buildGrid .= "      <div class=\"build-photo\"><img src=\"{$f}\" alt=\"{$c}\" loading=\"lazy\"></div>\n";
        $buildGrid .= "      <div class=\"build-info\">\n";
        $buildGrid .= "        <div class=\"build-model\">{$c}</div>\n";
        $buildGrid .= "        <div class=\"build-tags\"><span class=\"build-tag build-tag-genre\">{$t}</span></div>\n";
        $buildGrid .= "      </div>\n";
        $buildGrid .= "    </div>\n";
    }
    $html = preg_replace(
        '/<!-- BUILD_START -->.*?<!-- BUILD_END -->/s',
        "<!-- BUILD_START -->\n{$buildGrid}<!-- BUILD_END -->",
        $html
    );

    file_put_contents(GALLERY_HTML, $html);
}

// ログアウト
if (isset($_GET['logout'])) { session_destroy(); header('Location: gallery-admin.php'); exit; }

// ログイン
$loginError = '';
if (!empty($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) { $_SESSION['ga'] = true; }
    else { $loginError = 'パスワードが違います'; }
}
$isAdmin = !empty($_SESSION['ga']);

$msg = '';

// アップロード
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['image']['name'])) {
    $f = $_FILES['image'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp','heic','heif'];
    if ($f['error'] === 0 && in_array($ext, $allowed)) {
        $data = loadData();
        // ファイル番号を決める
        $existing = glob(UPLOAD_DIR . 'gallery-photo-*.{jpg,jpeg,png,webp,heic,heif}', GLOB_BRACE);
        $nums = [];
        foreach ($existing as $e) { if (preg_match('/gallery-photo-(\d+)\./', $e, $m)) $nums[] = (int)$m[1]; }
        $next = $nums ? max($nums) + 1 : 1;
        $ext = ($ext === 'heic' || $ext === 'heif') ? 'jpg' : $ext;
        $filename = sprintf('gallery-photo-%02d.%s', $next, $ext);
        if (move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $filename)) {
            $data[] = [
                'file'    => $filename,
                'caption' => trim($_POST['caption'] ?? ''),
                'tag'     => $_POST['tag'] ?? 'カラーカスタム',
                'type'    => $_POST['type'] ?? 'normal',
                'section' => $_POST['section'] === 'build' ? 'build' : 'gallery',
            ];
            saveData($data);
            regenerateGallery($data);
            $msg = 'アップロードしました！';
        } else { $msg = 'アップロード失敗。サーバーのパーミッションを確認してください。'; }
    } else { $msg = '対応形式: JPG / PNG / WebP / HEIC'; }
}

// 削除
if ($isAdmin && isset($_POST['delete_idx'])) {
    $idx = (int)$_POST['delete_idx'];
    $data = loadData();
    if (isset($data[$idx])) {
        array_splice($data, $idx, 1);
        saveData($data);
        regenerateGallery($data);
        $msg = '削除しました';
    }
}

// まとめ削除
if ($isAdmin && isset($_POST['bulk_delete']) && !empty($_POST['sel'])) {
    $data = loadData();
    $indices = array_map('intval', $_POST['sel']);
    rsort($indices);
    foreach ($indices as $idx) {
        if (isset($data[$idx])) array_splice($data, $idx, 1);
    }
    saveData($data);
    regenerateGallery($data);
    $msg = count($indices) . '枚削除しました';
}

// 順番入れ替え（上へ）
if ($isAdmin && isset($_POST['move_up'])) {
    $idx = (int)$_POST['move_up'];
    $data = loadData();
    if ($idx > 0 && isset($data[$idx])) {
        [$data[$idx-1], $data[$idx]] = [$data[$idx], $data[$idx-1]];
        saveData($data);
        regenerateGallery($data);
        $msg = '並び替えました';
    }
}

$data = loadData();
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ギャラリー管理</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Hiragino Sans',sans-serif;background:#111;color:#eee;min-height:100vh;padding:env(safe-area-inset-top,0) env(safe-area-inset-right,0) env(safe-area-inset-bottom,0) env(safe-area-inset-left,0)}
.wrap{max-width:640px;margin:0 auto;padding:1.5rem 1rem 4rem}
h1{font-size:1.15rem;font-weight:900;letter-spacing:0.04em;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.5rem}
h1 span{font-size:1.4rem}
.card{background:#1c1c1c;border:1px solid #333;border-radius:10px;padding:1.2rem;margin-bottom:1.2rem}
label{display:block;font-size:0.72rem;color:#aaa;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:0.35rem;margin-top:0.9rem}
label:first-child{margin-top:0}
input[type=text],select{width:100%;background:#111;border:1px solid #444;color:#eee;padding:0.6rem 0.75rem;border-radius:6px;font-size:0.9rem;font-family:inherit}
input[type=file]{display:none}
.file-btn{display:block;width:100%;padding:0.9rem;background:#222;border:2px dashed #555;border-radius:8px;text-align:center;cursor:pointer;font-size:0.9rem;color:#aaa;transition:border-color 0.2s}
.file-btn.selected{border-color:#e33;color:#eee}
.preview{width:100%;max-height:220px;object-fit:cover;border-radius:6px;margin-top:0.7rem;display:none}
.btn{display:block;width:100%;padding:0.85rem;background:#c00;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:900;letter-spacing:0.04em;cursor:pointer;margin-top:1rem;font-family:inherit}
.btn:active{background:#a00}
.btn-outline{background:transparent;border:1px solid #555;color:#aaa;margin-top:0.6rem}
.msg{background:#1a3a1a;border:1px solid #2a6a2a;color:#5f5;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:0.88rem}
.msg.err{background:#3a1a1a;border-color:#6a2a2a;color:#f88}
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1.5rem}
.login-box{background:#1c1c1c;border:1px solid #333;border-radius:12px;padding:2rem 1.5rem;width:100%;max-width:340px}
.login-box h2{font-size:1.1rem;font-weight:900;margin-bottom:1.2rem;text-align:center}
.login-box input[type=password]{width:100%;background:#111;border:1px solid #444;color:#eee;padding:0.7rem 0.9rem;border-radius:8px;font-size:1rem;margin-bottom:0.8rem}
/* gallery list */
.glist{display:flex;flex-direction:column;gap:0.7rem}
.gitem{display:flex;align-items:center;gap:0.75rem;background:#1c1c1c;border:1px solid #333;border-radius:8px;padding:0.6rem 0.75rem}
.gitem img{width:64px;height:48px;object-fit:cover;border-radius:4px;flex-shrink:0}
.gitem-info{flex:1;min-width:0}
.gitem-caption{font-size:0.88rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.gitem-tag{font-size:0.68rem;color:#888;margin-top:0.1rem}
.gitem-actions{display:flex;flex-direction:column;gap:0.3rem;flex-shrink:0}
.icon-btn{background:#222;border:1px solid #444;color:#ccc;padding:0.3rem 0.5rem;border-radius:5px;cursor:pointer;font-size:0.75rem;font-family:inherit}
.icon-btn.del{border-color:#6a2a2a;color:#f88}
.icon-btn:active{background:#333}
.bulk-bar{display:flex;align-items:center;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid #2a2a2a;margin-bottom:0.3rem}
.cb-all-wrap{display:flex;align-items:center;gap:0.4rem;font-size:0.78rem;color:#aaa;cursor:pointer}
.bulk-del-btn{padding:0.35rem 0.75rem;font-size:0.78rem}
.gitem-cb{width:18px;height:18px;accent-color:#c00;flex-shrink:0;cursor:pointer}
.gitem:has(.gitem-cb:checked){background:#2a0a0a;border-color:#6a2a2a}
.empty{text-align:center;color:#555;padding:2.5rem 0;font-size:0.9rem}
.logout{font-size:0.72rem;color:#666;text-decoration:none;float:right;margin-top:0.2rem}
.after-upload-btns{display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;margin-bottom:1.2rem}
.au-btn{display:block;padding:0.9rem 0.5rem;border-radius:8px;text-align:center;text-decoration:none;font-size:0.88rem;font-weight:900;letter-spacing:0.03em}
.au-btn-new{background:#c00;color:#fff}
.au-btn-list{background:#222;border:1px solid #444;color:#eee}
.type-row{display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem}
.type-row.two-col{grid-template-columns:repeat(2,1fr)}
.type-opt{position:relative}
.type-opt input{position:absolute;opacity:0;width:0;height:0}
.type-opt label{display:block;text-align:center;padding:0.5rem 0.3rem;background:#111;border:1px solid #444;border-radius:6px;cursor:pointer;font-size:0.78rem;color:#aaa;margin:0}
.type-opt input:checked + label{border-color:#e33;color:#fff;background:#2a0a0a}
</style>
</head>
<body>
<?php if (!$isAdmin): ?>
<div class="login-wrap">
  <div class="login-box">
    <h2>🔒 ギャラリー管理</h2>
    <?php if ($loginError): ?><div class="msg err"><?= htmlspecialchars($loginError) ?></div><?php endif ?>
    <form method="post">
      <input type="password" name="password" placeholder="パスワード" autofocus autocomplete="current-password">
      <button class="btn" type="submit">ログイン</button>
    </form>
  </div>
</div>
<?php else: ?>
<div class="wrap">
  <h1><span>🖼️</span> ギャラリー管理 <a class="logout" href="?logout=1">ログアウト</a></h1>

  <?php if ($msg): ?>
  <?php $isErr = (strpos($msg,'失敗') !== false) || (strpos($msg,'対応') !== false); ?>
  <div class="msg<?= $isErr ? ' err' : '' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php if (!$isErr): ?>
  <div class="after-upload-btns">
    <a href="#uploadForm" class="au-btn au-btn-new">📷 新規アップロード</a>
    <a href="#photoList" class="au-btn au-btn-list">📋 掲載中の写真</a>
  </div>
  <?php endif ?>
  <?php endif ?>

  <!-- アップロードフォーム -->
  <div class="card" id="uploadForm">
    <form method="post" enctype="multipart/form-data">
      <label for="fileInput">写真を選ぶ</label>
      <label class="file-btn" id="fileLabel" for="fileInput">📷 タップして写真を選択</label>
      <input type="file" id="fileInput" name="image" accept="image/*" onchange="onFileChange(this)">
      <img class="preview" id="preview">

      <label>キャプション（説明文）</label>
      <input type="text" name="caption" placeholder="例：レッド × ゴールドスレッド">

      <label>タグ</label>
      <select name="tag">
        <?php foreach ($TAGS as $t): ?>
        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
        <?php endforeach ?>
      </select>

      <label>掲載セクション</label>
      <div class="type-row two-col">
        <div class="type-opt"><input type="radio" name="section" value="gallery" id="s1" checked><label for="s1">📸 釣果写真</label></div>
        <div class="type-opt"><input type="radio" name="section" value="build"   id="s2"><label for="s2">🏆 制作実績</label></div>
      </div>

      <label>表示サイズ <span style="font-size:0.65rem;color:#666">（制作実績では無効）</span></label>
      <div class="type-row">
        <div class="type-opt"><input type="radio" name="type" value="normal" id="t1" checked><label for="t1">普通</label></div>
        <div class="type-opt"><input type="radio" name="type" value="tall"   id="t2"><label for="t2">縦長</label></div>
        <div class="type-opt"><input type="radio" name="type" value="wide"   id="t3"><label for="t3">横長</label></div>
      </div>

      <button class="btn" type="submit">アップロード</button>
    </form>
  </div>

  <!-- 現在の画像一覧 -->
  <div class="card" id="photoList">
    <label style="margin-top:0">掲載中の写真（<?= count($data) ?>枚）</label>
    <?php if (empty($data)): ?>
    <div class="empty">まだ写真がありません</div>
    <?php else: ?>
    <form method="post" id="bulkForm">
      <div class="bulk-bar">
        <label class="cb-all-wrap"><input type="checkbox" id="cbAll" onchange="toggleAll(this)"> すべて選択</label>
        <button type="submit" name="bulk_delete" value="1" class="icon-btn del bulk-del-btn" onclick="return confirmBulk()">まとめて削除</button>
      </div>
      <div class="glist" style="margin-top:0.5rem">
        <?php foreach ($data as $i => $item): ?>
        <div class="gitem">
          <input type="checkbox" name="sel[]" value="<?= $i ?>" class="gitem-cb">
          <img src="<?= htmlspecialchars($item['file']) ?>" alt="">
          <div class="gitem-info">
            <div class="gitem-caption"><?= htmlspecialchars($item['caption'] ?: '（キャプションなし）') ?></div>
            <div class="gitem-tag">
              <?php $sec = ($item['section'] ?? 'gallery') === 'build' ? '🏆制作実績' : '📸釣果写真'; ?>
              <span style="color:<?= ($item['section'] ?? '') === 'build' ? '#fa0' : '#8cf' ?>"><?= $sec ?></span>
              · <?= htmlspecialchars($item['tag'] ?? '') ?>
            </div>
          </div>
          <div class="gitem-actions">
            <?php if ($i > 0): ?>
            <button type="button" class="icon-btn" onclick="moveUp(<?= $i ?>)">↑</button>
            <?php endif ?>
          </div>
        </div>
        <?php endforeach ?>
      </div>
    </form>
    <!-- 順番入れ替え用フォーム（hidden） -->
    <form method="post" id="moveForm" style="display:none">
      <input type="hidden" name="move_up" id="moveIdx">
    </form>
    <?php endif ?>
  </div>
</div>
<script>
function toggleAll(cb) {
  document.querySelectorAll('.gitem-cb').forEach(function(c){ c.checked = cb.checked; });
}
function confirmBulk() {
  var checked = document.querySelectorAll('.gitem-cb:checked').length;
  if (checked === 0) { alert('写真を選択してください'); return false; }
  return confirm(checked + '枚削除しますか？');
}
function moveUp(idx) {
  document.getElementById('moveIdx').value = idx;
  document.getElementById('moveForm').submit();
}
function onFileChange(input) {
  const label = document.getElementById('fileLabel');
  const preview = document.getElementById('preview');
  if (input.files && input.files[0]) {
    label.textContent = input.files[0].name;
    label.classList.add('selected');
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
<?php endif ?>
</body>
</html>
