<?php
session_start();

// ===== 設定 =====
define('ADMIN_PASSWORD', 'jokers333');
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
function itemFiles($item) {
    return !empty($item['files']) ? $item['files'] : [$item['file']];
}
function photosHtml($files, $alt, $imgClass) {
    $cls  = $imgClass ? " class=\"{$imgClass}\"" : '';
    $imgs = '';
    foreach ($files as $f) {
        $imgs .= "<img{$cls} src=\"" . htmlspecialchars($f) . "\" alt=\"{$alt}\" loading=\"lazy\">";
    }
    if (count($files) < 2) return "      {$imgs}\n";
    $dots = str_repeat('<span></span>', count($files));
    return "      <div class=\"ph-slider\">{$imgs}</div>\n"
         . "      <button type=\"button\" class=\"ph-nav ph-prev\" aria-label=\"前の写真\">&#8249;</button><button type=\"button\" class=\"ph-nav ph-next\" aria-label=\"次の写真\">&#8250;</button>\n"
         . "      <div class=\"ph-count\">1 / " . count($files) . "</div><div class=\"ph-dots\">{$dots}</div>\n";
}
function saveUploads($field) {
    $saved = []; $err = '';
    if (empty($_FILES[$field]['name'])) return [$saved, $err];
    $names = (array)$_FILES[$field]['name'];
    $errs  = (array)$_FILES[$field]['error'];
    $tmps  = (array)$_FILES[$field]['tmp_name'];
    $existing = glob(UPLOAD_DIR . 'gallery-photo-*.{jpg,jpeg,png,webp,heic,heif}', GLOB_BRACE) ?: [];
    $nums = [];
    foreach ($existing as $e) { if (preg_match('/gallery-photo-(\d+)\./', $e, $m)) $nums[] = (int)$m[1]; }
    $next = $nums ? max($nums) + 1 : 1;
    $allowed = ['jpg','jpeg','png','webp','heic','heif'];
    foreach ($names as $k => $name) {
        if ($name === '') continue;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($errs[$k] !== 0 || !in_array($ext, $allowed)) { $err = '一部の写真のアップロードに失敗しました（対応形式: JPG / PNG / WebP / HEIC）'; continue; }
        $ext = ($ext === 'heic' || $ext === 'heif') ? 'jpg' : $ext;
        $filename = sprintf('gallery-photo-%02d.%s', $next, $ext);
        if (move_uploaded_file($tmps[$k], UPLOAD_DIR . $filename)) { $saved[] = $filename; $next++; }
        else { $err = 'アップロード失敗。サーバーのパーミッションを確認してください。'; }
    }
    return [$saved, $err];
}
function regenerateGallery($data) {
    $html = file_get_contents(GALLERY_HTML);

    // フォトギャラリー
    $galleryGrid = '';
    foreach ($data as $item) {
        if (($item['section'] ?? 'gallery') !== 'gallery') continue;
        $files = itemFiles($item);
        $cls = 'gallery-item';
        if (($item['type'] ?? '') === 'tall') $cls .= ' tall';
        if (($item['type'] ?? '') === 'wide') $cls .= ' wide';
        if (count($files) > 1) $cls .= ' ph-multi';
        $ca = htmlspecialchars($item['caption'] ?? '');
        $c  = nl2br($ca);
        $t  = htmlspecialchars($item['tag'] ?? '');
        $galleryGrid .= "    <div class=\"{$cls}\">\n";
        $galleryGrid .= photosHtml($files, $ca, 'gallery-img');
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
        $files = itemFiles($item);
        $ca   = htmlspecialchars($item['caption'] ?? '');
        $c    = nl2br($ca);
        $t    = htmlspecialchars($item['tag'] ?? '');
        $buildGrid .= "    <div class=\"build-card\" data-cat=\"{$cat}\">\n";
        $buildGrid .= "      <div class=\"build-card-line\"></div>\n";
        $buildGrid .= "      <div class=\"build-photo" . (count($files) > 1 ? ' ph-multi' : '') . "\">\n";
        $buildGrid .= photosHtml($files, $ca, '');
        $buildGrid .= "      </div>\n";
        $cu   = rawurlencode($item['caption'] ?? '');
        $buildGrid .= "      <div class=\"build-info\">\n";
        $buildGrid .= "        <div class=\"build-model\">{$c}</div>\n";
        $buildGrid .= "        <div class=\"build-tags\"><span class=\"build-tag build-tag-genre\">{$t}</span></div>\n";
        $buildGrid .= "        <a href=\"order-sheet.html?color={$cu}\" class=\"build-order-btn\" onclick=\"event.stopPropagation()\">このカラーでオーダーする</a>\n";
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

// 送信サイズがサーバー上限を超えると $_POST も $_FILES も空になる
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $msg = 'アップロード失敗：写真の容量が大きすぎます。枚数を減らして何回かに分けてください。';
}

// アップロード（1ブロックに複数枚）
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['images']['name'][0])) {
    [$saved, $err] = saveUploads('images');
    if ($saved) {
        $data = loadData();
        $data[] = [
            'file'    => $saved[0],
            'files'   => $saved,
            'caption' => trim($_POST['caption'] ?? ''),
            'tag'     => $_POST['tag'] ?? 'カラーカスタム',
            'type'    => $_POST['type'] ?? 'normal',
            'section' => ($_POST['section'] ?? '') === 'build' ? 'build' : 'gallery',
        ];
        saveData($data);
        regenerateGallery($data);
        $msg = $err ?: 'アップロードしました！（' . count($saved) . '枚）';
    } else { $msg = $err ?: '対応形式: JPG / PNG / WebP / HEIC'; }
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

// 編集保存
if ($isAdmin && isset($_POST['edit_save'])) {
    $idx = (int)$_POST['edit_idx'];
    $data = loadData();
    if (isset($data[$idx])) {
        $data[$idx]['caption'] = trim($_POST['edit_caption'] ?? '');
        $data[$idx]['tag']     = in_array($_POST['edit_tag'] ?? '', $TAGS) ? $_POST['edit_tag'] : 'カラーカスタム';
        $data[$idx]['section'] = ($_POST['edit_section'] ?? '') === 'build' ? 'build' : 'gallery';
        $data[$idx]['type']    = in_array($_POST['edit_type'] ?? '', ['normal','tall','wide']) ? $_POST['edit_type'] : 'normal';
        $orig = itemFiles($data[$idx]);
        $keep = json_decode($_POST['edit_files'] ?? '', true);
        if (!is_array($keep)) $keep = $orig;
        $keep = array_values(array_filter($keep, function($f) use ($orig) { return in_array($f, $orig, true); }));
        [$added, $err] = saveUploads('add_images');
        $files = array_merge($keep, $added);
        if (!$files) $files = $orig;
        $data[$idx]['files'] = $files;
        $data[$idx]['file']  = $files[0];
        saveData($data);
        regenerateGallery($data);
        $msg = $err ?: '編集を保存しました';
    }
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
input[type=text],select,textarea{width:100%;background:#111;border:1px solid #444;color:#eee;padding:0.6rem 0.75rem;border-radius:6px;font-size:0.9rem;font-family:inherit}
textarea{resize:vertical;min-height:4.5rem;line-height:1.55}
input[type=file]{display:none}
.file-btn{display:block;width:100%;padding:0.9rem;background:#222;border:2px dashed #555;border-radius:8px;text-align:center;cursor:pointer;font-size:0.9rem;color:#aaa;transition:border-color 0.2s}
.file-btn.selected{border-color:#e33;color:#eee}
.previews{display:grid;grid-template-columns:repeat(4,1fr);gap:0.4rem;margin-top:0.7rem}
.previews:empty{display:none}
.previews img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:5px}
.hint{font-size:0.72rem;color:#888;margin-top:0.4rem;line-height:1.5}
.ef-list{display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem}
.ef-item{position:relative;background:#111;border:1px solid #444;border-radius:6px;overflow:hidden}
.ef-item img{width:100%;aspect-ratio:1;object-fit:cover;display:block}
.ef-no{position:absolute;top:4px;left:4px;background:rgba(0,0,0,0.7);color:#fff;font-size:0.65rem;padding:1px 6px;border-radius:8px}
.ef-btns{display:grid;grid-template-columns:1fr 1fr 1fr}
.ef-btns button{background:#222;border:none;border-top:1px solid #444;color:#ccc;padding:0.4rem 0;font-size:0.8rem;cursor:pointer;font-family:inherit}
.ef-btns button+button{border-left:1px solid #444}
.ef-btns button.del{color:#f88}
.ef-btns button:disabled{color:#444;cursor:default}
.gitem-thumb{position:relative;flex-shrink:0}
.gitem-count{position:absolute;right:2px;bottom:2px;background:rgba(0,0,0,0.75);color:#fff;font-size:0.6rem;padding:0 4px;border-radius:6px}
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
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.82);z-index:100;align-items:flex-end}
.modal-bg.open{display:flex}
.modal-inner{background:#1c1c1c;border:1px solid #444;border-radius:14px 14px 0 0;padding:1.5rem 1.2rem calc(1.5rem + env(safe-area-inset-bottom,0px));width:100%;max-height:92vh;overflow-y:auto}
.modal-title{font-size:1rem;font-weight:900;margin-bottom:1.1rem}
.icon-btn.edit{border-color:#4466aa;color:#88aaff}
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
      <label class="file-btn" id="fileLabel" for="fileInput">📷 タップして写真を選択（複数OK）</label>
      <input type="file" id="fileInput" name="images[]" accept="image/*" multiple onchange="onFileChange(this)">
      <div class="previews" id="previews"></div>
      <p class="hint">まとめて選んだ写真は1つのブロックになり、サイト上でスライドして見られます。選んだ順に並びます（並び替えは後から✏️編集でできます）。</p>

      <label>キャプション（説明文）</label>
      <textarea name="caption" placeholder="例：レッド × ゴールドスレッド&#10;改行できます"></textarea>

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
          <div class="gitem-thumb">
            <img src="<?= htmlspecialchars($item['file']) ?>" alt="">
            <?php if (count(itemFiles($item)) > 1): ?><span class="gitem-count"><?= count(itemFiles($item)) ?>枚</span><?php endif ?>
          </div>
          <div class="gitem-info">
            <div class="gitem-caption"><?= htmlspecialchars($item['caption'] ?: '（キャプションなし）') ?></div>
            <div class="gitem-tag">
              <?php $sec = ($item['section'] ?? 'gallery') === 'build' ? '🏆制作実績' : '📸釣果写真'; ?>
              <span style="color:<?= ($item['section'] ?? '') === 'build' ? '#fa0' : '#8cf' ?>"><?= $sec ?></span>
              · <?= htmlspecialchars($item['tag'] ?? '') ?>
            </div>
          </div>
          <div class="gitem-actions">
            <button type="button" class="icon-btn edit" data-item="<?= htmlspecialchars(json_encode($item + ['files' => itemFiles($item)], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>" onclick="openEdit(<?= $i ?>, this)">✏️</button>
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

<!-- 編集モーダル -->
<div class="modal-bg" id="modalBg" onclick="if(event.target===this)closeEdit()">
  <div class="modal-inner">
    <div class="modal-title">✏️ 写真を編集</div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="edit_save" value="1">
      <input type="hidden" name="edit_idx" id="editIdx">
      <input type="hidden" name="edit_files" id="editFilesInput">
      <label>写真（左から順に表示・1枚目がサムネイル）</label>
      <div class="ef-list" id="editFiles"></div>
      <label class="file-btn" id="addLabel" for="addInput" style="margin-top:0.6rem">＋ 写真を追加</label>
      <input type="file" id="addInput" name="add_images[]" accept="image/*" multiple onchange="onAddChange(this)">
      <div class="previews" id="addPreviews"></div>
      <label>キャプション</label>
      <textarea name="edit_caption" id="editCaption" placeholder="例：レッド × ゴールドスレッド&#10;改行できます"></textarea>
      <label>タグ</label>
      <select name="edit_tag" id="editTag">
        <?php foreach ($TAGS as $t): ?>
        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
        <?php endforeach ?>
      </select>
      <label>掲載セクション</label>
      <div class="type-row two-col" style="margin-bottom:0.5rem">
        <div class="type-opt"><input type="radio" name="edit_section" value="gallery" id="es1"><label for="es1">📸 釣果写真</label></div>
        <div class="type-opt"><input type="radio" name="edit_section" value="build" id="es2"><label for="es2">🏆 制作実績</label></div>
      </div>
      <label>表示サイズ <span style="font-size:0.65rem;color:#666">（制作実績では無効）</span></label>
      <div class="type-row" style="margin-bottom:0.5rem">
        <div class="type-opt"><input type="radio" name="edit_type" value="normal" id="et1"><label for="et1">普通</label></div>
        <div class="type-opt"><input type="radio" name="edit_type" value="tall" id="et2"><label for="et2">縦長</label></div>
        <div class="type-opt"><input type="radio" name="edit_type" value="wide" id="et3"><label for="et3">横長</label></div>
      </div>
      <button class="btn" type="submit">保存する</button>
      <button class="btn btn-outline" type="button" onclick="closeEdit()">キャンセル</button>
    </form>
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
var editFiles = [];
function renderEditFiles() {
  var box = document.getElementById('editFiles');
  box.innerHTML = '';
  editFiles.forEach(function(f, i) {
    var el = document.createElement('div');
    el.className = 'ef-item';
    el.innerHTML = '<img src="' + f + '" alt=""><span class="ef-no">' + (i + 1) + '</span>' +
      '<div class="ef-btns"><button type="button" data-a="l">◀</button><button type="button" data-a="r">▶</button><button type="button" class="del" data-a="d">✕</button></div>';
    var b = el.querySelectorAll('button');
    b[0].disabled = i === 0;
    b[1].disabled = i === editFiles.length - 1;
    b[2].disabled = editFiles.length === 1;
    b[0].onclick = function() { editFiles.splice(i - 1, 0, editFiles.splice(i, 1)[0]); renderEditFiles(); };
    b[1].onclick = function() { editFiles.splice(i + 1, 0, editFiles.splice(i, 1)[0]); renderEditFiles(); };
    b[2].onclick = function() { editFiles.splice(i, 1); renderEditFiles(); };
    box.appendChild(el);
  });
  document.getElementById('editFilesInput').value = JSON.stringify(editFiles);
}
function openEdit(idx, btn) {
  var item = JSON.parse(btn.dataset.item);
  document.getElementById('editIdx').value = idx;
  document.getElementById('editCaption').value = item.caption || '';
  document.getElementById('editTag').value = item.tag || 'カラーカスタム';
  document.querySelectorAll('input[name="edit_section"]').forEach(function(r){ r.checked = r.value === (item.section || 'gallery'); });
  document.querySelectorAll('input[name="edit_type"]').forEach(function(r){ r.checked = r.value === (item.type || 'normal'); });
  editFiles = item.files.slice();
  renderEditFiles();
  var add = document.getElementById('addInput');
  add.value = '';
  document.getElementById('addPreviews').innerHTML = '';
  document.getElementById('addLabel').textContent = '＋ 写真を追加';
  document.getElementById('addLabel').classList.remove('selected');
  document.getElementById('modalBg').classList.add('open');
}
function closeEdit() {
  document.getElementById('modalBg').classList.remove('open');
}
function showPreviews(input, label, box, emptyText) {
  box.innerHTML = '';
  var n = input.files ? input.files.length : 0;
  label.textContent = n ? n + '枚選択中' : emptyText;
  label.classList.toggle('selected', n > 0);
  for (var i = 0; i < n; i++) {
    var img = document.createElement('img');
    img.src = URL.createObjectURL(input.files[i]);
    box.appendChild(img);
  }
}
function onFileChange(input) {
  showPreviews(input, document.getElementById('fileLabel'), document.getElementById('previews'), '📷 タップして写真を選択（複数OK）');
}
function onAddChange(input) {
  showPreviews(input, document.getElementById('addLabel'), document.getElementById('addPreviews'), '＋ 写真を追加');
}
</script>
<?php endif ?>
</body>
</html>
