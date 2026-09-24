@extends('layouts.public')

@section('title', 'Menu — ' . $cabang->nama_cabang)

@section('content')

<div class="kd-header">
    @if(file_exists(public_path('images/logo.png')))
    <img src="{{ asset('images/logo.png') }}" alt="Kopi Drip">
    @endif
    <div class="kd-title">
        <div class="brand">Kopi Drip Sidikalang</div>
        <div class="outlet">{{ $cabang->nama_cabang }}</div>
    </div>
    <div class="kd-meja-badge">MEJA {{ $meja->nomor_meja }}</div>
</div>

<div class="kd-tabs" id="kdTabs">
    @foreach($categories as $i => $kategori)
        @if(($itemsByKategori[$kategori->id] ?? collect())->isNotEmpty())
        <button type="button" class="kd-tab-btn {{ $i === 0 ? 'active' : '' }}" data-kategori="{{ $kategori->id }}" onclick="kdSwitchTab({{ $kategori->id }}, this)">
            {{ $kategori->nama_kategori }}
        </button>
        @endif
    @endforeach
</div>

@forelse($categories as $i => $kategori)
    @php $itemsKategori = $itemsByKategori[$kategori->id] ?? collect(); @endphp
    @if($itemsKategori->isNotEmpty())
    <div class="kd-grid kd-kategori-section" data-kategori="{{ $kategori->id }}" style="{{ $i === 0 ? '' : 'display:none' }}">
        @foreach($itemsKategori as $item)
        <div class="kd-card {{ $item->tersedia ? '' : 'habis' }}" data-item-id="{{ $item->id }}">
            <div class="thumb">
                @if($item->foto)
                <img src="{{ asset('storage/'.$item->foto) }}" alt="{{ $item->nama_item }}">
                @else
                <span>☕</span>
                @endif
            </div>
            <div class="body">
                <div class="nama">{{ $item->nama_item }}</div>
                <div class="harga">Rp {{ number_format($item->harga_efektif, 0, ',', '.') }}</div>
                @if($item->waktu_siap_menit)
                <div class="estimasi">⏳ ~{{ $item->waktu_siap_menit }} menit</div>
                @endif
                @if($item->tersedia)
                <div class="qty-slot">
                    <button type="button" class="kd-btn-add" onclick="kdAddToCart({{ $item->id }}, '{{ addslashes($item->nama_item) }}', {{ $item->harga_efektif }}, this)">
                        + Tambah
                    </button>
                </div>
                @else
                <div class="habis-label">Habis</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif
@empty
<p style="padding:20px;text-align:center;color:#888">Belum ada menu tersedia.</p>
@endforelse

<div class="kd-cart-bar" id="kdCartBar">
    <div class="info">
        <span id="kdCartCount">0</span> item — <span class="total">Rp <span id="kdCartTotal">0</span></span>
    </div>
    <button type="button" onclick="kdOpenCartModal()">Lihat Keranjang</button>
</div>

{{-- Modal Keranjang --}}
<div class="kd-modal-backdrop" id="kdCartModalBackdrop">
    <div class="kd-modal">
        <button type="button" class="close-btn" onclick="kdCloseModal('kdCartModalBackdrop')">&times;</button>
        <h3>Keranjang</h3>
        <div id="kdCartRows"></div>
        <button type="button" class="kd-btn-primary" style="margin-top:12px" onclick="kdOpenCheckoutModal()">Checkout</button>
    </div>
</div>

