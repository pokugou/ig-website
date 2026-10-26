// 日本の祝日（国民の祝日・振替休日・国民の休日）の判定。1980〜2099年の春分・秋分の日の計算式を使う。
(function () {
  function nthMonday(y, m, n) { var d = new Date(y, m, 1); var first = (8 - d.getDay()) % 7; return 1 + first + (n - 1) * 7; }
  function equinox(y, spring) {
    var base = spring ? 20.8431 : 23.2488;
    return Math.floor(base + 0.242194 * (y - 1980) - Math.floor((y - 1980) / 4));
  }
  // その年の「法律で決まっている祝日」（振替・国民の休日の前）を m*100+d の集合で返す
  function baseHolidays(y) {
    var h = {};
    function add(m, d) { h[(m) * 100 + d] = true; }
    add(1, 1); add(2, 11); add(2, 23); add(4, 29); add(5, 3); add(5, 4); add(5, 5); add(8, 11); add(11, 3); add(11, 23);
    add(1, nthMonday(y, 0, 2));            // 成人の日
    add(7, nthMonday(y, 6, 3));            // 海の日
    add(9, nthMonday(y, 8, 3));            // 敬老の日
    add(10, nthMonday(y, 9, 2));           // スポーツの日
    add(3, equinox(y, true));              // 春分の日
    add(9, equinox(y, false));             // 秋分の日
    return h;
  }
  var cache = {};
  function yearTable(y) {
    if (cache[y]) return cache[y];
    var base = baseHolidays(y), all = {}, k;
    for (k in base) all[k] = true;
    // 国民の休日：前後が祝日にはさまれた平日（日曜以外）
    for (var m = 1; m <= 12; m++) {
      var last = new Date(y, m, 0).getDate();
      for (var d = 1; d <= last; d++) {
        var key = m * 100 + d, dt = new Date(y, m - 1, d);
        if (all[key] || dt.getDay() === 0) continue;
        var prev = new Date(y, m - 1, d - 1), next = new Date(y, m - 1, d + 1);
        if (base[(prev.getMonth() + 1) * 100 + prev.getDate()] && prev.getFullYear() === y && base[(next.getMonth() + 1) * 100 + next.getDate()] && next.getFullYear() === y) all[key] = true;
      }
    }
    // 振替休日：祝日が日曜なら、次に来る祝日でない日
    for (var mm = 1; mm <= 12; mm++) {
      var l2 = new Date(y, mm, 0).getDate();
      for (var dd = 1; dd <= l2; dd++) {
        if (!base[mm * 100 + dd] || new Date(y, mm - 1, dd).getDay() !== 0) continue;
        var t = new Date(y, mm - 1, dd + 1);
        while (t.getFullYear() === y && all[(t.getMonth() + 1) * 100 + t.getDate()]) t.setDate(t.getDate() + 1);
        if (t.getFullYear() === y) all[(t.getMonth() + 1) * 100 + t.getDate()] = true;
      }
    }
    cache[y] = all;
    return all;
  }
  window.isJpHoliday = function (d) { return !!yearTable(d.getFullYear())[(d.getMonth() + 1) * 100 + d.getDate()]; };
  // 会社が一般的に休みになる期間（月, 日 〜 月, 日）。変えたいときはここを直す
  //   年末年始：12/29〜1/3　　お盆休み：8/13〜8/16
  var CLOSED = [[12, 29, 12, 31], [1, 1, 1, 3], [8, 13, 8, 16]];
  window.isCompanyClosed = function (d) {
    var v = (d.getMonth() + 1) * 100 + d.getDate();
    return CLOSED.some(function (r) { return v >= r[0] * 100 + r[1] && v <= r[2] * 100 + r[3]; });
  };
  // 土日・祝日・会社の休み（年末年始・お盆）にあたるときは、前（過去）へ戻って最初の営業日を返す
  window.isBusinessDay = function (d) { return d.getDay() !== 0 && d.getDay() !== 6 && !window.isJpHoliday(d) && !window.isCompanyClosed(d); };
  window.prevBusinessDay = function (d) {
    var t = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    while (!window.isBusinessDay(t)) t.setDate(t.getDate() - 1);
    return t;
  };
})();
