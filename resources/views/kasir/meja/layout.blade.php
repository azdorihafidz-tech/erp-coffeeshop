@extends('layouts.app')

@section('title', 'Layout Meja')

@section('content')

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap-fill me-2 text-success"></i>Layout Meja — {{ $cabang->nama_cabang }}</h5>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <x-panduan-button slug="kasir-layout-meja" />
        <a href="{{ route('kasir.queue') }}" class="btn btn-sm {{ $queuePendingCount > 0 ? 'btn-warning' : 'btn-outline-secondary' }}" id="btnQueueNotif">
            <i class="bi bi-bell-fill me-1"></i>
            <span id="queueCountBadge">{{ $queuePendingCount }}</span> order menunggu approve
        </a>
        <span id="komplainBadge" class="btn btn-sm btn-danger d-none">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <span id="komplainCount">0</span> komplain dapur
        </span>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="kdRefreshLayout()">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>
</div>

@if($lokasiList->count() > 1)
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <select name="cabang" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($lokasiList as $c)
                    <option value="{{ $c->id }}" @selected($cabang->id == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>
@endif

<div class="mb-3 d-flex gap-3 flex-wrap small">
    <span><span class="badge" style="background:#E5E5E5;color:#333">&nbsp;&nbsp;</span> Kosong</span>
    <span><span class="badge" style="background:#B7E4C7;color:#333">&nbsp;&nbsp;</span> &lt;30 menit</span>
    <span><span class="badge" style="background:#FCD34D;color:#333">&nbsp;&nbsp;</span> 30–90 menit</span>
    <span><span class="badge" style="background:#FCA5A5;color:#333">&nbsp;&nbsp;</span> &gt;90 menit</span>
    <span><span class="badge" style="background:#93C5FD;color:#333">&nbsp;&nbsp;</span> Menunggu Bayar</span>
</div>

<div id="kdMejaGrid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px">
    @foreach($mejas as $meja)
    @php $bill = $meja->bills->first(); @endphp
    <div class="kd-meja-box" data-meja-id="{{ $meja->id }}" data-started-at="{{ $bill?->started_at?->toIso8601String() }}" data-status="{{ $bill ? $bill->status : 'kosong' }}"
         style="border-radius:10px;padding:12px;cursor:pointer;min-height:100px;background:{{ $bill ? ($bill->status === 'waiting_payment' ? '#93C5FD' : '#B7E4C7') : '#E5E5E5' }}"
         onclick="kdOpenMejaDetail({{ $meja->id }})">
        <div class="fw-bold">{{ $meja->nama_meja }}</div>
        <div class="small text-muted">Kapasitas {{ $meja->kapasitas ?? '-' }}</div>
        @if($bill)
        <div class="small mt-2 kd-timer-text">Terisi ...</div>
        <div class="small fw-semibold">{{ $bill->items->count() }} item — Rp {{ number_format($bill->items->sum('subtotal'), 0, ',', '.') }}</div>
        @else
        <div class="small mt-2 text-muted">Kosong</div>
        @endif
    </div>
    @endforeach
</div>

<div class="mt-3">
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-plus-circle me-1"></i>Tandai Meja Terisi
        </button>
        <ul class="dropdown-menu">
            @forelse($mejas->filter(fn($m) => $m->bills->isEmpty()) as $mejaKosong)
            <li><a class="dropdown-item" href="#" onclick="kdTandaiTerisi({{ $mejaKosong->id }});return false;">{{ $mejaKosong->nama_meja }}</a></li>
            @empty
            <li><span class="dropdown-item-text text-muted">Semua meja terisi</span></li>
            @endforelse
        </ul>
    </div>
</div>

{{-- Modal Detail Meja --}}
<div class="modal fade" id="kdModalDetailMeja" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kdModalMejaTitle">Detail Meja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="kdModalMejaBody">
                <div class="text-center text-muted py-4">Memuat...</div>
            </div>
            <div class="modal-footer flex-wrap gap-2" id="kdModalMejaFooter"></div>
        </div>
    </div>
</div>

@push('scripts')
<style>
@keyframes kdFlashPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(220,53,69,0); transform: scale(1); }
    50% { box-shadow: 0 0 0 6px rgba(220,53,69,0.35); transform: scale(1.06); }
}
.kd-flash { animation: kdFlashPulse 0.5s ease-in-out 2; }
</style>
<script>
(function () {
    var DETAIL_URL_BASE = @json(url('/kasir/meja'));
    var TANDAI_URL_BASE = @json(url('/kasir/meja'));
    var CSRF = @json(csrf_token());
    var currentMejaId = null;
    var modalEl = new bootstrap.Modal(document.getElementById('kdModalDetailMeja'));

    function updateTimers() {
        document.querySelectorAll('.kd-meja-box').forEach(function (box) {
            var startedAt = box.dataset.startedAt;
            if (!startedAt) return;
            var mins = Math.floor((Date.now() - new Date(startedAt).getTime()) / 60000);
            var timerEl = box.querySelector('.kd-timer-text');
            if (timerEl) timerEl.textContent = 'Terisi ' + mins + ' menit';

            var color = '#B7E4C7';
            if (box.dataset.status === 'waiting_payment') {
                color = '#93C5FD';
            } else if (mins > 90) {
                color = '#FCA5A5';
            } else if (mins > 30) {
                color = '#FCD34D';
            }
            box.style.background = color;
        });
    }

    window.kdOpenMejaDetail = function (mejaId) {
        currentMejaId = mejaId;
        document.getElementById('kdModalMejaBody').innerHTML = '<div class="text-center text-muted py-4">Memuat...</div>';
        document.getElementById('kdModalMejaFooter').innerHTML = '';
        modalEl.show();

        fetch(DETAIL_URL_BASE + '/' + mejaId, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(renderDetail)
            .catch(function () {
                document.getElementById('kdModalMejaBody').innerHTML = '<div class="text-danger">Gagal memuat detail.</div>';
            });
    };

    function renderDetail(data) {
        document.getElementById('kdModalMejaTitle').textContent = data.meja.nama_meja;
        var body = document.getElementById('kdModalMejaBody');
        var footer = document.getElementById('kdModalMejaFooter');

        if (!data.bill) {
            body.innerHTML = '<p class="text-muted">Meja kosong, belum ada bill.</p>';
            footer.innerHTML = '';
            return;
        }

        var html = '<table class="table table-sm"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Subtotal</th><th>Status</th></tr></thead><tbody>';
        var total = 0;
        (data.bill.items || []).forEach(function (bi) {
            total += parseFloat(bi.subtotal);
            var badge = bi.status_dapur === 'siap' ? 'success' : (bi.status_dapur === 'komplain' ? 'danger' : 'secondary');
            html += '<tr><td>' + (bi.item ? bi.item.nama_item : ('#' + bi.item_id)) + '</td><td class="text-end">' + bi.qty + '</td><td class="text-end">Rp ' + Number(bi.subtotal).toLocaleString('id-ID') + '</td><td><span class="badge bg-' + badge + '">' + bi.status_dapur + '</span></td></tr>';
        });
        html += '</tbody></table><div class="fw-bold text-end">Total: Rp ' + total.toLocaleString('id-ID') + '</div>';
        body.innerHTML = html;

        footer.innerHTML =
            '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="kdOpenTransfer(' + data.bill.id + ')"><i class="bi bi-arrow-left-right me-1"></i>Transfer Meja</button>' +
            '<button type="button" class="btn btn-sm btn-outline-success" onclick="kdPrintStruk(' + data.bill.id + ')"><i class="bi bi-printer me-1"></i>Print Struk</button>' +
            '<button type="button" class="btn btn-sm btn-success" onclick="kdBayar(' + data.bill.id + ', ' + total + ')"><i class="bi bi-cash-coin me-1"></i>Bayar Sekarang</button>';
    }

    window.kdTandaiTerisi = function (mejaId) {
        fetch(TANDAI_URL_BASE + '/' + mejaId + '/tandai-terisi', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) { kdRefreshLayout(); } else { alert(data.message); }
        });
    };

    var MEJA_LIST = @json($mejas->map(fn($m) => ['id' => $m->id, 'nama' => $m->nama_meja])->values());

    window.kdOpenTransfer = function (billId) {
        var options = MEJA_LIST.map(function (m) { return m.nama + ' (id:' + m.id + ')'; }).join('\n');
        var pilihan = prompt('Transfer ke meja mana? Ketik nomor id meja tujuan.\n\nDaftar meja:\n' + options);
        if (!pilihan) return;
        var mejaBaruId = parseInt(pilihan, 10);
        if (!mejaBaruId) { alert('Input tidak valid.'); return; }

        fetch('/kasir/bill/' + billId + '/transfer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ meja_baru_id: mejaBaruId }),
        })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
            if (!result.ok) { alert(result.data.message || 'Gagal transfer.'); return; }
            alert('Bill berhasil dipindah.');
            kdRefreshLayout();
        });
    };

    window.kdPrintStruk = function (billId) {
        window.open('/kasir/bill/' + billId + '/print', '_blank');
    };

    window.kdBayar = function (billId, total) {
        if (!confirm('Konfirmasi bayar Rp ' + total.toLocaleString('id-ID') + ' (tunai)?')) return;
        fetch('/kasir/bill/' + billId + '/bayar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ payments: [{ metode: 'tunai', jumlah: total }] }),
        })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
            if (!result.ok) { alert(result.data.message || 'Gagal.'); return; }
            alert('Bill berhasil dibayar & ditutup.');
            modalEl.hide();
            kdRefreshLayout();
        });
    };

    window.kdRefreshLayout = function () {
        window.location.reload();
    };

    setInterval(updateTimers, 15000);
    updateTimers();

    // ── E6 — Notifikasi Kasir: polling badge queue + komplain, flash on new ──
    var POLL_NOTIF_URL = @json(route('kasir.layout-meja.poll-notifikasi', $cabang));
    var prevQueueCount = {{ $queuePendingCount }};
    var prevKomplainCount = 0;

    function playAlertBeep() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(660, ctx.currentTime);
            gain.gain.setValueAtTime(0.35, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.4);
        } catch (_) {}
    }

    function flashElement(el) {
        el.classList.add('kd-flash');
        setTimeout(function () { el.classList.remove('kd-flash'); }, 1500);
    }

    function pollNotifikasi() {
        fetch(POLL_NOTIF_URL, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var queueBadge = document.getElementById('queueCountBadge');
                var btnQueue = document.getElementById('btnQueueNotif');
                queueBadge.textContent = data.queue_pending_count;
                btnQueue.classList.toggle('btn-warning', data.queue_pending_count > 0);
                btnQueue.classList.toggle('btn-outline-secondary', data.queue_pending_count === 0);
                if (data.queue_pending_count > prevQueueCount) {
                    flashElement(btnQueue);
                    playAlertBeep();
                }
                prevQueueCount = data.queue_pending_count;

                var komplainBadge = document.getElementById('komplainBadge');
                var komplainCountEl = document.getElementById('komplainCount');
                komplainCountEl.textContent = data.komplain_count;
                komplainBadge.classList.toggle('d-none', data.komplain_count === 0);
                if (data.komplain_count > prevKomplainCount) {
                    flashElement(komplainBadge);
                    playAlertBeep();
                }
                prevKomplainCount = data.komplain_count;
            })
            .catch(function () { /* diamkan, coba lagi siklus berikutnya */ });
    }
    setInterval(pollNotifikasi, 10000);
    pollNotifikasi();
})();
</script>
@endpush

@endsection
