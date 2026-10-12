// Gemeinsame Funktionen der Tvheadend-Kacheln.

var TRASH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 14h10l1-14"/><path d="M10 11v6M14 11v6"/></svg>';

/** Element mit Klasse und Text anlegen. */
function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text !== undefined) { e.textContent = text; } return e; }

/** Einstellung im Browser merken (kann fehlen, zum Beispiel im privaten Fenster). */
function save(k, v) { try { localStorage.setItem('tvh_' + k, v); } catch (e) {} }
function load(k) { try { return localStorage.getItem('tvh_' + k); } catch (e) { return null; } }

/** Kurze Meldung unten in der Kachel (Element mit der ID "toast"). */
function toast(text, bad) {
  var t = document.getElementById('toast'); if (!t) { return; }
  t.textContent = text; t.className = 'toast' + (bad ? ' bad' : ''); t.style.display = 'block';
  setTimeout(function () { t.style.display = 'none'; }, 2200);
}

/** Hinweis "Server nicht erreichbar" ein- oder ausblenden (Element mit der ID "off"). */
function setOnline(online, text) {
  var off = document.getElementById('off'); if (!off) { return; }
  off.textContent = online ? '' : text; off.style.display = online ? 'none' : 'block';
}

/** Die Kachel füllt den Platz innerhalb der Ränder, die die Visualisierung am body setzt. */
function tvhFit(rootId, cb) {
  var root = document.getElementById(rootId);
  function fit() {
    var cs = getComputedStyle(document.body);
    var h = window.innerHeight - (parseFloat(cs.marginTop) || 0) - (parseFloat(cs.marginBottom) || 0);
    root.style.height = Math.max(0, h) + 'px';
    if (cb) { cb(); }
  }
  window.addEventListener('resize', fit);
  fit();
}

/** Zeit als HH:MM, Datum und Zeit, Dauer in Stunden und Minuten. */
function fmtTime(ts) { return new Date(ts * 1000).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}); }
function fmtDate(ts) { return new Date(ts * 1000).toLocaleDateString([], {day: '2-digit', month: '2-digit'}) + ' ' + fmtTime(ts); }
function fmtDuration(sec) { sec = Math.max(0, Math.floor(sec)); var h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60); return h > 0 ? h + ' h ' + m + ' min' : m + ' min'; }
function fmtSize(bytes) { bytes = Number(bytes || 0); return bytes >= 1073741824 ? (bytes / 1073741824).toFixed(1) + ' GB' : (bytes / 1048576).toFixed(0) + ' MB'; }
