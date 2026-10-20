// Shows a "download PDF" bar after a PDF is generated. Mobile browsers block downloads started
// after async work, but a tap on a real link is always allowed.
(function () {
  window.showPdfDownload = function (pdf, name) {
    try {
      var url = URL.createObjectURL(pdf.output('blob'));
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
