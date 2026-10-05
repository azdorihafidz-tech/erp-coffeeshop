@props(['unitBeli' => null, 'isiPerUnitBeli' => null])

{{-- Sprint Unit Family (2026-10-02, porting dari erp-dimsum) — section reusable utk 2 form Master.
     Sprint Fix UX (2026-10-05) — alert error di top, live warning kuning partial fill, pesan error lebih jelas. --}}
<div class="card mb-3 unit-beli-section">
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <span>Unit Beli <span class="text-muted small">(Opsional)</span></span>
    </div>
    <div class="card-body">
        @if($errors->has('unit_beli') || $errors->has('isi_per_unit_beli'))
            <div class="alert alert-danger small py-2 mb-3 fw-semibold">
                <i class="bi bi-exclamation-octagon-fill me-1"></i>
                Unit Beli TIDAK TERSIMPAN. Pastikan <strong>Unit Beli</strong> DAN <strong>Isi per Unit Beli</strong> keduanya terisi dengan angka &gt; 0, atau kosongkan keduanya kalau tidak dipakai.
            </div>
        @endif

        <div class="alert alert-info small py-2 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Isi kalau kamu membeli bahan ini dalam <strong>pack/karung/dus</strong> (bukan satuan pakai).
            Contoh: cup dibeli per <em>pack</em>, 1 pack isi 100 pcs.
            Nanti di form Pembelian, kamu bisa langsung input <em>10 pack</em> dan sistem otomatis hitung jadi <em>1000 pcs</em>.
            <br><strong>Catatan:</strong> kedua field harus diisi bersamaan — atau kosongkan keduanya kalau tidak pakai Unit Beli.
        </div>

        <datalist id="unitBeliSuggestions">
            <option value="pack">
            <option value="karung">
            <option value="dus">
            <option value="box">
            <option value="karton">
            <option value="plastik">
            <option value="lusin">
            <option value="gross">
        </datalist>

        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold">Unit Beli</label>
                <input type="text" name="unit_beli" list="unitBeliSuggestions"
                    class="form-control unit-beli-input @error('unit_beli') is-invalid @enderror"
                    value="{{ old('unit_beli', $unitBeli) }}"
                    placeholder="cth: pack, karung, dus" maxlength="20">
                @error('unit_beli')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold">Isi per Unit Beli</label>
                <input type="number" name="isi_per_unit_beli"
                    class="form-control isi-per-unit-input @error('isi_per_unit_beli') is-invalid @enderror"
                    min="0.001" step="0.001"
                    value="{{ old('isi_per_unit_beli', $isiPerUnitBeli) }}"
                    placeholder="cth: 100">
                @error('isi_per_unit_beli')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end">
                <div class="preview-unit-beli text-muted small" style="min-height:38px">
                    <span class="preview-empty">Preview: <em>belum diisi</em></span>
                </div>
            </div>
        </div>

        {{-- Sprint Fix UX (2026-10-05): warning live JS kalau partial fill, bukan hanya saat submit --}}
        <div class="live-warning-unit-beli alert alert-warning small py-2 mb-0 mt-2 d-none">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <span class="live-warning-text"></span>
        </div>
    </div>
</div>

@once
<script>
(function () {
    function bind(section) {
        var unit = section.querySelector('.unit-beli-input');
        var isi = section.querySelector('.isi-per-unit-input');
        var satuan = document.querySelector('input[name="satuan"]');
        var preview = section.querySelector('.preview-unit-beli');
        var warning = section.querySelector('.live-warning-unit-beli');
        var warningText = section.querySelector('.live-warning-text');
        if (!unit || !isi || !preview) return;

        function refresh() {
            var u = (unit.value || '').trim();
            var iRaw = isi.value;
            var i = parseFloat(iRaw);
            var s = ((satuan && satuan.value) || 'unit pakai').trim();
            var partialUnitOnly = u && (!iRaw || isNaN(i) || i <= 0);
            var partialIsiOnly  = !u && iRaw !== '' && !isNaN(i) && i > 0;

            if (!u && (!iRaw || iRaw === '')) {
                preview.innerHTML = '<span class="preview-empty">Preview: <em>belum diisi</em></span>';
            } else if (partialUnitOnly || partialIsiOnly || !u || !i || i <= 0) {
                preview.innerHTML = '<span class="text-danger">Kedua field harus diisi</span>';
            } else {
                var num = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(i);
                preview.innerHTML = '<strong>1 ' + u + ' = ' + num + ' ' + s + '</strong>';
            }

            // Live warning kalau partial fill
            if (warning && warningText) {
                if (partialUnitOnly) {
                    warningText.innerHTML = 'Isi per Unit Beli masih kosong atau 0. Form <strong>tidak akan tersimpan</strong> — tambahkan angka &gt; 0 (misal 100 kalau 1 pack = 100 pcs).';
                    warning.classList.remove('d-none');
                } else if (partialIsiOnly) {
                    warningText.innerHTML = 'Unit Beli masih kosong. Form <strong>tidak akan tersimpan</strong> — isi nama unit (misal pack, karton, dus).';
                    warning.classList.remove('d-none');
                } else {
                    warning.classList.add('d-none');
                }
            }
        }

        unit.addEventListener('input', refresh);
        isi.addEventListener('input', refresh);
        if (satuan) satuan.addEventListener('input', refresh);
        refresh();
    }

    document.querySelectorAll('.unit-beli-section').forEach(bind);
})();
</script>
@endonce
