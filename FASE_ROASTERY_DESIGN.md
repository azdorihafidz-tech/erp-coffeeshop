# FASE ROASTERY — Roastery Batch Management

> Design blueprint. **Belum ada implementasi kode** — dokumen ini dibuat untuk disetujui Owner dulu. Aturan kerja: `CLAUDE.md` 3.3 (reuse), 3.7 (verifikasi), 3.8 (kelengkapan menu).

**Tanggal dibuat**: 2026-09-24
**Status**: ✅ Disetujui Owner & diimplementasi R1–R5 (2026-09-24). Penyimpangan dari desain: `roasting_packagings` diganti `roasting_batch_packs`; waste = green − roasted (sesuai spesifikasi Owner); tanpa kolom `items.varietas/roast_level` (varietas/level dikodekan di `kode_item`); biaya operasional ditunda ke R6. Detail: CLAUDE.md 4.20.

---

## RINGKASAN

- **Nama fitur**: Roastery Batch Management
- **Owner**: Kopi Drip Sidikalang — lokasi roastery = Gudang Pusat `GP001` (rumah Owner)
- **Estimasi**: 6–7 hari kerja (R1–R5)
- **Konteks bisnis**: Green Bean (mentah) di-roast → Roasted Bean, dengan susut berat & waste. Roasted bean dijual retail (RTB) dan dipakai sebagai bahan menu kopi di outlet.

## KONDISI DATA SAAT INI (hasil cek DB 2026-09-24)

| Item | Kode | Satuan | Harga beli terakhir | Stok GP001 | Stok tiap outlet |
|------|------|--------|---------------------|-----------|------------------|
| Green Bean Arabika Sidikalang | GRB-001 | kg | 90.000 | 50 | 0 |
| Green Bean Robusta | GRB-002 | kg | 60.000 | 30 | 0 |
| Roasted Bean Sidikalang 250g | RTB-001 | pack | 45.000 | 20 | 5 |
| Roasted Bean Sidikalang 500g | RTB-002 | pack | 85.000 | 15 | 3 |
| Roasted Bean Sidikalang 1kg | RTB-003 | pack | 160.000 | 10 | 2 |

Semua GRB bertipe `bahan_baku`; semua RTB bertipe `produk_jual` (satuan **pack**, bukan kg). `ResepBumbuSeeder` masih kosong → menu kopi belum memotong bahan (TODO 12.4). Struktur stok/FIFO yang bisa di-reuse: `stocks`, `stock_batches` (FIFO + `harga_beli_per_unit`), `StokService::keluar()/masuk()`, `stock_transfers`, `resep_bumbu*`.

### ⚠️ Temuan desain penting
Tidak ada item **"Roasted Bean curah (kg)"**. RTB yang ada hanya kemasan pack. Padahal menu kopi butuh gram biji per gelas. Maka perlu item baru bahan baku roasted curah (mis. `RBK-001 Roasted Bean Arabika (kg)`) sebagai output batch; pengemasan ke RTB pack = langkah terpisah (lihat Flow D).

---

## FITUR UTAMA

1. Master Roasting Profile (Light/Medium/Dark, susut % rata-rata)
2. Batch Roasting Session (input green kg + profile → output roasted kg + waste kg)
3. Auto-adjust stok (green berkurang, roasted bertambah, waste tercatat)
4. Riwayat & Analytics (yield %, waste %, cost per kg roasted)
5. Distribusi roasted ke outlet (via Stock Transfer existing)
6. Integrasi Resep Bumbu untuk menu kopi (potong roasted bean saat jual)
7. Pengemasan ke RTB pack (roasted curah → RTB 250g/500g/1kg)

## FLOW UTAMA

### Flow A — Batch Roasting
1. Buka `/roastery/batch/new` (lokasi otomatis GP001).
2. Pilih green bean + qty (mis. Arabika 5 kg), pilih profile → auto-isi estimasi susut.
3. Input actual: roasted 4,2 kg, waste 0,3 kg (sisa = susut moisture, dihitung otomatis).
4. Submit dalam **satu `DB::transaction`**:
   - `StokService::keluar(green)` → FIFO menghasilkan **cost_green_total** (reuse, bukan hitung ulang).
   - `StokService::masuk(roasted curah)` dengan `harga_beli_per_unit = cost_green_total / roasted_qty` (+ biaya tambahan opsional) → batch FIFO baru, sehingga HPP kopi otomatis benar.
   - Simpan `roasting_batches` (yield %, waste %, cost/kg).
5. Validasi: green ≤ stok GP001; roasted + waste ≤ green.

### Flow B — Distribusi ke Outlet
Pakai modul **Stock Transfer** existing (roasted curah / RTB pack GP001 → outlet). Tidak ada kode baru selain memastikan item roasted muncul di daftar transfer.

