@php $p = $profile ?? null; @endphp
<div class="row g-3">
    <div class="col-12 col-md-6">
        <label class="form-label small fw-semibold">Nama Profile *</label>
        <input type="text" name="nama" class="form-control @error('nama') is-invalid @enderror" value="{{ old('nama', $p->nama ?? '') }}" required maxlength="50">
        @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label small fw-semibold">Level Target *</label>
        <select name="level_target" class="form-select">
            @foreach(['light' => 'Light', 'medium' => 'Medium', 'dark' => 'Dark', 'custom' => 'Custom'] as $v => $l)
            <option value="{{ $v }}" @selected(old('level_target', $p->level_target ?? 'custom') === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold">Susut Rata-rata (%) * <x-tooltip key="roastery_profile.susut_percent" /></label>
        <input type="number" step="0.01" min="0" max="60" name="avg_susut_percent" class="form-control @error('avg_susut_percent') is-invalid @enderror" value="{{ old('avg_susut_percent', $p->avg_susut_percent ?? 15) }}" required>
        @error('avg_susut_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-4">
        <label class="form-label small fw-semibold">Suhu Target (°C)</label>
        <input type="number" name="suhu_target_celsius" class="form-control" value="{{ old('suhu_target_celsius', $p->suhu_target_celsius ?? '') }}">
    </div>
    <div class="col-6 col-md-4">
        <label class="form-label small fw-semibold">Waktu Target (menit)</label>
        <input type="number" name="waktu_target_menit" class="form-control" value="{{ old('waktu_target_menit', $p->waktu_target_menit ?? '') }}">
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold">Catatan</label>
        <textarea name="catatan" rows="2" class="form-control" maxlength="500">{{ old('catatan', $p->catatan ?? '') }}</textarea>
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="pfActive" @checked(old('is_active', $p->is_active ?? true))>
            <label class="form-check-label" for="pfActive">Aktif (bisa dipilih di form batch)</label>
        </div>
    </div>
</div>
