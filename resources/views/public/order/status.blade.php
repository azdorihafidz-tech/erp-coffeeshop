@extends('layouts.public')

@section('title', 'Status Pesanan')

@section('content')

<div class="kd-header">
    @if(file_exists(public_path('images/logo.png')))
    <img src="{{ asset('images/logo.png') }}" alt="Kopi Drip">
    @endif
    <div class="kd-title">
        <div class="brand">Kopi Drip Sidikalang</div>
        <div class="outlet">{{ $queue->meja->cabang->nama_cabang }}</div>
    </div>
    <div class="kd-meja-badge">MEJA {{ $queue->meja->nomor_meja }}</div>
</div>

<div style="padding:32px 20px;text-align:center">
    <div id="kdStatusIcon" style="font-size:3rem;margin-bottom:12px">⏳</div>
    <h2 id="kdStatusTitle" style="margin:0 0 8px">Pesanan Terkirim!</h2>
    <p id="kdStatusMessage" style="color:#555;margin:0 0 20px">Menunggu konfirmasi kasir...</p>

    <div style="background:#fff;border-radius:12px;padding:16px;text-align:left;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <div style="font-weight:700;margin-bottom:8px">Ringkasan Pesanan</div>
        @foreach($queue->payload as $row)
        @php $item = \App\Models\Item::find($row['item_id']); @endphp
        <div style="display:flex;justify-content:space-between;font-size:.88rem;padding:4px 0">
            <span>{{ $item?->nama_item ?? 'Item' }} x{{ $row['qty'] }}</span>
        </div>
        @endforeach
        @if($queue->catatan_umum)
        <div style="font-size:.82rem;color:#777;margin-top:8px">Catatan: {{ $queue->catatan_umum }}</div>
        @endif
        @if($estimasiMenit)
        <div style="font-size:.82rem;color:#2D6A4F;margin-top:8px;font-weight:600">⏳ Estimasi ~{{ $estimasiMenit }} menit setelah dikonfirmasi kasir</div>
        @endif
    </div>

    <div id="kdRetryWrap" style="display:none;margin-top:20px">
        <a href="{{ route('order.menu', ['cabang_kode' => $queue->meja->cabang->kode_cabang, 'meja_nomor' => $queue->meja->nomor_meja, 'token' => $queue->meja->qr_token]) }}" class="kd-btn-primary" style="display:inline-block;text-decoration:none;width:auto;padding:12px 24px">
            Coba Lagi
        </a>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var POLL_URL = @json(route('order.poll-status', ['queue_id' => $queue->id]));

    var currentStatus = @json($queue->status);

    function poll() {
        if (currentStatus !== 'pending') return;

        fetch(POLL_URL, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.status === currentStatus) return;
                currentStatus = data.status;
                render(data);
            })
            .catch(function () {});
    }

    function render(data) {
        var icon = document.getElementById('kdStatusIcon');
        var title = document.getElementById('kdStatusTitle');
        var msg = document.getElementById('kdStatusMessage');

        if (data.status === 'approved') {
            icon.textContent = '✅';
            title.textContent = 'Pesanan Diterima!';
            msg.textContent = data.message || 'Pesanan Anda sudah dikonfirmasi kasir.';
        } else if (data.status === 'rejected') {
            icon.textContent = '❌';
            title.textContent = 'Pesanan Ditolak';
            msg.textContent = data.message || 'Silakan hubungi kasir.';
            document.getElementById('kdRetryWrap').style.display = 'block';
        }
    }

    if (currentStatus === 'pending') {
        setInterval(poll, 3000);
    } else {
        render({ status: currentStatus });
    }
})();
</script>
@endpush

@endsection