### Flow C — Potong stok saat jual
Sudah ada di `PenjualanService::potongStokUntukItem()` via resep. Prasyarat: `resep_bumbu` menu kopi diisi (butuh takaran Owner, TODO 12.4) dengan bahan = roasted curah (gram → kg).

### Flow D — Pengemasan
Form kecil: kurangi roasted curah X kg, tambah RTB-001/002/003 sejumlah pack (250 g = 0,25 kg dst). Cost RTB = cost/kg × berat + biaya kemasan. Bisa satu form di `/roastery/pengemasan`.

---

## ARSITEKTUR DB

### Tabel baru
1. **`roasting_profiles`**: id, nama (Light/Medium/Dark), level (enum light/medium/dark), estimasi_susut_persen, catatan, is_active, timestamps, soft delete.
2. **`roasting_batches`**: id, nomor_batch (unik, `RB-YYYY-NNNN`), tanggal, cabang_id (=GP001), green_item_id, green_qty, profile_id, roasted_item_id, roasted_qty, waste_qty, yield_persen, cost_green_total, cost_per_kg_roasted, is_test (bool), catatan, user_id, timestamps, soft delete. Index (cabang_id, tanggal).
3. **`roasting_packagings`** (Flow D): id, tanggal, cabang_id, roasted_item_id, roasted_kg_dipakai, rtb_item_id, pack_qty, cost_per_pack, user_id.

`roasting_batch_items` **tidak dibuat** (1 batch = 1 varietas = 1 output; blend = beberapa batch berurutan).

### Kolom tambahan
- `items.varietas` (string nullable), `items.roast_level` (enum light/medium/dark nullable).

### Item master baru (seeder)
- `RBK-001 Roasted Bean Arabika (curah, kg)`, `RBK-002 Roasted Bean Robusta (curah, kg)` — tipe `bahan_baku`, satuan kg, kategori RTB/BHN (keputusan Owner).

### Reuse (jangan bangun ulang)
`StokService` (FIFO), `stock_batches`, `stock_transfers`, `resep_bumbu`, `HasAuditLog`, pola controller/permission/panduan warisan.

## UI/UX — Menu "Roastery" (sidebar, hanya GP001/owner/admin_pusat)

- `/roastery/dashboard` — ringkasan batch bulan ini, yield rata-rata
- `/roastery/profile` — master profile
- `/roastery/batch` & `/roastery/batch/new` — daftar & form batch
- `/roastery/pengemasan` — Flow D
- `/roastery/analytics` — yield & waste per varietas/profile, tren cost/kg

## Aturan 3.8 (wajib tiap sub-fase)
Sidebar + `@can`, tombol Cara Pakai, panduan (`roastery-*`), tooltip (`roastery_batch.*`), permission `roastery.profile.*`, `roastery.batch.view/create/void`, `roastery.analytics.view`, `roastery.pengemasan.create`; mapping: owner (bypass), admin_pusat & admin_gudang full, manajer_cabang tidak ada, reseed 4 seeder, verifikasi visual. Filter `cabang_id` manual di semua query (3.4).

## RENCANA PENGERJAAN

| Sub-fase | Isi | Estimasi |
|----------|-----|----------|
| R1 | Migration + master Roasting Profile + item roasted curah + permission/panduan | 1 hari |
| R2 | Form Batch + auto-stok (transaksi atomik) + cost FIFO | 2 hari |
| R3 | Dashboard + Analytics | 1–2 hari |
| R4 | Pengemasan ke RTB + integrasi Resep Bumbu menu kopi (butuh takaran Owner) | 1 hari |
| R5 | Testing E2E (stok, cost, void, edge case) + update CLAUDE.md | 1 hari |

## FAQ / EDGE CASE
- **Batch gagal (gosong)** → roasted_qty = 0, waste = green_qty; stok green tetap berkurang, cost tercatat sebagai kerugian.
- **Roasting eksperimen** → flag `is_test`; stok tetap bergerak, tapi dikecualikan dari analytics.
- **Campur varietas** → 1 batch = 1 varietas; blend = beberapa batch.
- **Salah input batch** → fitur *void* (soft delete + reversal stok/batch FIFO dalam transaksi), bukan edit angka langsung.
- **Roasted + waste > green** → ditolak validasi; selisih green − (roasted+waste) = susut moisture wajar.
- **Stok green kurang** → ditolak, sama dengan pola `StokService::keluar()`.

## KEPUTUSAN YANG DIBUTUHKAN DARI OWNER
1. Setuju menambah item **roasted curah (kg)** sebagai output batch + langkah pengemasan ke RTB pack?
2. Siapa yang menginput batch (Owner / Admin Gudang / operator roastery)?
3. Susut tipikal per profile (mis. Light ~12%, Medium ~15%, Dark ~18%) — pakai angka ini sebagai default?
4. Biaya tambahan per batch (gas/listrik/tenaga kerja) ikut masuk cost/kg atau diabaikan dulu?
5. Takaran gram biji per menu kopi (prasyarat R4, TODO 12.4).
