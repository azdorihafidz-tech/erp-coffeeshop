<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk Bill {{ $bill->nomor_bill }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 11px; width: 76mm; margin: 0 auto; padding: 4mm; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 4px 0; }
        .row { display: flex; justify-content: space-between; }
        .item-name { flex: 1; }
        .header-store { font-size: 14px; font-weight: bold; }
        .page-break { page-break-after: always; }
        .kitchen-item { font-size: 14px; font-weight: bold; margin-bottom: 6px; }
        .kitchen-varian { font-size: 11px; font-weight: normal; color: #333; }
    </style>
</head>
<body>

{{-- ===== HALAMAN 1 — CUSTOMER COPY ===== --}}
<div class="page-break">
    <div class="center header-store">KOPI DRIP</div>
    <div class="center">{{ $bill->meja->cabang->nama_cabang }}</div>
    <div class="line"></div>

    <div class="row"><span>{{ $bill->meja->nama_meja }}</span><span>Bill #{{ $bill->nomor_bill }}</span></div>
    <div class="row"><span>{{ $bill->started_at?->format('d/m/Y H:i') }}</span><span></span></div>
    <div class="line"></div>

    @foreach($bill->items as $bi)
    <div class="row">
        <span class="item-name">{{ $bi->item->nama_item }} x{{ (int) $bi->qty }}</span>
        <span>Rp {{ number_format($bi->subtotal, 0, ',', '.') }}</span>
    </div>
    @endforeach

    <div class="line"></div>
    <div class="row bold">
        <span>TOTAL</span>
        <span>Rp {{ number_format($bill->items->sum('subtotal'), 0, ',', '.') }}</span>
    </div>
    <div class="line"></div>

    <div class="center" style="margin-top:8px">
        Tempel di meja — Bayar di kasir saat selesai
    </div>
</div>

{{-- ===== HALAMAN 2 — KITCHEN / BARISTA COPY ===== --}}
<div>
    <div class="center bold" style="font-size:16px">KOPI DRIP DAPUR</div>
    <div class="line"></div>

    <div class="row bold" style="font-size:14px"><span>{{ $bill->meja->nama_meja }}</span><span>#{{ $bill->nomor_bill }}</span></div>
    <div>Jam order: {{ $bill->started_at?->format('H:i') }}</div>
    <div class="line"></div>

    @foreach($bill->items as $bi)
    <div class="kitchen-item">
        {{ (int) $bi->qty }}x {{ $bi->item->nama_item }}
        @if(!empty($bi->varian))
        <div class="kitchen-varian">{{ collect($bi->varian)->map(fn($v, $k) => "$k: $v")->join(', ') }}</div>
        @endif
        @if($bi->catatan)
        <div class="kitchen-varian">Catatan: {{ $bi->catatan }}</div>
        @endif
    </div>
    @endforeach

    <div class="line"></div>
    @if($bill->cabang->qr_ordering_active)
    <div class="center" style="margin-top:8px">Cek juga di kitchen display</div>
    @endif
</div>

</body>
</html>
