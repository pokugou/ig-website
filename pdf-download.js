// Shows a "download PDF" bar after a PDF is generated. Mobile browsers block downloads started
// after async work, but a tap on a real link is always allowed.
(function () {
  function isDesktop() {
    var ua = navigator.userAgent || '';
    if (/iPhone|iPad|iPod|Android/i.test(ua)) return false;
    if (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1) return false; // iPad（パソコン表示）
    return true;
  }
  // スマホでは、PDFを作る前に新しい画面を開かない（iPhoneは裏に回った画面の処理を止めてしまい、作成が進まなくなるため）。
  // 今の画面のままPDFを作り、できたら画面中央に「開いて確認」「この名前で保存」を出す。
  window.pdfMobile = !isDesktop();

  // 作成中の表示（スマホ用。text を空にすると消える）
  window.pdfBusy = function (text) {
    var el = document.getElementById('pdf-busy');
    if (!text) { if (el) el.remove(); return; }
    if (!el) {
      el = document.createElement('div'); el.id = 'pdf-busy';
      el.style.cssText = 'position:fixed;inset:0;z-index:99998;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:24px';
      el.innerHTML = '<div style="background:#fff;color:#111;font:700 16px/1.6 sans-serif;padding:22px 26px;max-width:320px;text-align:center"></div>';
      document.body.appendChild(el);
    }
    el.firstChild.textContent = text;
  };

  function showPdfSheet(url, name) {
    var old = document.getElementById('pdf-dl-bar');
    if (old) old.remove();
    var wrap = document.createElement('div');
    wrap.id = 'pdf-dl-bar';
    wrap.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;padding:20px';
    var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
    wrap.innerHTML = '<div style="background:#fff;color:#111;width:100%;max-width:360px;padding:20px 18px 16px;font-family:sans-serif;text-align:center">' +
      '<div style="font-weight:900;font-size:18px;margin-bottom:6px">PDFができました</div>' +
      '<div style="font-size:13px;color:#555;word-break:break-all;margin-bottom:16px">' + esc(name) + '</div>' +
      '<a id="pdf-sheet-open" href="' + url + '" target="_blank" style="display:block;border:2px solid #111;color:#111;font-weight:700;font-size:16px;padding:12px;text-decoration:none;margin-bottom:10px">PDFを開いて確認</a>' +
      '<a id="pdf-sheet-save" href="' + url + '" download="' + esc(name) + '" style="display:block;background:#cc2200;color:#fff;font-weight:700;font-size:16px;padding:13px;text-decoration:none;margin-bottom:12px">⬇ この名前で保存</a>' +
      '<div style="font-size:11px;color:#777;line-height:1.6;margin-bottom:10px">確認した画面の共有・保存を使うと、名前が英数字に変わります。<br>保存はこの赤いボタンから。</div>' +
      '<button type="button" style="font:700 14px sans-serif;background:none;border:1px solid #ccc;padding:8px 22px;color:#555">閉じる</button></div>';
    wrap.querySelector('button').addEventListener('click', function () { wrap.remove(); });
    document.body.appendChild(wrap);
  }

  window.showPdfDownload = function (pdf, name) {
    try {
      var url = URL.createObjectURL(pdf.output('blob'));
      if (window.pdfBusy) window.pdfBusy('');
      if (window.pdfMobile) { showPdfSheet(url, name); return; }
      var old = document.getElementById('pdf-dl-bar');
      if (old) old.remove();
      var style = document.getElementById('pdf-dl-style');
      if (!style) {
        style = document.createElement('style');
        style.id = 'pdf-dl-style';
        style.textContent = '@media print{#pdf-dl-bar{display:none!important}}';
        document.head.appendChild(style);
      }
      var bar = document.createElement('div');
      bar.id = 'pdf-dl-bar';
      bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:99999;display:flex;gap:8px;align-items:center;justify-content:center;flex-wrap:wrap;padding:10px 12px;background:#111;color:#fff;font:700 14px "Noto Sans JP",sans-serif;box-shadow:0 -2px 8px rgba(0,0,0,.25)';
      var dl = document.createElement('a');
      dl.href = url; dl.download = name; dl.textContent = '📥 PDFをダウンロード保存';
      dl.style.cssText = 'color:#fff;background:#cc2200;padding:10px 16px;text-decoration:none;border-radius:2px';
      var open = document.createElement('a');
      open.href = url; open.target = '_blank'; open.textContent = '開く';
      open.style.cssText = 'color:#fff;border:1px solid #fff;padding:9px 14px;text-decoration:none;border-radius:2px';
      var close = document.createElement('button');
      close.type = 'button'; close.textContent = '×';
      close.style.cssText = 'color:#fff;background:none;border:none;font-size:20px;cursor:pointer;padding:4px 8px';
      close.addEventListener('click', function () { bar.remove(); });
      bar.appendChild(dl); bar.appendChild(open); bar.appendChild(close);
      document.body.appendChild(bar);
    } catch (e) { console.error(e); }
  };

  // 先に開いておいたタブ（win）にPDFを表示し、上に「この名前で保存」ボタンを付ける（自動では保存しない）。
  // ブラウザのPDF画面から保存すると「dec9f77e-….pdf」のような名前になってしまうため。
  window.openPdfIn = function (win, pdf, name) {
    var url, blob = null;
    try { blob = pdf.output('blob'); url = URL.createObjectURL(blob); } catch (e) { url = pdf.output('bloburl'); }
    try {
      try { pdf.setProperties({ title: String(name || '').replace(/\.pdf$/, '') }); } catch (e0) {}
      var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
      win.document.open();
      win.document.write('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' + esc(name) + '</title>' +
        '<style>html,body{margin:0;height:100%;font-family:sans-serif;background:#555}.bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 14px;background:#111;color:#fff}' +
        '.bar a{font-weight:700;text-decoration:none;padding:9px 16px;font-size:15px}.bar a.dl{background:#cc2200;color:#fff}.bar a.op{border:1px solid #fff;color:#fff}.bar span{font-size:13px;opacity:.85;word-break:break-all}' +
        'iframe{display:block;width:100%;height:calc(100% - 60px);border:0;background:#fff}</style>' +
        '<div class="bar"><a class="dl" id="dl" href="' + url + '" download="' + esc(name) + '">⬇ この名前で保存</a><span>' + esc(name) + '<br>※下のPDF画面の保存ボタンを使うと、名前が英数字に変わります</span></div>' +
        '<iframe src="' + url + '#view=FitH" title="PDF"></iframe>');
      win.document.close();
      // PDFを見て確認してから「この名前で保存」。パソコンの Chrome などでは、名前入りの保存画面（保存先も選べる）を出す
      var dl = win.document.getElementById('dl');
      if (dl && blob && isDesktop() && typeof win.showSaveFilePicker === 'function') {
        dl.addEventListener('click', async function (e) {
          e.preventDefault();
          try {
            var handle = await win.showSaveFilePicker({ suggestedName: name, types: [{ description: 'PDF', accept: { 'application/pdf': ['.pdf'] } }] });
            var w = await handle.createWritable();
            await w.write(blob); await w.close();
            dl.textContent = '✓ 保存しました';
          } catch (err) {
            if (err && err.name === 'AbortError') return;   // 保存画面でキャンセルした
            console.error(err);
            var a = win.document.createElement('a'); a.href = url; a.download = name; win.document.body.appendChild(a); a.click(); a.remove();
          }
        });
      }
    } catch (e) {
      win.location.href = url;
    }
  };

  // 「保存のみ」ボタン（PDFは作らず、一覧にだけ保存する）。fn は成功で true を返す関数
  window.wireSaveOnly = function (btn, fn) {
    if (!btn) return;
    btn.addEventListener('click', async function () {
      var label = btn.textContent;
      btn.disabled = true; btn.textContent = '保存中…';
      var ok = false;
      try { ok = await fn(); } catch (e) { console.error(e); }
      btn.textContent = ok ? '保存しました ✓' : label;
      setTimeout(function () { btn.textContent = label; btn.disabled = false; }, ok ? 1800 : 0);
    });
  };
})();
