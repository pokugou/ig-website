// Arrow-key movement between the text fields of an editor page.
//   ↑ ↓ : jump to the field above / below (a multi-line box moves only from its first / last line)
//   ← → : jump to the field on the left / right when the caret is at the start / end of the text
// Fields are found by their position on screen, so it works for forms, cards and tables alike.
// Selects, dates and number boxes are never entered this way (their own arrow keys change the value).
(function () {
  var TEXT = /^(text|tel|email|search|url|)$/;
  function isTextField(el) {
    return !!el && ((el.tagName === 'INPUT' && TEXT.test(el.getAttribute('type') || 'text')) || el.tagName === 'TEXTAREA');
  }
  function usable(el) {
    if (!isTextField(el) || el.disabled) return false;
    var r = el.getBoundingClientRect();
    if (r.width < 4 || r.height < 4) return false;
    var cs = getComputedStyle(el);
    return cs.visibility !== 'hidden' && cs.display !== 'none';
  }
  function singleLine(ta) {
    var lh = parseFloat(getComputedStyle(ta).lineHeight) || 20;
    return ta.scrollHeight <= lh * 1.6 + 16;
  }
  function find(el, dir) {
    var c = el.getBoundingClientRect(), cx = c.left + c.width / 2, cy = c.top + c.height / 2;
    var best = null, bestScore = Infinity;
    Array.prototype.forEach.call(document.querySelectorAll('input,textarea'), function (o) {
      if (o === el || !usable(o)) return;
      var r = o.getBoundingClientRect(), ox = r.left + r.width / 2, oy = r.top + r.height / 2, score;
      if (dir === 'up' || dir === 'down') {
        var gap = dir === 'up' ? c.top - r.bottom : r.top - c.bottom;
        if (gap < -6) return;
        var dx = Math.max(0, Math.max(c.left - r.right, r.left - c.right));
        score = Math.max(gap, 0) + dx * 3 + Math.abs(cx - ox) * 0.05;
      } else {
        var gx = dir === 'left' ? c.left - r.right : r.left - c.right;
        if (gx < -6) return;
        var dy = Math.max(0, Math.max(c.top - r.bottom, r.top - c.bottom));
        score = Math.max(gx, 0) + dy * 3 + Math.abs(cy - oy) * 0.05;
      }
      if (score < bestScore) { bestScore = score; best = o; }
    });
    return best;
  }
  document.addEventListener('keydown', function (e) {
    if (e.defaultPrevented || e.isComposing || e.keyCode === 229 || e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
    var k = e.key;
    if (k !== 'ArrowUp' && k !== 'ArrowDown' && k !== 'ArrowLeft' && k !== 'ArrowRight') return;
    var el = e.target;
    if (!isTextField(el)) return;
    var v = el.value, s = el.selectionStart, en = el.selectionEnd;
    if (s == null || s !== en) return;
    var area = el.tagName === 'TEXTAREA', dir = null;
    if (k === 'ArrowLeft' && s === 0) dir = 'left';
    else if (k === 'ArrowRight' && s === v.length) dir = 'right';
    else if (k === 'ArrowUp') { if (!area || (v.lastIndexOf('\n', s - 1) === -1 && (singleLine(el) || s === 0))) dir = 'up'; }
    else if (k === 'ArrowDown') { if (!area || (v.indexOf('\n', s) === -1 && (singleLine(el) || s === v.length))) dir = 'down'; }
    if (!dir) return;
    var t = find(el, dir);
    if (!t) return;
    e.preventDefault();
    t.focus();
    var len = (t.value || '').length, pos = dir === 'left' ? len : dir === 'right' ? 0 : Math.min(s, len);
    try { t.setSelectionRange(pos, pos); } catch (x) {}
  });
})();