{{-- Modal Checkout --}}
<div class="kd-modal-backdrop" id="kdCheckoutModalBackdrop">
    <div class="kd-modal">
        <button type="button" class="close-btn" onclick="kdCloseModal('kdCheckoutModalBackdrop')">&times;</button>
        <h3>Checkout</h3>

        <div id="kdCheckoutSummary" style="margin-bottom:12px;font-size:.85rem;color:#555"></div>

        <div class="kd-form-group">
            <label>Nama (opsional)</label>
            <input type="text" id="kdCustomerName" placeholder="Nama Anda">
        </div>
        <div class="kd-form-group">
            <label>No HP (opsional)</label>
            <input type="text" id="kdCustomerPhone" placeholder="08xxxxxxxxxx">
        </div>
        <div class="kd-form-group">
            <label>Catatan (opsional)</label>
            <textarea id="kdCatatanUmum" placeholder="Catatan tambahan untuk pesanan"></textarea>
        </div>

        <div class="kd-form-group">
            <label>Metode Pembayaran</label>
            <label class="kd-payment-option">
                <input type="radio" name="paymentMode" value="open_bill" checked>
                Bayar di Kasir
            </label>
            <label class="kd-payment-option disabled">
                <input type="radio" name="paymentMode" value="bayar_dulu" disabled>
                Bayar Dulu (QRIS) — segera hadir
            </label>
        </div>

        <button type="button" class="kd-btn-primary" id="kdSubmitBtn" onclick="kdSubmitOrder()">Kirim Pesanan</button>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var MEJA_ID = {{ $meja->id }};
    var TOKEN = @json($token);
    var SUBMIT_URL = @json(route('order.submit'));

    var cart = {}; // { itemId: { nama, harga, qty } }

    window.kdSwitchTab = function (kategoriId, btn) {
        document.querySelectorAll('.kd-tab-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        document.querySelectorAll('.kd-kategori-section').forEach(function (sec) {
            sec.style.display = (sec.dataset.kategori == kategoriId) ? '' : 'none';
        });
    };

    window.kdAddToCart = function (itemId, nama, harga, btnEl) {
        if (!cart[itemId]) {
            cart[itemId] = { nama: nama, harga: harga, qty: 0 };
        }
        cart[itemId].qty += 1;
        kdRenderQtyPicker(itemId, btnEl.closest('.qty-slot'));
        kdUpdateCartBar();
    };

    function kdRenderQtyPicker(itemId, slotEl) {
        var qty = cart[itemId].qty;
        if (qty <= 0) {
            slotEl.innerHTML = '<button type="button" class="kd-btn-add" onclick="kdAddToCart(' + itemId + ', ' + JSON.stringify(cart[itemId].nama) + ', ' + cart[itemId].harga + ', this)">+ Tambah</button>';
            delete cart[itemId];
            return;
        }
        slotEl.innerHTML = '<div class="kd-qty-picker">' +
            '<button type="button" onclick="kdChangeQty(' + itemId + ', -1, this)">-</button>' +
            '<span>' + qty + '</span>' +
            '<button type="button" onclick="kdChangeQty(' + itemId + ', 1, this)">+</button>' +
            '</div>';
    }

    window.kdChangeQty = function (itemId, delta, btnEl) {
        var slotEl = btnEl.closest('.qty-slot');
        if (!cart[itemId]) return;
        cart[itemId].qty += delta;
        kdRenderQtyPicker(itemId, slotEl);
        kdUpdateCartBar();
    };

    function kdUpdateCartBar() {
        var count = 0, total = 0;
        Object.keys(cart).forEach(function (id) {
            count += cart[id].qty;
            total += cart[id].qty * cart[id].harga;
        });
        var bar = document.getElementById('kdCartBar');
        document.getElementById('kdCartCount').textContent = count;
        document.getElementById('kdCartTotal').textContent = kdFormatRupiah(total);
        if (count > 0) { bar.classList.add('show'); } else { bar.classList.remove('show'); }
    }

    function kdFormatRupiah(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    window.kdOpenCartModal = function () {
        var rows = document.getElementById('kdCartRows');
        rows.innerHTML = '';
        Object.keys(cart).forEach(function (id) {
            var row = cart[id];
            var div = document.createElement('div');
            div.className = 'kd-cart-row';
            div.innerHTML = '<div class="nama">' + row.nama + '<div class="harga">' + row.qty + ' x Rp ' + kdFormatRupiah(row.harga) + '</div></div>' +
                '<div>Rp ' + kdFormatRupiah(row.qty * row.harga) + '</div>';
            rows.appendChild(div);
        });
        document.getElementById('kdCartModalBackdrop').classList.add('show');
    };

    window.kdCloseModal = function (id) {
        document.getElementById(id).classList.remove('show');
    };

    window.kdOpenCheckoutModal = function () {
        kdCloseModal('kdCartModalBackdrop');
        var count = 0, total = 0;
        Object.keys(cart).forEach(function (id) { count += cart[id].qty; total += cart[id].qty * cart[id].harga; });
        document.getElementById('kdCheckoutSummary').textContent = count + ' item — Total Rp ' + kdFormatRupiah(total);
        document.getElementById('kdCheckoutModalBackdrop').classList.add('show');
    };

    window.kdSubmitOrder = function () {
        var cartArr = Object.keys(cart).map(function (id) {
            return { item_id: parseInt(id, 10), qty: cart[id].qty };
        });

        if (cartArr.length === 0) {
            alert('Keranjang masih kosong.');
            return;
        }

        var btn = document.getElementById('kdSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Mengirim...';

        fetch(SUBMIT_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token()),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                meja_id: MEJA_ID,
                token: TOKEN,
                cart: cartArr,
                customer_name: document.getElementById('kdCustomerName').value || null,
                customer_phone: document.getElementById('kdCustomerPhone').value || null,
                catatan_umum: document.getElementById('kdCatatanUmum').value || null,
                payment_mode: document.querySelector('input[name="paymentMode"]:checked').value,
            }),
        })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
            if (!result.ok) {
                alert(result.data.message || 'Gagal mengirim pesanan.');
                btn.disabled = false;
                btn.textContent = 'Kirim Pesanan';
                return;
            }
            window.location.href = '/order/status/' + result.data.queue_id;
        })
        .catch(function () {
            alert('Gagal terhubung ke server. Coba lagi.');
            btn.disabled = false;
            btn.textContent = 'Kirim Pesanan';
        });
    };
})();
</script>
@endpush

@endsection
