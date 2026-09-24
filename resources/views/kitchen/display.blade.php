<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Display — {{ $cabang->nama_cabang }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%; width: 100%;
            background: #0f172a; color: #f1f5f9;
            font-family: 'Segoe UI', system-ui, sans-serif;
            overflow: hidden;
        }
        .screen { display: flex; flex-direction: column; height: 100vh; padding: 14px 16px; gap: 12px; }
        .header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 18px; background: #1e293b; border-radius: 14px; flex-shrink: 0;
        }
        .header-title { font-size: clamp(1.2rem, 2.4vw, 1.8rem); font-weight: 800; color: #2D6A4F; }
        .header-cabang { font-size: clamp(0.75rem, 1.5vw, 1rem); color: #94a3b8; margin-top: 2px; }
        .clock { font-size: clamp(1.6rem, 3vw, 2.4rem); font-weight: 700; color: #34d399; font-variant-numeric: tabular-nums; font-family: 'Courier New', monospace; }

        .board {
            flex: 1; min-height: 0; overflow-y: auto;
            display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px; align-content: start;
        }
        .order-card { background: #1e293b; border-radius: 14px; padding: 14px; display: flex; flex-direction: column; gap: 8px; border-left: 6px solid #2D6A4F; }
        .order-card.overdue { border-left-color: #ef4444; animation: pulseRed 1.5s ease-in-out infinite; }
        @keyframes pulseRed { 0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,0);} 50% { box-shadow: 0 0 14px 3px rgba(239,68,68,.35);} }
        .order-card-head { display: flex; justify-content: space-between; align-items: baseline; }
        .order-meja { font-size: 1.3rem; font-weight: 800; color: #f1f5f9; }
        .order-timer { font-size: 0.95rem; font-weight: 700; color: #94a3b8; font-variant-numeric: tabular-nums; }
        .item-row { background: #0f172a; border-radius: 8px; padding: 8px 10px; display: flex; flex-direction: column; gap: 4px; }
        .item-name { font-weight: 700; font-size: 0.95rem; }
        .item-varian { font-size: 0.75rem; color: #94a3b8; }
        .item-catatan { font-size: 0.75rem; color: #fbbf24; }
        .item-actions { display: flex; gap: 6px; margin-top: 4px; }
        .btn-siap, .btn-komplain {
            flex: 1; border: none; border-radius: 6px; padding: 6px 8px; font-weight: 700;
            font-size: 0.78rem; cursor: pointer;
        }
        .btn-siap { background: #2D6A4F; color: #fff; }
        .btn-siap:hover { background: #24573f; }
        .btn-komplain { background: #7f1d1d; color: #fecaca; }
        .btn-komplain:hover { background: #991b1b; }
        .empty-state { color: #334155; text-align: center; padding: 60px 20px; font-size: 1.1rem; grid-column: 1/-1; }
    </style>
</head>
<body>
<div class="screen">
    <div class="header">
        <div>
            <div class="header-title">🍳 KITCHEN DISPLAY</div>
            <div class="header-cabang">{{ $cabang->nama_cabang }}</div>
        </div>
        <div class="clock" id="clock">--:--:--</div>
    </div>

    <div class="board" id="board">
        <div class="empty-state">Memuat...</div>
    </div>
</div>

<script>
(function () {
'use strict';

const PENDING_URL   = '{{ route('kitchen.pending', $cabang) }}';
const SIAP_URL_TPL   = '{{ url('/kitchen/item') }}/__ID__/siap';
const KOMPLAIN_URL_TPL = '{{ url('/kitchen/item') }}/__ID__/komplain';
const REFRESH_MS     = {{ max(3, (int) $setting->auto_refresh_seconds) * 1000 }};
const CSRF_TOKEN      = @json(csrf_token());

function tickClock() {
    const now = new Date();
    document.getElementById('clock').textContent =
        String(now.getHours()).padStart(2,'0') + ':' +
        String(now.getMinutes()).padStart(2,'0') + ':' +
        String(now.getSeconds()).padStart(2,'0');
}
setInterval(tickClock, 1000);
tickClock();

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatVarian(varian) {
    if (!varian || typeof varian !== 'object') return '';
    return Object.values(varian).filter(Boolean).join(', ');
}

function minutesSince(iso) {
    const started = new Date(iso).getTime();
    return Math.max(0, Math.floor((Date.now() - started) / 60000));
}

function renderBoard(orders) {
    const board = document.getElementById('board');
    if (!orders || orders.length === 0) {
        board.innerHTML = '<div class="empty-state">Tidak ada pesanan menunggu</div>';
        return;
    }

    board.innerHTML = orders.map(function (o) {
        const menit = minutesSince(o.started_at);
        // E6 — kalau ada item dengan estimasi (waktu_siap_menit) terisi, overdue
        // dihitung dari deadline TERKETAT (paling cepat harus siap) di antara item
        // tsb. Kalau tidak ada satupun item punya estimasi, fallback ke aturan flat 15 menit.
        const estimasiList = o.items.map(function (it) { return it.waktu_siap_menit; }).filter(function (v) { return v !== null && v !== undefined; });
        const deadlineMenit = estimasiList.length > 0 ? Math.min.apply(null, estimasiList) : 15;
        const overdue = menit >= deadlineMenit;
        const itemsHtml = o.items.map(function (it) {
            const varianText = formatVarian(it.varian);
            const itemMenit = minutesSince(it.created_at);
            const itemOverdue = it.waktu_siap_menit !== null && it.waktu_siap_menit !== undefined && itemMenit >= it.waktu_siap_menit;
            const estimasiText = (it.waktu_siap_menit !== null && it.waktu_siap_menit !== undefined)
                ? `<div class="item-varian" style="${itemOverdue ? 'color:#ef4444;font-weight:700' : ''}">⏳ Estimasi ${it.waktu_siap_menit} menit${itemOverdue ? ' — TERLAMBAT' : ''}</div>`
                : '';
            return `
                <div class="item-row">
                    <div class="item-name">${escHtml(it.nama)} x${it.qty}</div>
                    ${varianText ? `<div class="item-varian">${escHtml(varianText)}</div>` : ''}
                    ${estimasiText}
                    ${it.catatan ? `<div class="item-catatan">📝 ${escHtml(it.catatan)}</div>` : ''}
                    <div class="item-actions">
                        <button class="btn-siap" data-id="${it.id}">✅ Siap</button>
                        <button class="btn-komplain" data-id="${it.id}">⚠️ Komplain</button>
                    </div>
                </div>`;
        }).join('');

        return `
            <div class="order-card ${overdue ? 'overdue' : ''}">
                <div class="order-card-head">
                    <div class="order-meja">${escHtml(o.meja_nama)}</div>
                    <div class="order-timer">⏱️ ${menit} menit</div>
                </div>
                ${itemsHtml}
            </div>`;
    }).join('');

    board.querySelectorAll('.btn-siap').forEach(function (btn) {
        btn.addEventListener('click', function () { markSiap(btn.dataset.id); });
    });
    board.querySelectorAll('.btn-komplain').forEach(function (btn) {
        btn.addEventListener('click', function () { promptKomplain(btn.dataset.id); });
    });
}

async function postAction(url) {
    return fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: '{}',
    });
}

async function markSiap(itemId) {
    try {
        await postAction(SIAP_URL_TPL.replace('__ID__', itemId));
        fetchData();
    } catch (e) { /* diamkan, akan ke-refresh siklus berikutnya */ }
}

async function promptKomplain(itemId) {
    const reason = window.prompt('Alasan komplain?');
    if (!reason) return;
    try {
        await fetch(KOMPLAIN_URL_TPL.replace('__ID__', itemId), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ reason: reason }),
        });
        fetchData();
    } catch (e) { /* diamkan */ }
}

let fetchInFlight = false;
async function fetchData() {
    if (fetchInFlight) return;
    fetchInFlight = true;
    try {
        const resp = await fetch(PENDING_URL, { cache: 'no-store' });
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const data = await resp.json();
        renderBoard(data.orders);
    } catch (e) {
        // biarkan board lama tetap tampil, coba lagi siklus berikutnya
    } finally {
        fetchInFlight = false;
    }
}

fetchData();
setInterval(fetchData, REFRESH_MS);

})();
</script>
</body>
</html>
