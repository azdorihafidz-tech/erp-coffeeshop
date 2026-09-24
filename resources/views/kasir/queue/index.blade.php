@extends('layouts.app')

@section('title', 'Antrian Order')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-bell-fill me-2 text-warning"></i>Antrian Order QR</h5>
    <div class="d-flex gap-2">
        <x-panduan-button slug="kasir-queue" />
        <a href="{{ route('kasir.layout-meja') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Layout Meja</a>
    </div>
</div>

<div id="kdQueueList" class="row g-3">
    @forelse($queues as $queue)
    <div class="col-md-6 col-lg-4 kd-queue-card" data-queue-id="{{ $queue->id }}">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h6 class="fw-bold mb-1">{{ $queue->meja->nama_meja }}</h6>
                    <span class="badge bg-light text-dark">{{ $queue->created_at->diffForHumans() }}</span>
                </div>
                @if($queue->customer_name)
                <div class="small text-muted mb-2"><i class="bi bi-person me-1"></i>{{ $queue->customer_name }}</div>
                @endif

                <ul class="list-unstyled small mb-2">
                    @foreach($queue->payload as $row)
                    @php $item = \App\Models\Item::find($row['item_id']); @endphp
                    <li>{{ $item?->nama_item ?? 'Item #'.$row['item_id'] }} x{{ $row['qty'] }}</li>
                    @endforeach
                </ul>

                <span class="badge {{ $queue->payment_mode === 'bayar_dulu' ? 'bg-info' : 'bg-secondary' }}">
                    {{ $queue->payment_mode === 'bayar_dulu' ? 'Bayar Dulu (QRIS)' : 'Bayar di Kasir' }}
                </span>

                @if($queue->catatan_umum)
                <div class="small text-muted mt-2">Catatan: {{ $queue->catatan_umum }}</div>
                @endif

                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-success flex-fill" onclick="kdApprove({{ $queue->id }})">
                        <i class="bi bi-check-circle me-1"></i>Approve
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger flex-fill" onclick="kdOpenReject({{ $queue->id }})">
                        <i class="bi bi-x-circle me-1"></i>Reject
                    </button>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <p class="text-center text-muted py-5" id="kdEmptyState">Tidak ada order yang menunggu approve.</p>
    </div>
    @endforelse
</div>

{{-- Modal Reject --}}
<div class="modal fade" id="kdModalReject" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Alasan Reject <span class="text-danger">*</span> <x-tooltip key="kasir_queue.reject_reason" /></label>
                <textarea id="kdRejectReason" class="form-control" rows="3" placeholder="mis. bahan habis, pesanan salah"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" onclick="kdConfirmReject()">Reject</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var CSRF = @json(csrf_token());
    var rejectModal = new bootstrap.Modal(document.getElementById('kdModalReject'));
    var currentRejectId = null;

    window.kdApprove = function (queueId) {
        if (!confirm('Approve order ini? Bill akan otomatis dibuka.')) return;

        fetch('/kasir/queue/' + queueId + '/approve', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
            if (!result.ok) { alert(result.data.message || 'Gagal approve.'); return; }
            removeCard(queueId);
            alert('Order berhasil di-approve, bill dibuka.');
        });
    };

    window.kdOpenReject = function (queueId) {
        currentRejectId = queueId;
        document.getElementById('kdRejectReason').value = '';
        rejectModal.show();
    };

    window.kdConfirmReject = function () {
        var reason = document.getElementById('kdRejectReason').value.trim();
        if (!reason) { alert('Alasan reject wajib diisi.'); return; }

        fetch('/kasir/queue/' + currentRejectId + '/reject', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ reason: reason }),
        })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
            if (!result.ok) { alert(result.data.message || 'Gagal reject.'); return; }
            rejectModal.hide();
            removeCard(currentRejectId);
        });
    };

    function removeCard(queueId) {
        var card = document.querySelector('.kd-queue-card[data-queue-id="' + queueId + '"]');
        if (card) card.remove();
        if (document.querySelectorAll('.kd-queue-card').length === 0) {
            document.getElementById('kdQueueList').innerHTML = '<div class="col-12"><p class="text-center text-muted py-5">Tidak ada order yang menunggu approve.</p></div>';
        }
    }

    setInterval(function () { window.location.reload(); }, 10000);
})();
</script>
@endpush

@endsection
