// Shared helper for the saved-list pages: turns the checked records into one combined PDF.
// Each record is drawn by the real editor page inside a hidden frame, so the PDF matches the one
// saved from the editor. Needs jsPDF (window.jspdf) and optionally pdf-download.js.
(function () {
  function copyCanvas(c) {
    var out = document.createElement('canvas');
    out.width = c.width; out.height = c.height;
    out.getContext('2d').drawImage(c, 0, 0);
    return out;
  }

  // Loads url in a hidden frame (after putting value in localStorage[storageKey]) and calls call(win).
  function viaFrame(url, storageKey, value, call) {
    return new Promise(function (resolve, reject) {
      if (storageKey) localStorage.setItem(storageKey, value);
      var f = document.createElement('iframe');
      f.style.cssText = 'position:fixed;left:-10000px;top:0;width:1000px;height:1500px;border:0';
      f.src = url;
      function done(fn, v) { try { f.remove(); } catch (e) {} fn(v); }
      f.onload = function () {
        var w = f.contentWindow, tries = 0;
        (function wait() {
          if (w.captureForBulk || w.__bulkReady) {
            (w.document.fonts ? w.document.fonts.ready : Promise.resolve())
              .then(function () { return new Promise(function (r) { setTimeout(r, 300); }); })
              .then(function () { return call(w); })
              .then(function (cs) { done(resolve, (Array.isArray(cs) ? cs : [cs]).map(copyCanvas)); })
              .catch(function (e) { done(reject, e); });
          } else if (++tries < 100) { setTimeout(wait, 100); }
          else { done(reject, new Error('ページの読み込みがタイムアウトしました')); }
        })();
      };
      document.body.appendChild(f);
    });
  }

  // opts: { btn, items, renderOne(item) -> Promise<canvas[]>  (or renderAll(items) -> Promise<canvas[]>),
  //         name(items) -> string, storageKey, margin (mm, default 8) }
  async function run(opts) {
    var items = opts.items;
    if (!items.length) return;
    var btn = opts.btn, label = btn.textContent;
    btn.disabled = true;
    var win = window.open('', '_blank');
    try { if (win) { win.document.write('<meta charset="utf-8"><meta name="viewport" content="width=device-width"><p style="font:16px sans-serif;padding:2rem">PDFを作成中です…件数が多いと時間がかかります</p>'); win.document.close(); } } catch (e) {}
    try {
      var pdf = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4' });
      var pageW = pdf.internal.pageSize.getWidth(), pageH = pdf.internal.pageSize.getHeight(), margin = opts.margin != null ? opts.margin : 8, first = true;
      function addCanvas(c) {
        var imgW = pageW - margin * 2, imgH = imgW * c.height / c.width;
        if (imgH > pageH - margin * 2) { imgH = pageH - margin * 2; imgW = imgH * c.width / c.height; }
        if (!first) pdf.addPage();
        first = false;
        pdf.addImage(c.toDataURL('image/jpeg', 0.92), 'JPEG', (pageW - imgW) / 2, margin, imgW, imgH);
      }
      if (opts.renderAll) {
        btn.textContent = '作成中…';
        (await opts.renderAll(items)).forEach(addCanvas);
      } else {
        for (var i = 0; i < items.length; i++) {
          btn.textContent = '作成中 ' + (i + 1) + '/' + items.length;
          (await opts.renderOne(items[i])).forEach(addCanvas);
        }
      }
      var name = opts.name(items);
      if (win) { win.location.href = pdf.output('bloburl'); } else { pdf.save(name); }
      if (window.showPdfDownload) showPdfDownload(pdf, name);
    } catch (e) {
      console.error(e);
      try { if (win) win.document.body.innerHTML = '<p style="font:16px sans-serif;padding:2rem">PDFの作成に失敗しました。もう一度お試しください。<br><small>' + String(e && e.message || e) + '</small></p>'; } catch (e2) {}
      alert('PDFの作成に失敗しました。もう一度お試しください。');
    } finally {
      if (opts.storageKey) localStorage.removeItem(opts.storageKey);
      btn.disabled = false; btn.textContent = label;
    }
  }

  window.BulkPdf = { run: run, viaFrame: viaFrame };
  window.bulkStamp = function () { var d = new Date(); return d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0') + String(d.getDate()).padStart(2, '0'); };
})();
