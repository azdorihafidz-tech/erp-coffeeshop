# CLAUDE.md — ERP Kopi Drip

> **Untuk Claude Code**: File ini adalah **single source of truth** untuk seluruh project. WAJIB dibaca sebelum eksekusi apapun.
> Isinya: keputusan bisnis, aturan teknis, riwayat pengerjaan, dan filosofi kerja.

**Versi**: 1.0
**Update terakhir**: 2026-09-24
**Status**: 🚧 **DALAM PENGEMBANGAN** — Fase A, B, D, Audit 2, E1–E6 (QR Table Ordering: Master Meja, Public Menu, Kasir Approve Queue+Layout Meja, Bill Unification, Kitchen Display, Timer+Notifikasi+Estimasi) SELESAI. Fase C (dokumentasi, file ini) sedang berjalan. E7 (testing interaktif browser) MENUNGGU instruksi Owner. Audit sinkronisasi stok & Audit Retroaktif 3.8 MENYUSUL. Lihat section 4 untuk riwayat lengkap.

---

## 1. GAMBARAN UMUM PROJECT

### 1.1 Identitas Brand
- **Nama Brand**: **Kopi Drip Sidikalang**
- **Tagline**: **Coffee Shop • Roastery • Eatery**
- **Jenis Bisnis**: Coffee shop dengan roastery biji kopi sendiri (proses green bean → roasted bean in-house) + eatery (menu makanan lengkap, bukan cuma snack pendamping kopi)
- **Sistem**: ERP (Enterprise Resource Planning) — POS + inventory + HR + keuangan + roastery

### 1.2 Asal Project
- **Base**: hasil copy penuh dari `erp-dimsum` (ERP D'mentai, Laravel 12) — **BUKAN** langsung dari `erp-manajemenberkahmulyo`
- **Tanggal fork**: September 2026
- **Alasan fork dari Dimsum (bukan langsung dari Berkah Mulyo)**: POS `erp-dimsum` sudah punya grid produk dengan gambar (1-klik ke keranjang), tipe transaksi Dine-in/Takeaway/Frozen, dan struktur Varian N-dimensi — semuanya jauh lebih dekat ke kebutuhan coffee shop (grid menu visual, varian size/susu/gula) dibanding UI `erp-manajemenberkahmulyo` asli yang masih text-based list (didesain untuk jasa giling daging, bukan retail walk-in). Fork dari Dimsum menghemat waktu bangun ulang UI POS dari nol.
- **Konsekuensi warisan**: karena base-nya Dimsum (yang sendiri fork dari Berkah Mulyo), project ini mewarisi 2 lapis riwayat — sebagian nama tabel/kolom/komentar kode masih menyebut konteks lama (jasa giling → dimsum → kopi). Lihat `CLAUDE.dimsum.reference.md` untuk detail riwayat penuh Dimsum (arsip referensi, tidak lagi jadi sumber kebenaran project ini).

### 1.3 Struktur Bisnis Saat Ini
- **1 Gudang Pusat** (merangkap **Roastery** — tempat proses green bean jadi roasted bean, di rumah owner)
- **5 Outlet retail** (coffee shop + eatery)
- **Total 6 lokasi** di sistem

### 1.4 Prinsip Fleksibilitas (FILOSOFI UTAMA!)
Meski saat ini ada 5 outlet, sistem **HARUS fleksibel** untuk:
- Jumlah outlet bisa nambah/berkurang tanpa ganti kode
- Lokasi outlet: 1 kota / lintas kota / lintas provinsi
- Harga per outlet: bisa sama semua atau beda-beda (config-driven)
- Jenis menu per outlet: bisa beda (config per outlet) — misal outlet kecil tidak jual eatery lengkap
- Varian menu: N-dimensi, opsional per item (size, susu, gula, es, extra shot)
- Stok varian: bisa terpisah atau ikut induk (config per item)

**Aturan emas: kalau bisa jadi config, JANGAN hardcode.**

---

## 2. TECH STACK & LINGKUNGAN

### 2.1 Stack
Sama persis dengan `erp-dimsum` (warisan langsung, tidak ada perubahan stack):

| Item | Detail |
|------|--------|
| Framework | Laravel 12 (bootstrap/app.php style, no Kernel.php) |
| PHP | 8.2+ |
| Database | MySQL (via XAMPP) |
| Frontend | Bootstrap 5.3.3 (CDN) + Select2 + SweetAlert2 + Chart.js + Bootstrap Icons + jQuery |
| Permission | 100% custom (BUKAN spatie/laravel-permission) |
| PDF | barryvdh/laravel-dompdf ^3.1 |
| Excel | maatwebsite/excel ^3.1 |
| Activity Log | spatie/laravel-activitylog ^4.12 |
| Backup | spatie/laravel-backup ^9.3 |
| PWA | silviolleite/laravelpwa ^2.0 |
| Realtime | pusher/pusher-php-server ^7.2 |

### 2.2 Lingkungan Lokal
| Item | Detail |
|------|--------|
| Path folder | `D:\xampp\htdocs\erp-coffeeshop` |
| Nama database | `erp_coffeeshop` |
| Port dev | `php artisan serve --port=8002` |
| URL akses | `http://localhost:8002` |

### 2.3 Project Lain di Environment (JANGAN DISENTUH)
| Project | Folder | Database | Port | Status |
|---------|--------|----------|------|--------|
| erp-manajemenberkahmulyo (ASLI) | `erp-manajemenberkahmulyo` | `erp_berkahmulyo` | 8000 | 🔴 Production BM, HARAM disentuh |
| erp-dimsum (ASAL FORK) | `erp-dimsum` | `erp_dimsum` | 8001 | 🔴 Production Sinsam Dimsum, HARAM disentuh |
| erp-coffeeshop (INI) | `erp-coffeeshop` | `erp_coffeeshop` | 8002 | 🟢 Aktif dikerjakan |

---

## 3. ATURAN KERJA WAJIB (JANGAN DILANGGAR)

### 🔴 3.1 JANGAN pernah sentuh folder ini:
- `D:\xampp\htdocs\erp-manajemenberkahmulyo` — project produksi Berkah Mulyo
- `D:\xampp\htdocs\erp-dimsum` — project produksi Sinsam Dimsum (D'mentai)
- Database `erp_berkahmulyo` — data real Berkah Mulyo
- Database `erp_dimsum` — data real Sinsam Dimsum

Semua modifikasi HANYA di folder `erp-coffeeshop` dan database `erp_coffeeshop`.

### 🔴 3.2 Test SETIAP perubahan
Setelah edit kode, WAJIB:
1. Cek error di `storage/logs/laravel.log`
2. Jalankan `php artisan config:clear` dan `php artisan view:clear`
3. Coba akses menu terkait di browser
4. Kalau ada error, FIX DULU sebelum lanjut fitur lain

### 🔴 3.3 Reuse dulu, bikin baru terakhir
Sebelum bikin controller/model/view baru, **cek dulu** apakah pola serupa sudah ada (warisan dari Dimsum/Berkah Mulyo):
- Cek folder: `app/Http/Controllers`, `app/Models`, `resources/views`
- Kalau ada pola serupa, IKUTI pola itu (naming, struktur folder, style code)
- Jangan bikin pola baru kalau tidak perlu

### 🔴 3.4 Filter Cabang WAJIB Manual (SECURITY-CRITICAL!)
**Warisan temuan audit Dimsum**: `CabangScope` DORMANT (tidak pernah aktif). Pemanggilan `withoutGlobalScope(CabangScope::class)` di codebase adalah no-op — efek nyatanya cuma matiin `SoftDeletingScope`.

**Konsekuensi**: Filter cabang HARUS ditulis manual di setiap query.

**Contoh yang BENAR**:
```php
$orders = Order::where('cabang_id', auth()->user()->cabang_aktif_id)->get();
```

**Contoh yang SALAH** (akan bocor data cabang lain):
```php
$orders = Order::all(); // SECURITY BUG — lihat semua cabang!
```

**Aturan**: Setiap query yang menyentuh data cabang, cek dulu apakah sudah ada filter `cabang_id`. Ini security-critical.

### 🔴 3.5 Migration Harus Urut & Bisa Rollback
- Nama file migration harus datetime yang benar (bukan asal timestamp)
- Setiap `up()` HARUS ada `down()` yang balikin state
- Cek foreign key: tabel yang dirujuk harus dibuat DULU

### 🔴 3.6 Permission + Panduan + Tooltip + Tombol Cara Pakai Wajib Dibuat
Setiap fitur/menu BARU wajib:
1. Tambah entry di `PermissionSeeder` (untuk hak akses)
2. Tambah entry di `RolePermissionSeeder` (mapping role ke permission)
3. Tambah panduan di menu Panduan (via `PanduanKontenSeeder`)
4. Tambah tooltip untuk form-form penting (via `TooltipKontenSeeder`)
5. **Embed tombol "Cara Pakai" (`<x-panduan-button slug="{slug}" />`) di halaman fitur** — pojok kanan atas header halaman.

Ini WAJIB, bukan optional. Kalau lupa, kerjaan bakal dobel di akhir (pelajaran dari Dimsum: poin 3-5 silently missing kalau lupa, tidak menghasilkan error yang cepat ketahuan seperti poin 1-2).

### 🔴 3.7 Verifikasi Wajib Setelah Tambah/Update
Setiap kali menambah atau mengubah kode/data/config, WAJIB jalankan urutan verifikasi ini:

1. **Syntax check** — pastikan file yang diedit bisa di-parse:
   - PHP: `php -l path/to/file.php` (harus return "No syntax errors detected")
   - Blade: coba akses view via route atau `php artisan view:cache` (kalau error, syntax salah)
   - JSON: `php -r "json_decode(file_get_contents('path'), true); echo json_last_error_msg();"`
   - .env / config: buka & baca ulang, pastikan format `KEY=VALUE` benar

2. **Clear cache** setelah edit:
```
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```
   (Route cache tidak perlu di-clear kecuali edit routes/*.php)

3. **Cek error log** setelah cache clear, buka file `storage/logs/laravel.log` bagian paling akhir. Cari kata `ERROR`, `Exception`, `Fatal`, `Undefined`. Kalau ada entry baru dari waktu edit, harus difix dulu.

4. **Cek route terkait** — kalau edit controller/route/blade:
```
php artisan route:list --path=<route-yang-relevan>
```
   Pastikan route masih terdaftar. Akses via curl atau browser:
```
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://127.0.0.1:8002/<path>
```
   Kode 200 (halaman OK) atau 302 (redirect login) = normal. Kode 500 = error, harus difix.

5. **Cek fitur yang dipengaruhi** — smoke test manual:
   - Edit seeder? Jalankan `db:seed --class=NamaSeeder` yang khusus
   - Edit model? Cek relasi via `php artisan tinker` (`Item::first()->cabangs`)
   - Edit view? Buka halaman terkait di browser
   - Edit config? Restart `php artisan serve`

6. **Laporkan hasil verifikasi lengkap ke user** — jangan cuma bilang "selesai". Format laporan minimal:
```
✅ Syntax check: OK (php -l pass)
✅ Cache clear: OK (config/view/cache cleared)
✅ Error log: bersih, tidak ada entry ERROR baru
✅ Route check: /path return HTTP 200
✅ Smoke test: <fitur X> berfungsi normal
```

**Aturan STOP-FIX-LANJUT:**
- Kalau salah satu step 1-5 gagal → **STOP** eksekusi task berikutnya
- Fix error dulu sampai step yang gagal itu pass
- Baru lanjut ke task berikutnya
- Jangan sembunyikan warning/error dari user — laporkan apa adanya

**Konvensi laporan ringkas:**
- Kalau task kecil (1-2 file diedit): cukup laporan checklist di atas
- Kalau task besar (10+ file): tampilkan ringkasan angka (misal "12 file diedit, semua syntax OK, 0 error log baru")

### 🔴 3.8 Aturan Menu Baru — Kelengkapan Wajib

Setiap kali membuat fitur/menu baru, WAJIB include SEMUA checklist berikut sebelum menandai fitur "selesai":

1. **Menu Sidebar** — item menu ditambah di layout sidebar dengan icon yang cocok
2. **Permission Gate di Menu** — pakai `@can('permission-name')` supaya menu hanya muncul untuk role yang punya akses
3. **Tombol "Cara Pakai"** — di header halaman utama, buka modal panduan berdasarkan slug (`<x-panduan-button slug="..." />`)
4. **Konten Panduan** — entry baru di `PanduanKontenSeeder.php` dengan slug matching, isi jelas & terstruktur (## Tentang / Cara Pakai / Peringatan)
5. **Tooltip Field Kompleks** — entry di `TooltipKontenSeeder.php` untuk field yang tidak self-explanatory
6. **Permission untuk Semua Aksi** — CRUD split (view/create/edit/delete) + aksi khusus (misal print, export, approve, dll), tambah di `PermissionSeeder.php`
7. **Role-Permission Mapping** — set default role mana punya permission apa (owner/admin_pusat full, manajer view+edit, kasir view-only, dst), tambah di `RolePermissionSeeder.php`
8. **Reseed konten setelah tambah** — jalankan `db:seed --class=PanduanKontenSeeder`, `db:seed --class=TooltipKontenSeeder`, `db:seed --class=PermissionSeeder`, `db:seed --class=RolePermissionSeeder` (idempotent)
9. **Verifikasi visual di browser** — login sebagai owner, sidebar muncul menu baru, tombol panduan berfungsi, tooltip muncul saat hover

**Aturan STOP-INCOMPLETE**: Kalau salah satu dari 9 item di atas tidak dikerjakan, fitur BELUM boleh ditandai "selesai" di TODO CLAUDE.md.

**Catatan naming convention** (ditemukan saat fix gap E1, 2026-09-23): slug panduan & key tooltip di project ini pakai **kebab-case/underscore tanpa prefix "master."** (`produk-jual`, `master_produk_jual.resep` — BUKAN `master.produk-jual`/`master.meja.field`). Selalu cek pola existing di `PanduanKontenSeeder.php`/`TooltipKontenSeeder.php` sebelum menambah slug/key baru — jangan asumsi sendiri formatnya.

**Catatan sistem permission** (CLAUDE.md 2.1): project ini **100% custom permission**, BUKAN Spatie. Role tersimpan langsung di kolom `users.role` (enum `RoleUser`), owner-bypass via `Gate::before()` di `app/Providers/AppServiceProvider.php`. Tabel `roles`/`model_has_permissions`/`model_has_roles` (skema Spatie) **TIDAK ADA** di database ini — jangan query tabel itu untuk debug permission.

---

## 4. RIWAYAT PENGERJAAN

### 4.1 🟢 Fase A — Bersih-bersih Sisa Dimsum (Selesai 2026-09-22)

Rebranding teknis dari warisan `erp-dimsum` ke Kopi Drip, tanpa mengubah data master (itu jatah Fase B):
- `.env.example`: `APP_NAME="ERP Kopi Drip"`, `APP_URL=http://localhost:8002`
- `config/laravelpwa.php`: nama, manifest, warna PWA (`#1B4332`/`#2D6A4F`)
- `resources/logo-source/Logo_KopiDrip.png` (ganti `Logo_Dmentai.png`) + `GeneratePwaIcons.php` default path diupdate
- 8 ukuran PWA icon (72–512px) di-generate ulang dari logo baru (ada catatan: PNG sumber sempat punya ICC profile korup yang bikin `imagecreatefrompng()` throw — sudah di-strip)
- Batch replace "D'mentai"/"ERP D'mentai"/"Dimsum & Gyoza" → "Kopi Drip"/"ERP Kopi Drip"/"Coffee Shop & Roastery" di 30+ file blade (komentar historis "Tahap X D'mentai" SENGAJA dibiarkan sebagai jejak sejarah kode, tidak diubah)
- `HRSeeder.php` & `StokSeeder.php`: `kode_cabang` lama (`CA001`/`CB001`) → `OUT001`/`OUT002`, email `@berkahmulyo.com` → `@kopidrip.com`
- 4 seeder data (JenisOlahan/ItemCategory/Item/ResepBumbu) dikosongkan sementara (diisi Fase B)
- `CabangSeeder.php`: "Gudang Pusat D'mentai" → "Kopi Drip Pusat", alamat "TBD"
- `UserSeeder.php`: 9 email → `@kopidrip.com`
- `PengaturanUmum.php`, `PengaturanUmumController.php`, `AntrianDisplayController.php`: default nama perusahaan → "Kopi Drip Sidikalang"
- `README.md`: judul, tagline, port, DB, repo URL diupdate
- 2 ronde cleanup tambahan (anomali ditemukan lewat verifikasi grep manual): placeholder email `email@dmentai.com` → `nama@kopidrip.com` di 3 form, fallback `config('app.name', "D'mentai")` di export Laporan Absensi, `.env` APP_NAME spasi

**Verifikasi**: `migrate:fresh --seed` sukses tanpa error, server `--port=8002` jalan, login `admin@kopidrip.com`/`password` berhasil.

### 4.2 🟢 Fase B — Data Master Kopi Drip (Selesai 2026-09-22)

Mengisi seeder yang dikosongkan Fase A dengan data real bisnis Kopi Drip:

- **B2 — Kategori Item** (`ItemCategorySeeder.php`): 9 kategori — Kopi (KPI), Manual Brew (MBW), Non-Kopi (NKP), Snack (SNK), Makanan (MKN), Roasted Bean (RTB), Green Bean (GRB), Bahan Baku (BHN), Kemasan (KMS)
- **B3 — Menu Item** (`ItemSeeder.php`): 44 item — 28 `produk_jual` (kopi, manual brew, non-kopi, snack, makanan, roasted bean retail) + 16 `bahan_baku` (green bean, susu, gula, sirup, kemasan). "Kopi Sidikalang" (KPI-008) sengaja diberi `punya_varian=true` sebagai item contoh fitur Varian.
- **B4 — User** (`UserSeeder.php`): 9 user dengan email `@kopidrip.com`, nama placeholder eksplisit "(TBD)" untuk yang perlu diganti Owner nanti (Manajer/Kasir/Barista Outlet 1 & 2). Dependensi `HRSeeder.php`/`StokSeeder.php` yang lookup email lama ikut disesuaikan.
- **B5 — Jenis Olahan** (`JenisOlahanSeeder.php`): 4 jenis — Racikan Kopi, Manual Brew, Roasting Batch, Kitchen. `ResepBumbuSeeder.php` **sengaja dibiarkan kosong** — perlu takaran akurat per gelas dari Owner (lihat TODO 12.3).
- **B6 — Footer Struk & Tagline**: footer struk (`struk.blade.php`, `_struk_modal_content.blade.php`) → "Terima kasih atas kunjungan Anda!\nKopi Drip Sidikalang - Coffee & Roastery". Sisa teks contoh tagline "Dimsum & Gyoza" di `PanduanKontenSeeder.php` dibersihkan.

**Catatan penting**: data B3/B5 di-seed 2x — sekali incremental (`db:seed --class=...`) untuk verifikasi cepat saat development, lalu final lewat `migrate:fresh --seed` penuh (mengikutsertakan perubahan B4 UserSeeder yang butuh fresh karena pakai `User::create()` bukan `updateOrCreate()`).

### 4.3 🚧 Fase C — Dokumentasi (Sedang Berjalan)

Menulis ulang `CLAUDE.md` ini dari template `erp-dimsum` (di-backup sebagai `CLAUDE.dimsum.reference.md`), diadaptasi penuh ke konteks Kopi Drip — bukan tulis dari nol, supaya aturan kerja & konvensi teknis yang sudah teruji (filter cabang manual, nested-form ban, dll) tidak hilang.

**Update 2026-09-22**: ditambahkan section 3.7 "Verifikasi Wajib Setelah Tambah/Update" — checklist wajib (syntax check → clear cache → cek error log → cek route → smoke test → lapor ke user) yang harus dijalankan setiap kali ada perubahan kode/data/config, plus aturan STOP-FIX-LANJUT kalau ada step yang gagal.

### 4.4 🟢 Fase D — Rebranding Warna CSS View (Selesai 2026-09-22)

Ganti skema warna hardcode di 12 file view (warisan Dimsum: hitam `#1A1A1A` + oranye `#FF6B00` + krim `#FFF8E7`) ke palette Kopi Drip pertama kali (hijau tua `#1B4332` + accent `#2D6A4F` + cream `#F5F0E6`) — 64 occurrence direplace di `layouts/app.blade.php`, `layouts/guest.blade.php`, error pages (403/404/500), `penjualan/pos.blade.php`, `vendor/laravelpwa/offline.blade.php`, dan 5 template PDF laporan.

**Revisi (reversal parsial, sama hari)**: Owner memutuskan sidebar balik ke hitam `#1A1A1A` (bukan hijau tua) — kombinasi klasik "sidebar hitam + accent hijau" ala coffee shop premium. 7 file view + `config/laravelpwa.php` (`background_color`) diganti `#1B4332` → `#1A1A1A` (16 occurrence). `#2D6A4F` (accent) dan `#F5F0E6` (cream) tidak berubah. Lihat palette final di section 6.1.

### 4.5 ⏳ Audit Menyeluruh — Sinkronisasi Stok 3 Level (Pending)

Perlu dicek: (1) `item_cabang` — ketersediaan menu per outlet, (2) resep bahan baku — komposisi menu ke bahan mentah, (3) `stock_transfers` — perpindahan stok Gudang Pusat/Roastery ↔ Outlet. Lihat section 12.

### 4.6 🟢 Audit 2 — Smoke Test, Fix Branding Leak, Cleanup Dead Code (Selesai 2026-09-22)

Audit menyeluruh backend + UI pasca Fase A-D: smoke test 34+ route (0 error 500), audit sinkronisasi stok Level 1-3 (lihat 4.5), isi stok testing 264 baris (44 item × 6 cabang), dan **fix branding leak yang lolos dari Fase A**:
- **Komentar CSS/JS `Tahap X D'mentai`** di dalam `<style>`/`<script>` — ternyata TETAP terkirim ke browser (beda dari komentar Blade `{{-- --}}` yang di-strip saat compile) — 2 CSS comment + 14 JS comment diganti jadi netral di `layouts/app.blade.php`, `penjualan/pos.blade.php`, `dashboard/pusat.blade.php`
- **Konten panduan & tooltip yang genuinely bocor ke UI** (bukan komentar) — 12 occurrence di 6 slug `PanduanKontenSeeder.php` (termasuk teks "dimsum/gyoza"/"Dimsum Mentai" yang literally muncul di modal "Cara Pakai" halaman POS) + 5 occurrence "Berkah Mulyo" di 5 slug akuntansi/COA yang **ternyata belum pernah diadaptasi sejak 2 lapis fork sebelumnya** (Berkah Mulyo → Dimsum → Kopi Drip) + 2 occurrence di `TooltipKontenSeeder.php`
- **Cleanup 3 dead code seeder** (ditemukan saat audit, dikonfirmasi 0 referensi aktif via grep sebelum dihapus): `ItemVarianSeeder.php`, `ProgramLoyaltySeeder.php` dihapus 2026-09-22 saat Audit 2 cleanup dead code, `TestingBatch1SeederTemp.php` — ketiganya warisan Dimsum, tidak pernah didaftarkan di `DatabaseSeeder.php`

**Temuan lain (di-skip/di-defer, bukan bug)**: Modul Roastery 0% dibangun (konfirmasi status TODO 12.5 akurat, `/antrian/produksi` ternyata fitur lain — kitchen queue POS, bukan batch roasting), toggle `frozen_aktif` per cabang sudah config-driven (keputusan produk di TODO 12.8, bukan kode), 1 error log `sessions` transient (tidak berulang, confirmed aman).

### 4.7 📋 2026-09-22: Diskusi & Finalisasi Desain Fase E (QR Table Ordering)

Design doc: `FASE_E_QR_TABLE_ORDERING.md`. **Keputusan kunci**: approve queue kasir (bukan auto-approve), 2 sub-tipe dine-in (QR + kasir), session bill = 1 grup pelanggan (bill di-close saat bayar → meja kosong). Bonus: Timer, Notifikasi, Estimasi Waktu Siap. Belum ada coding — murni dokumentasi blueprint, menunggu approval Owner untuk mulai E1 (lihat TODO 12.9).

### 4.8 🟢 E1 Selesai — Master Meja + QR Generator (2026-09-23)

Fondasi teknis QR Table Ordering: library `simplesoftwareio/simple-qrcode` (v4.2), 3 migration (`mejas` — 12 kolom termasuk `qr_token` unique + composite unique `(cabang_id, nomor_meja)`; `cabangs.qr_ordering_active`; `items.waktu_siap_menit`), model `Meja` (+ relasi `cabang()`, stub `bills()` untuk E3, `generateQrToken()` static helper 32-char unik), `MejaController` (10 route: CRUD + generate-qr + print-qr + temporary create/store), `QrCodeService` (encode URL `/order/{kode_cabang}/{nomor_meja}?token=...`, SVG error-correction Q), 5 view (index/create/edit/temporary-create/pdf-sticker A6), 5 permission baru `master.meja.*` (admin_pusat/admin_gudang full, manajer_cabang read+edit, kasir/barista read-only), `MejaSeeder` (50 meja testing — 5 outlet × 10, Gudang Pusat sengaja 0 meja).

**Bug ditemukan & difix saat verifikasi 3.7**: `Route::resource(...)->name('meja')` salah method (Laravel `->name($method,$name)` butuh 2 argumen untuk rename 1 action, bukan untuk override prefix) — throw `ArgumentCountError`, ke-detect langsung lewat `route:list` di step verifikasi. Fix: ganti ke `->names('meja')` (method yang benar untuk override prefix semua action sekaligus).

**Verifikasi 3.7**: syntax check 12 file OK, migrate 3/3 DONE, seed 50 meja + 5 cabang qr_ordering_active=1 sesuai ekspektasi, permission `master.meja.*` ter-seed (re-run PermissionSeeder+RolePermissionSeeder karena ditambahkan setelah migrate:fresh terakhir), route smoke test semua 200/302, print-qr generate PDF valid 1 halaman (1.18MB, custom MediaBox 283.5×425.2pt ~10×15cm), tinker relasi & `generateQrToken()` jalan normal, error log bersih (1 bug ArgumentCountError ke-log tapi sudah resolved sebelum lanjut, sesuai aturan STOP-FIX-LANJUT).

### 4.9 🟢 Fix Gap E1 — Kelengkapan Menu (2026-09-23)

Audit pasca-E1 menemukan menu Master Meja **teknisnya jalan (route/controller/permission semua sehat) tapi TIDAK BISA DIAKSES lewat UI** — 3 gap murni kelengkapan (bukan bug logic), plus 1 aturan proses baru ditambahkan supaya tidak terulang:

1. **Menu sidebar tidak ada** — `layouts/app.blade.php` belum ditambah item "Master Meja". Fix: ditambah di grup "Stok & Gudang" (sejajar Produk Jual/Bahan Baku), `@canany` section title diupdate include `master.meja.view`.
2. **Tombol "Cara Pakai" tidak ada** — ditambah `<x-panduan-button slug="meja" />` di header `index.blade.php`.
3. **Konten panduan & tooltip belum ada** — 1 panduan (slug `meja`, format ## Tentang/Cara Menambah/Print QR/Edit/Temporary/Hapus/Peringatan) + 6 tooltip `master_meja.*` (nomor_meja, nama_meja, kapasitas, lokasi, qr_token, qr_type), di-embed ke `create.blade.php`/`edit.blade.php`.

**Koreksi naming convention**: instruksi awal minta slug `master.meja` & tooltip key `master.meja.field` — setelah cek pola existing (`produk-jual`, `master_produk_jual.resep`), dipakai **`meja`** (slug panduan) dan **`master_meja.field`** (tooltip key) supaya konsisten aplikasi, BUKAN mengikuti mentah-mentah format yang diminta.

**Investigasi permission (STEP 7, hasil: bukan bug)**: dicek asumsi "owner mungkin tidak bypass permission" — TERNYATA SALAH PREMIS. Project ini 100% custom permission (bukan Spatie, CLAUDE.md 2.1), tabel `roles`/`model_has_permissions` tidak ada. `Gate::before()` di `AppServiceProvider.php` sudah otomatis bypass semua ability untuk role `owner`, dikonfirmasi `$user->can('master.meja.view')` return `true`. Akar masalah "menu tidak muncul" 100% dari gap #1 (sidebar), bukan permission.

**Aturan baru ditambahkan**: section 3.8 "Aturan Menu Baru — Kelengkapan Wajib" (9-item checklist + STOP-INCOMPLETE) — supaya gap kelengkapan seperti ini (fitur "jalan" di backend tapi tidak reachable dari UI) tidak lolos lagi ke depan.

### 4.10 🟢 Bug Fix QR PDF + E2 Selesai — Public Menu Page (2026-09-23)

**Bug fix (sebelum E2)**: QR tidak tampil di PDF sticker (`master/meja/{id}/print-qr`) — root cause: `QrCodeService::generate()` return SVG lengkap dengan XML declaration (`<?` + `xml ...?` + `>`) di baris pertama, yang di-embed mentah via `{!! $qrSvg !!}` ke tengah dokumen HTML — invalid HTML, bikin dompdf skip render elemen itu. Fix: strip XML declaration, sisakan cuma tag `<svg>...</svg>`. **Bug turunan saat nulis fix-nya sendiri**: komentar PHP `//` yang menyebut literal XML declaration itu (mengandung `?` + `>`) bikin PHP tokenizer nutup blok `<?php` prematur (`?>` = closing tag) — persis pola bug Blade `@if()` di komentar yang pernah ketemu sebelumnya, cuma versi native PHP. Fix: reword komentar, hindari literal `?` + `>` di manapun dalam kode/komentar PHP.

**E2 — Public Menu Page**: 3 tabel baru (`bills`/`bill_items` — STUB minimum, detail penuh di E3/E4; `order_queues` — full struktur), 3 model baru (`OrderQueue`, `Bill` stub, `BillItem` stub) + `Meja.bills()`/`orderQueues()` diaktifkan, `PublicOrderController` (4 method: menu/submit/status/pollStatus, TANPA middleware auth — ditempatkan sejajar pola existing `antrian.display`), `layouts/public.blade.php` (CSS vanilla mobile-first, no Bootstrap/jQuery/Alpine, palette Kopi Drip), 3 view (`menu`/`status`/`expired`) dengan cart JS vanilla (no library) + polling status tiap 3 detik. 3 permission baru `queue.*` (view/approve/reject) disiapkan untuk E3 — kasir/manajer_cabang/admin_pusat dapat akses (owner bypass otomatis).

**Reuse maksimal** (sesuai CLAUDE.md 3.3): query menu & cek stok pakai helper `Item` yang SUDAH ADA (`tersediaDiCabang()`, `bisaDijualDiCabang()`, `hargaEfektifDiCabang()`) — 0 logic stok baru ditulis ulang.

**Verifikasi 3.7**: syntax check 16 file OK, migrate 3/3 DONE, route 4/4 terdaftar, smoke test lengkap — menu token valid=200, token salah=403, meja/cabang tidak ada=404, submit order dgn payload valid → `order_queues` terisi benar, status page + polling (pending→approved) jalan sesuai skenario, mobile user-agent test 200, data test dibersihkan setelah verifikasi. Error log bersih (1 entry lama dari sesi E1 sebelumnya, bukan baru).

### 4.11 📋 Audit Retroaktif Aturan 3.8 Dijadwalkan (2026-09-23)

Ditambahkan TODO 12.10 — audit kelengkapan aturan 3.8 (menu sidebar, tombol Cara Pakai, panduan, tooltip) untuk SEMUA menu authenticated existing yang dibangun SEBELUM aturan 3.8 dibuat (jadi belum tentu ikut checklist itu). **Dijadwalkan setelah Fase E selesai** (E7 tuntas) — supaya fokus tidak kepecah tapi juga tidak terlupa. Prioritas nanti: menu operasional harian (POS, Master, Stok) duluan.

### 4.12 🟢 E3 Selesai — Kasir Approve Queue + Layout Meja Visual (2026-09-23)

**Lengkapi tabel E2 (stub → full)**: `bills` +9 kolom (diskon/pajak/catatan/closed_by/transfer-audit/customer/payment_mode), `bill_items` +4 kolom (status_dapur_at/by, komplain_reason, urutan_masuk). **Tabel baru**: `table_events` (audit trail 8 event_type). Model `Bill`/`BillItem` full (bukan stub lagi) + `TableEvent` baru. `BillService` baru (approveQueue/rejectQueue/tandaiMejaTerisi/addItemManual/transferMeja/closeBill) — **reuse `PenjualanService::buatOrder()` existing** untuk closeBill (potong stok via resep + catat kas), 0 logic penjualan ditulis ulang (CLAUDE.md 3.3). `KasirMejaController` (10 route: layout meja, queue, approve/reject, detail meja, tandai terisi, tambah item, transfer, print struk 2-halaman, bayar). 9 permission baru `kasir.*` (kasir/manajer_cabang/admin_pusat full akses, owner bypass). Aturan 3.8 lengkap: 2 menu sidebar + badge counter (scoped per cabang, security-critical CLAUDE.md 3.4), 2 panduan (`kasir-layout-meja`, `kasir-queue`), 3 tooltip `kasir_*` (1 di-embed ke UI, 2 tersimpan di DB tapi belum ada elemen UI statis buat nempelinnya — transfer pakai `prompt()` JS dinamis, payment mode belum ada selector di E3, keduanya realistis baru dapat tempat di E4 polish).

**3 bug ditemukan & difix saat manual testing** (sesuai aturan STOP-FIX-LANJUT):
1. `resolveCabangId()` di `KasirMejaController` fallback ke `defaultCabangId()` owner (Gudang Pusat) alih-alih outlet pertama — untuk owner/admin_pusat tanpa `session('active_cabang_id')` eksplisit, sekarang fallback ke outlet aktif pertama (meja/bill genuinely per-outlet, beda dari pola "owner=semua cabang" di StokController).
2. Enum `bills.payment_mode` (`bayar_dulu`/`bayar_di_kasir`) tidak match vocab `order_queues.payment_mode` (`bayar_dulu`/`open_bill`) — MySQL truncate error saat approve. Fix: translate value di `BillService::approveQueue()`.
3. `BillService::closeBill()` lupa isi `nama_item` (field wajib tanpa fallback di `PenjualanService::resolveItemRows()`) saat mapping `bill_items` → format item row `buatOrder()`.

**Blocker data ditemukan & diselesaikan**: tabel `kas` genuinely 0 baris di SELURUH database sejak Fase B (bukan bug E3) — bikin `closeBill()` gagal di step pembayaran. Dibuat `KasSeeder.php` (5 Kas Tunai, 1 per outlet, saldo awal Rp500rb) atas persetujuan Owner, didaftarkan di `DatabaseSeeder.php`.

**Verifikasi 3.7 + manual test end-to-end PENUH**: syntax check 17 file OK, migrate 3/3 DONE, route 10/10 terdaftar, aturan 3.8 checklist 6/6 pass. Skenario lengkap tervalidasi: submit order via QR (E2) → muncul di `/kasir/queue` → approve → bill terbuka, `bill_items` terisi → layout meja berubah hijau muda dengan timer → detail modal tampil item benar → bayar → **stok terpotong benar** (Espresso 20→18, Kopi Susu 20→19), **Kas bertambah benar** (Rp500rb→Rp550rb), **order/penjualan record dibuat dengan HPP terisi**, bill status closed, meja kembali kosong (abu-abu). Semua data test dibersihkan & efek stok/kas di-reverse setelah verifikasi.

### 4.13 🟢 Audit E4 + E4.1 Selesai — Bill Unification: Fondasi Migration `meja_id` (2026-09-23)

**Audit E4 (read-only)** menemukan Owner pilih **Opsi A** (semua bayar via POS) membuka 2 gap: (1) POS pilih meja masih text input bebas (`orders.nomor_meja` string, bukan FK), (2) Save Bill POS lama (`Order` status Pending) 100% independen dari sistem `bills` baru (E2/E3) — 2 sumber kebenaran paralel yang tidak saling tahu, dikonfirmasi nyata lewat data test Owner sendiri (order `nomor_meja="2"` text vs bill `meja_id=1` terpisah untuk "meja yang sama").

**Audit E4.0 (dependency mapping)** memetakan 5 fitur POS yang bergantung `Order.status=Pending` (list Bill Tersimpan, Save Bill, Bayar, Batalkan, Print Preview — yang terakhir ternyata independen, tidak perlu disentuh) — 0 Laporan/Dashboard yang bergantung status Pending, `OrderObserver` aman dipertahankan (0 referensi ke Bill), event `AntrianCreated` WAJIB tetap terpicu (dipicu dari `buatOrder()`, bukan dari status Pending langsung).

**Strategi final disepakati Owner: Approach C (Hybrid)** — `Bill` = SATU-SATUNYA representasi "sedang berlangsung" (baik dari QR maupun POS manual), `Order` = SATU-SATUNYA representasi "sudah final" (selalu via `buatOrder()` existing, dipicu dari `BillService::closeBill()` — pola E3 yang sudah proven). `StatusOrder::Pending` case **dipertahankan di enum** untuk backward-compat data historis, walau alur baru tidak akan pakai lagi.

**Cleanup data residual** (temuan test manual Owner sendiri di antara sesi): `orders` id=4, `bills` id=3, `table_events` id=1, 1 meja OUT001 berlebih (id=51, nomor=15) — semua dihapus, baseline kembali bersih (mejas=50, bills=0, orders=1 seeder awal).

**E4.1 — Migration `orders.meja_id`**: kolom FK nullable ke `mejas` (`nullOnDelete`, index), `nomor_meja` string **TETAP dipertahankan** (backward-compat data historis, TIDAK di-backfill — keputusan final Owner, skip). Model `Order` — fillable + relasi `meja()`.

**Sub-fase E4 disepakati**: E4.1 (migration, **selesai**) → E4.2 (POS dropdown meja) → E4.3 (gabung Save Bill + Bayar + Cancel ke `BillService`, termasuk `cancelBill()` baru) → E4.4 (testing E2E, **split payment WAJIB dites eksplisit** bukan asumsi).

**Verifikasi 3.7**: syntax check 2 file OK, migrate 1/1 DONE, FK+index terkonfirmasi via `information_schema`, relasi `Order::first()->meja` return NULL (benar, belum ada data), route `/penjualan/pos` 200 (logged in) — `$billTersimpan` query existing tidak pecah oleh kolom baru, error log bersih.

### 4.14 🟢 E4.2–E4.4 Selesai — POS Dropdown Meja + Bill Unification + Testing E2E (2026-09-23)

**E4.2**: dropdown `<select name="meja_id">` di POS (ganti text input bebas) — sumber data `Meja::where('cabang_id', ...)->aktif()`, hidden input `nomor_meja` tetap ke-sync otomatis via JS (backward-compat kolom lama). Fallback: outlet tanpa Master Meja masih dapat text input manual + warning.

**E4.3 (inti E4)**: `simpanBill()`/`chargeBill()`/`batalkanBill()` di-refactor — untuk dine-in dengan `meja_id`, alur baru lewat `BillService` (`tandaiMejaTerisi()` + `addItemManual()` dengan `hargaOverride` baru utk preservasi harga varian POS legacy), bukan lagi `Order` status Pending. `BillService::cancelBill()` ditambah. Route Bill-based **terpisah** dari route `{order}` lama (`/penjualan/bill/{bill}/charge` vs `/penjualan/{order}/charge`) — sengaja, hindari resiko ID collision `bills.id` vs `orders.id`. Non-dine-in (Takeaway) tetap pakai alur `Order` Pending lama, tidak disentuh.

**E4.4 — Testing E2E, 7 skenario, SEMUA PASS**: Save Bill Dine-in, Bayar dari POS, Save Bill Takeaway (alur lama tidak keganggu), Split Payment (Tunai+Transfer), Cancel Bill, Approve QR lalu tambah item dari POS (masuk `bill_id` yang SAMA), Approve QR lalu Bayar dari POS (semua item QR+POS merge jadi 1 order). **5 bug/blocker ditemukan & difix** selama testing (semua data/seeder issue, bukan bug arsitektur E4.2/E4.3): (1) wording pesan "Bill Meja Meja 3" duplikat kata "Meja", (2) cleanup sesi sebelumnya lupa reverse stok/kas order yang ternyata sudah "selesai" bukan "pending" residual, (3) Kas Transfer/QRIS belum ada di `KasSeeder` (cuma Tunai dari E3) — split payment gagal, (4) fix poin 3 sempat introduce bug idempotency baru (`updateOrCreate()` reset `saldo_sekarang` tiap reseed) — diperbaiki jadi update metadata-only, (5) baseline stok Kopi Susu awalnya salah diasumsikan "30" saat verifikasi cleanup, ternyata benar "20" sesuai `StokTestingSeeder`.

**Verifikasi 3.7**: syntax check semua file OK, cache clear OK, migrate status normal, route 4 baru terdaftar, 7/7 test skenario PASS, error log bersih setelah fix, semua data test dibersihkan (`DB::table()->delete()` bukan Eloquent `->delete()` — hindari SoftDelete trap yang jadi akar bug #2).

### 4.15 🟢 E5 Selesai — Kitchen Display (2026-09-23)

Layar dapur fullscreen (pola serupa `antrian/display.blade.php`, vanilla JS no-framework) menampilkan `bill_items` berstatus `status_dapur=pending`, dikelompokkan per meja, auto-refresh polling (interval configurable per cabang). Migration `kitchen_display_settings` (1 baris per cabang: `is_active`, `auto_refresh_seconds`). Model `KitchenDisplaySetting` (`forCabang()` helper). `KitchenController` (4 method: `index`, `pending` JSON polling, `markSiap`, `markKomplain` — **reuse method `BillItem::markSiap()`/`markKomplain()` yang sudah ada dari E3**, 0 logic baru ditulis ulang). Kartu meja berkedip merah kalau item ≥15 menit belum "Siap". Setting aktif/nonaktif + interval refresh diintegrasikan ke halaman **Cabang → Edit** (bukan halaman terpisah, tabel settings sendiri di luar `cabangs`).

Aturan 3.8 lengkap: menu sidebar "Kitchen Display" (grup Antrian Produksi, gated `kitchen.display.view`, target `_blank` sama seperti Display TV Antrian), 2 permission baru (`kitchen.display.view`, `kitchen.item.mark` — kasir view-only, barista/operator_produksi + manajer_cabang + admin_pusat full), panduan slug `kitchen.display`, tooltip `cabang.kitchen_display_aktif` di-embed ke form Edit Cabang + tombol Cara Pakai di section yang sama.

**Verifikasi 3.7**: syntax check semua file OK, migrate 1/1 DONE, route 4/4 terdaftar, seeder permission/role/panduan/tooltip re-run sukses, smoke test route 302 (redirect login, normal — belum ada sesi browser aktif saat testing headless), error log bersih.

### 4.16 🟢 E6 Selesai — Timer + Notifikasi + Estimasi Waktu Siap (2026-09-23)

**Timer meja**: sudah ada & berfungsi dari E3 (`kd-timer-text`, update tiap 15 detik dari `started_at`) — diverifikasi ulang, tidak perlu perubahan.

**Notifikasi Kasir**: endpoint polling baru `KasirMejaController::pollNotifikasi()` (queue pending count + komplain count per cabang, di-poll tiap 10 detik dari Layout Meja) — badge queue existing sekarang live-update tanpa reload halaman, badge komplain baru (merah, muncul kalau ada `bill_items.status_dapur='komplain'`), animasi flash + beep (Web Audio API, sama pola dengan ding Antrian Display) saat ada penambahan.

**Estimasi Waktu Siap**: kolom `items.waktu_siap_menit` (ternyata SUDAH ADA dari migration E2 lama yang belum dipakai — dicek dulu sebelum bikin baru sesuai CLAUDE.md 3.3, migration duplikat yang sempat dibuat dihapus lagi) — ditambah ke `$fillable` Item, form input di Master Produk Jual (`_form.blade.php`, shared create+edit) dan Item generik, validasi di `ProdukJualRequest`/`ItemRequest`. Ditampilkan di 3 tempat: (1) halaman menu QR pelanggan (badge "~X menit" per item), (2) halaman status pesanan (estimasi total = MAX bukan SUM, karena item disiapkan paralel), (3) Kitchen Display (badge per-item + deadline overdue individual, menggantikan aturan flat 15-menit kalau item itu punya estimasi).

Aturan 3.8: tooltip `item.waktu_siap_menit` ditambah (dipakai di 2 form: Item generik + Master Produk Jual).

**Verifikasi 3.7**: syntax check 7 file OK, tooltip reseed sukses (78 tooltip), cache clear OK, route smoke test normal, error log bersih (1 entry lama dari eksperimen migration duplikat yang sudah dihapus sebelum verifikasi — bukan residual aktif).

### 4.17 🟢 E7 Selesai — Testing E2E Autonomous (2026-09-24)

12 skenario dijalankan lewat script PHP yang memanggil `BillService`/`PenjualanService` langsung (kode yang sama dengan controller) + curl untuk endpoint GET/JSON. Hasil: **semua skenario PASS** (S1 QR flow, S2 split payment, S3 cancel, S4 transfer, S5 merge QR+POS, S6 takeaway jalur lama, S7 kitchen, S8 timer, S9 estimasi, S10 reject, S11 10 meja penuh, S12 3 queue serentak). 5 assertion awal FAIL murni karena artefak harness (curl di dalam `shell_exec` kehilangan sesi) — diverifikasi ulang langsung via Bash, PASS.

**Temuan**: (1) sisa `transaksi_keuangans`/`orders` yatim dari sesi E4.4 belum bersih → bikin `nomor_transaksi` bentrok (UniqueConstraint) — dibersihkan, baseline dipulihkan (stok 20, kas 500rb/0/0). (2) **Perbedaan ekspektasi**: estimasi waktu pakai MAX (5 mnt), bukan SUM (11 mnt) — sesuai desain E6, perlu konfirmasi Owner. (3) **Perbedaan ekspektasi**: order kedua/ketiga di meja yang sudah punya bill **di-merge** ke bill yang sama (bukan ditolak) — sesuai desain E3/E4. (4) Warna timer dihitung JS klien dari `data-started-at`; SSR selalu hijau sampai JS jalan. **Belum diuji**: klik UI nyata di browser (E7 interaktif tetap sebaiknya dilakukan Owner sebelum uji coba produksi).

### 4.18 🟢 Bug HPP Laba Rugi (2026-09-24)

Laporan "ngambil 1 angka di depan" **tidak bisa direproduksi**: order HPP 25.000 tampil "Rp 25.000" penuh di Laba Rugi Produksi; kode query (`SUM(order_items.hpp)`), kolom `decimal(15,2)`, dan `number_format` semua benar. **Bug nyata yang ditemukan**: Laba Rugi *Formal* menampilkan HPP Rp 0 (dan tanpa baris akun) karena tabel `chart_of_accounts` **kosong** — `ChartOfAccountsSeeder` tidak terdaftar di `DatabaseSeeder`. Fix: seeder didaftarkan + dijalankan (59 akun); verifikasi HPP 25.000 muncul di akun 5-1101. Catatan: bila keluhan Owner soal tampilan lain (mis. layar/kolom tertentu), perlu screenshot/halaman spesifik.

### 4.19 📋 Design Fase Roastery (2026-09-24)

Dibuat `FASE_ROASTERY_DESIGN.md` (belum ada kode). Temuan kunci: tidak ada item roasted curah (kg) — RTB hanya pack, jadi perlu item output batch + langkah pengemasan. Menunggu approval Owner (5 pertanyaan di akhir dokumen).

### 4.20 🟢 Fase Roastery R1–R5 Selesai (2026-09-24)

Keputusan Owner: item roasted curah + pengemasan terpisah (Q1), akses Owner + Admin Gudang (Q2), susut default Light 12/Medium 15/Dark 18 (Q3), cost = harga green saja, biaya operasional jadi **R6** (Q4), takaran biji 12 menu (Q5).

- **R1** Roasting Profile: tabel `roasting_profiles`, CRUD `/roastery/profile`, 3 profile default.
- **R2** Batch Roasting: tabel `roasting_batches` + `roasting_batch_packs`, `RoastingBatchService` (create draft → complete → cancel draft → pack), 6 item `RTB-CURAH-{ARABIKA|ROBUSTA}-{LIGHT|MEDIUM|DARK}` (bahan_baku, kg, stok 0 di GP001). Semua mutasi stok lewat `StokService` existing (FIFO + movements). Waste = green − roasted (termasuk susut air), yield = roasted ÷ green.
- **R3** Dashboard `/roastery/dashboard` (ringkasan bulanan + Chart.js tren yield/waste 30 hari).
- **R4** `ResepBumbuKopiSeeder`: 12 menu kopi memotong Roasted Curah Arabika Medium (Espresso 9 g … Kopi Sidikalang 20 g), tidak menimpa resep yang sudah ada. Susu/gula belum diisi.
- **R5** Test E2E 25 assertion, semua PASS: batch 5 kg → 4,2 kg (yield 84%, cost/kg Rp107.143), pengemasan 8×500 g, transfer ke OUT001, jual Espresso memotong 0,009 kg + HPP Rp964, dashboard menampilkan angka benar.

**Bug ditemukan & difix**: roasted curah/pack yang ditransfer ke outlet masuk dengan cost 0 (`prosesTerimaTransfer` memakai `items.harga_beli_terakhir`) → HPP kopi 0. Fix: `completeBatch`/`packBatch` mengisi `harga_beli_terakhir` item curah/pack dengan cost hasil batch.

**Konsekuensi operasional PENTING**: sejak resep kopi aktif, menu kopi **ditolak** di outlet yang stok roasted curahnya kosong. Sebelum dipakai kasir, kirim roasted curah dari GP001 ke tiap outlet lewat Transfer Stok. Aturan 3.8: sidebar grup "Roastery" (3 menu), 10 permission `roastery.*` (Admin Gudang; Owner bypass), 4 panduan (`roastery-profile/-batch/-dashboard`, `resep-bumbu-kopi`, modul `roastery`), 7 tooltip `roastery_*`. Slug memakai kebab-case sesuai konvensi (bukan `roastery.profile`). **Belum diuji**: klik UI nyata di browser (form batch, JS estimasi live).

### 4.21 🟢 Distribusi Awal Roastery (2026-09-24)

Data operasional nyata (bukan test, tidak di-cleanup): 2 batch roasting completed di GP001 — `RB-2026-0001` Arabika Medium 20 kg → 17 kg (yield 85%, Rp105.882/kg) dan `RB-2026-0002` Robusta Medium 8 kg → 6,5 kg (yield 81,25%, Rp73.846/kg). Stok green tadinya cukup (50/30 kg, tanpa top-up), sisa Arabika 30 kg, Robusta 22 kg. 5 Stock Transfer (`TRF-20260924-001..005`, status diterima) GP001 → OUT001–OUT005, masing-masing 3 kg Arabika + 1 kg Robusta lewat `StokService` (alur yang sama dengan controller). Hasil stok: GP001 Arabika 2 kg / Robusta 1,5 kg; tiap outlet 3 kg / 1 kg. Test jual 1 Espresso di OUT001: **SUCCESS**, curah Arabika 3 → 2,991 kg (−9 g); transaksi test di-rollback (kas, stok, orders, transaksi keuangan kembali seperti semula). Verifikasi 3.7: syntax OK, halaman batch/dashboard/transfer HTTP 200, error log bersih. Catatan: stok ini cukup ±330 gelas Espresso atau ±166 gelas 18 g per outlet — perlu roasting rutin berikutnya.

### 4.22 🟢 Isu #3 — Resep Lengkap Menu Kopi & Non-Kopi (2026-09-24)

`ResepBumbuKopiSeeder` diperluas (+35 baris: susu, gula, sirup, cup, tutup untuk 8 KPI + 4 MBW) dan `ResepBumbuNonKopiSeeder` baru (4 resep, 17 baris; Air Mineral sengaja tanpa resep, teh celup tidak ada di inventory). Total **16 resep**, 64 baris bahan. Logic dipusatkan di trait `Database/Seeders/Concerns/SeedsResepMenu` — idempotent & non-destruktif (hanya MENAMBAH baris per `item_id` yang belum ada; baris hasil edit Owner tidak diubah, tapi baris yang dihapus Owner akan muncul lagi bila seeder dijalankan ulang). Konversi satuan lewat `ResepBumbuItem::getQtyPerUnitDalamKgAttribute` (ml→liter, g→kg, pcs apa adanya); sirup (satuan botol) disimpan sebagai fraksi 0,02 botol per gelas (15 ml/750 ml, Opsi B) — ubah bila satuan sirup diganti ke ml. Test jual 1 Cappuccino di OUT001 (rollback): biji −0,018 kg, susu −0,15 L, cup 16 oz −1, tutup −1 (semua benar), HPP FIFO Rp6.906 (biji 1.906 + susu 2.700 + cup/tutup 2.300) → margin 72,4% dari Rp25.000. Verifikasi 3.7: syntax OK, seeder 2× run (idempotent), POS & produk-jual HTTP 200, log bersih. Catatan: resep belum mencakup sedotan/tas takeaway/es batu (takaran belum ada).

### 4.23 🟢 Audit Retroaktif Aturan 3.8 Selesai (2026-09-24)

Inventarisasi otomatis 90 link sidebar (script membaca `layouts/app.blade.php`, route → controller → view → tombol panduan/tooltip/permission). Owner menyetujui scope (permission absensi/keamanan dibuat semua; halaman admin tetap Owner-only; tooltip hanya 6 menu kompleks).

- **Prioritas 0 — bug kritis permission**: **17** permission dipakai di sidebar/controller sejak warisan Dimsum tapi **tidak pernah dibuat** di `PermissionSeeder` (11 yang terdeteksi di sidebar + `hapus_log_absensi`, `restore_data_terhapus`, `hapus_permanen_data`, `buat_backup`, `download_backup`, `hapus_backup` yang ketemu lewat scan seluruh kode). `RolePermissionSeeder` melewatinya diam-diam (`isset($permMap[..])`), sehingga **hanya Owner yang bisa** scan absensi, lihat dashboard absensi, shift, audit log, dll. Dibuat 19 permission (17 + `lihat_shift`, `export_audit_log`), mapping bawaan aktif: Kasir/Barista/Helper bisa `scan_absensi`; Manajer bisa dashboard/laporan absensi; restore/hapus permanen/backup/penggajian/hari libur tetap Owner-only. Verifikasi: kasir.o1 `/absen/scan` 200 dan menu tampil, `/backup` & `/audit-log` 403; manajer.o1 dashboard/laporan absensi 200.
- **Panduan baru (13)**: `cabang`, `user`, `role-hak-akses`, `antrian-produksi-mode`, `antrian-display-tv`, `face-attendance-log`, `pemakaian-perlengkapan`, `laporan-perlengkapan`, `pengaturan-penggajian`, `audit-log`, `backup-database`, `admin-tooltip-helper`, `admin-panduan-helper` (total 100 panduan). Tombol Cara Pakai ditambah di 12 view (Display TV = layar penuh, panduan diakses lewat /panduan).
- **Tooltip baru (23 key, 6 menu)**: `supplier.*` (3), `kategori_transaksi.*` (5), `resep_bumbu.*` (4), `loyalty.*` (5), `transfer_antar_kas.*` (2), `transfer_dana.*` (4) — di-embed ke 9 view form. Field disesuaikan dengan form nyata (Supplier tidak punya NPWP/rekening; Transfer Antar Kas tidak punya biaya transfer).
- **Bug tambahan**: dropdown satuan di edit Master Bumbu Pusat tidak punya `ml` (resep Isu #3 memakai ml) → menyimpan ulang akan mengubah ml menjadi kg; ditambahkan opsi `ml`. Form resep di Produk Jual memakai input teks bebas sehingga aman.
- **Bukan gap (info)**: ±20 permission laporan akuntansi/CoA/Loyalty/Bumbu Pusat/Transfer Antar Kas/Dashboard PO Owner-only sesuai komentar seeder. Panduan Loyalty masih membahas "jasa giling" (warisan lama, belum diadaptasi). Panduan/tooltip helper: seeder bersifat updateOrCreate — reseed menimpa edit Owner.
- **Verifikasi 3.7**: syntax OK, reseed Permission/RolePermission/Panduan/Tooltip, 12 halaman + 7 form/index HTTP 200 tanpa error, log bersih (1 error di log berasal dari typo nama tabel di script audit sendiri, bukan aplikasi). **Belum**: verifikasi visual tooltip di browser.

---

## 5. STRATEGI PENGEMBANGAN

### 5.0 Fase Roastery (R1–R5 selesai 2026-09-24, lihat 4.20; R6 = biaya operasional)
Batch roasting di GP001: green bean → roasted curah (yield, waste, cost/kg via FIFO existing) → pengemasan ke RTB → distribusi via Stock Transfer → potong stok via resep. 3 tabel baru (`roasting_profiles`, `roasting_batches`, `roasting_packagings`), 5 sub-fase R1–R5 (~6–7 hari). Detail: `FASE_ROASTERY_DESIGN.md`.

### 5.1 Modul yang PERLU DIBANGUN/DIADAPTASI 🔴

| Modul | Perlakuan | Detail |
|-------|-----------|--------|
| **Modul Roastery** | BANGUN BARU (reuse struktur) | Batch roasting: green bean → roasted bean. Bisa reuse struktur "produksi" yang sudah ada di warisan Dimsum (resep/komposisi bahan), tapi perlu model/alur baru untuk konsep "batch" (input kg green bean, output kg roasted bean dengan shrinkage rate, tanggal roasting, tanggal expired) |
| **Varian Menu N-dimensi** | ADAPTASI | Struktur `ItemAttribute`/`ItemAttributeValue`/`ItemVariant` sudah ada dari Dimsum (dulu untuk size/rasa/level pedas dimsum) — reuse untuk: Size (S/M/L), Susu (Fullcream/Skim/Oat/Almond), Gula (Less/Normal/Extra), Es (Less/Normal/No Ice), Extra Shot |
| **POS Dine-in vs Takeaway** | ADAPTASI | Tipe transaksi Dimsum ada 3 (Dine-in/Takeaway/Frozen) — "Frozen" tidak relevan untuk Kopi Drip, perlu diputuskan apakah dihapus atau diganti (mis. Gojek/Grab untuk delivery, lihat TODO 12.8) |

### 5.2 Modul yang DIPAKAI APA ADANYA (REUSE dari Dimsum/Berkah Mulyo) 🟢

| Modul | Alasan |
|-------|--------|
| **HR** (Karyawan, Absensi, Cuti, Gaji, Shift, Evaluasi) | Universal, pola sama untuk bisnis apapun |
| **Aset & Depresiasi** | Universal |
| **Keuangan Dasar** (COA, Neraca, Kategori Transaksi) | Universal, akuntansi standar |
| **Stok FIFO + Batch** | Universal, pattern inventory food & beverage |
| **Notifikasi, Activity Log, Backup** | Infrastruktur, tidak perlu diubah |
| **Pembelian (PO)** | Universal — dipakai juga untuk beli green bean dari supplier |
| **Transfer Antar Cabang** | Universal — Gudang Pusat/Roastery kirim roasted bean & bahan baku ke outlet |
| **Setoran Kasir Cabang → HO** | Universal, alur approval sudah teruji di Dimsum |
| **Dashboard & Laporan** | Universal, tinggal adaptasi terminologi (kg giling → gelas/porsi sudah pernah dikerjakan di Dimsum, prinsip sama) |
| **Export Excel/PDF Laporan** | Universal, pola sudah matang dari Dimsum (styling konsisten, xlsx asli bukan CSV bertopeng) |

**Prinsip**: Kalau modulnya universal, JANGAN diubah. Fokus energi ke yang beda (Roastery, Varian Menu Kopi).

### 5.3 Fase E: QR Table Ordering (Coffee Shop Modern)

Fitur baru khusus dine-in coffee shop — pelanggan scan QR di meja, pesan langsung dari HP (guest, no login), order masuk antrian approve kasir, bill per meja terbuka sampai dibayar. Design blueprint lengkap (arsitektur DB 6 tabel baru, API endpoints, UI sketch, 7 sub-fase E1–E7, FAQ edge case) ada di **`FASE_E_QR_TABLE_ORDERING.md`** (root project) — belum dikerjakan, status masih 📋 design blueprint menunggu approval mulai E1.

Keputusan kunci: approve queue kasir (bukan auto-approve), 2 sub-tipe dine-in ("via QR"/"via Kasir"), session bill = 1 grup pelanggan (close saat bayar → meja kosong), Kitchen Display opsional per outlet, reuse maksimal infrastruktur existing (resep/potong-stok/split-payment/printer/permission).

---

## 6. DESAIN & BRANDING

### 6.1 Palette Warna
| Warna | Hex | Pakai untuk |
|-------|-----|-------------|
| **Hitam** | `#1A1A1A` | Sidebar, header, primary text |
| **Hijau Tua** | `#2D6A4F` | Button primary, accent, highlight, link aktif |
| **Cream** | `#F5F0E6` | Background section, card hover |
| Putih | `#FFFFFF` | Background section kontras |
| Abu terang | `#F5F5F5` | Divider, background secondary |

**Filosofi warna**: Hitam-hijau elegant coffee shop premium (mirip vibe Starbucks) + cream buat kesan hangat & cozy.

**Riwayat palette**: Fase D awalnya pakai skema hijau tua gelap (`#1B4332`) untuk sidebar, tapi direvisi (reversal parsial) balik ke hitam `#1A1A1A` — Owner prefer kombinasi klasik sidebar hitam + accent hijau. `#2D6A4F` (accent) dan `#F5F0E6` (cream) tidak berubah. **Status implementasi**: SUDAH diterapkan penuh di `config/laravelpwa.php` dan semua CSS view (`layouts/app.blade.php`, `layouts/guest.blade.php`, error pages, dll).

### 6.2 Nama & Tagline
- **Nama**: **Kopi Drip Sidikalang**
- **Tagline**: **Coffee Shop • Roastery • Eatery**
- **APP_NAME** di `.env`: `"ERP Kopi Drip"`
- **Default `PengaturanUmum.nama_perusahaan`**: `"Kopi Drip Sidikalang"`
- **Footer struk default**: `"Kopi Drip Sidikalang - Coffee & Roastery"` (bisa diedit per outlet via `Cabang.footer_struk`)

### 6.3 Logo
| Versi | Fungsi | Sumber |
|-------|--------|--------|
| **Logo Full** (bulat, "KOPIDRIP" + Sidikalang) | Login page, sidebar full, PDF header, PWA icon | `resources/logo-source/Logo_KopiDrip.png` (disiapkan Owner) |
| **Logo Icon** | Sidebar collapsed, favicon, notif icon | Generate otomatis dari logo full |

**PWA Icon**: 8 ukuran (72, 96, 128, 144, 152, 192, 384, 512) — SUDAH di-generate ulang dari `Logo_KopiDrip.png` via `php artisan generate:pwa-icons` (Fase A).

### 6.4 Responsif
- Wajib bisa dipakai di PC (browser desktop) dan Tablet
- Ikuti pola responsif warisan Dimsum/Berkah Mulyo (Bootstrap 5 grid)
- POS khususnya: layout harus optimal di tablet horizontal (kasir/barista pakai tablet)

---

## 7. SPESIFIKASI FITUR UTAMA

### 7.1 POS (Point of Sale)

**Layout**: Grid produk dengan gambar (warisan Dimsum, sudah sesuai kebutuhan coffee shop). Sidebar kanan untuk keranjang & bayar.

**Kategori item** (9 kategori, lihat 4.2 B2): Kopi (KPI), Manual Brew (MBW), Non-Kopi (NKP), Snack (SNK), Makanan (MKN), Roasted Bean (RTB — retail biji kopi kemasan), Green Bean (GRB — bahan baku roastery), Bahan Baku (BHN), Kemasan (KMS).

**Klasifikasi `items.tipe`** (warisan struktur Dimsum, masih berlaku):
| Tipe | Tampil di POS? | Kelola via menu |
|------|----------------|-----------------|
| `bahan_baku` | Tidak (bahan mentah, termasuk green bean) | Bahan Baku & Kemasan |
| `kemasan` | Tidak | Bahan Baku & Kemasan |
| `tambahan_gratis` | Ya — section "Item Tambahan" (1-klik, gratis) | Bahan Baku & Kemasan |
| `produk_jual` | Ya — grid utama (kopi, manual brew, non-kopi, snack, makanan, roasted bean retail) | Produk Jual |
| `produk_tambahan` | Ya — section "Item Tambahan" (berbayar, mis. extra shot) | Produk Jual |

**Fitur wajib** (warisan Dimsum, masih relevan untuk Kopi Drip):
1. Grid produk dengan gambar + nama + harga, filter kategori, search bar
2. **Tipe transaksi**: Dine-in / Takeaway (⚠️ "Frozen" warisan Dimsum perlu diputuskan nasibnya — lihat TODO 12.8)
3. **Varian produk** (N-dimensi) — untuk Kopi Drip: Size (S/M/L), Susu (Fullcream/Skim/Oat/Almond), Gula (Less/Normal/Extra), Es (Less/Normal/No Ice), Extra Shot. Struktur `ItemAttribute`/`ItemVariant` sudah ada, tinggal diisi data per menu (belum dikerjakan, lihat TODO 12.6)
4. **Auto potong stok** setelah klik "Charge/Bayar", berdasarkan resep (belum diisi untuk menu Kopi Drip — lihat TODO 12.4)
5. **Resep opsional per produk** — menu kopi butuh resep (biji, susu, sirup, cup, dst), roasted bean retail mungkin tidak butuh resep (langsung potong stok pack)
6. **Item tambahan** (extra shot, syrup tambahan, dll — gratis atau berbayar)
7. **Custom item**, **Add customer** (loyalty), **Diskon & fee**, **Split Payment**
8. **Metode pembayaran**: Tunai, Transfer, QRIS (Gojek/Grab sudah dihapus dari enum warisan Dimsum — lihat CLAUDE.dimsum.reference.md 4.17 kalau perlu diaktifkan lagi untuk delivery)
9. **Multi-action**: Save Bill, Print Bill, Charge, Split Bill
10. **Struk cetak**: fleksibel, footer configurable per cabang (default sudah "Kopi Drip Sidikalang - Coffee & Roastery")

### 7.2 Struktur Varian Produk

Model konseptual (warisan Dimsum, struktur DB sudah ada):
```
Item (Menu)
  ├─ punya_varian: BOOLEAN (config per item)
  ├─ stok_per_varian: BOOLEAN (config per item)
  │
  └─ Kalau punya_varian = TRUE:
       ├─ ItemAttribute (misal: "Size", "Susu", "Gula", "Es", "Extra Shot")
       │    └─ ItemAttributeValue (misal Size: "S", "M", "L")
       └─ ItemVariant (kombinasi: Size M + Susu Oat + Gula Less + Es Normal)
             ├─ harga_override (opsional, mis. susu oat +5rb)
             ├─ stok (kalau stok_per_varian = TRUE)
             └─ resep_override (opsional)
```

"Kopi Sidikalang" (KPI-008) sudah diberi `punya_varian=true` sebagai contoh — kombinasi atribut belum diisi (lihat TODO 12.6).

### 7.3 Modul Roastery (BELUM DIBANGUN — design lengkap di `FASE_ROASTERY_DESIGN.md`)

**Konsep**: proses batch — input Green Bean (kg) → output Roasted Bean (kg, dengan shrinkage/susut berat khas proses roasting) → jadi stok RTB (produk retail kemasan) sekaligus bahan baku internal untuk menu kopi di outlet.

**Rencana kasar** (perlu didetailkan saat eksekusi, lihat TODO 12.7):
- Model baru untuk "Roasting Batch" (tanggal, green bean source + qty, roasted bean output qty, shrinkage %, operator, cabang/roastery lokasi)
- Terhubung ke `stock_movements`: kurangi stok Green Bean, tambah stok Roasted Bean
- Kemungkinan reuse pola "produksi" yang sudah ada di modul lain (perlu dicek pola paling dekat di codebase sebelum bikin baru — prinsip section 3.3)

### 7.4 Setoran Kasir, Dashboard, Laporan

Semua warisan Dimsum, struktur & alur **REUSE apa adanya** (lihat 5.2) — terminologi generik (Rp/transaksi), tidak spesifik dimsum, jadi tidak perlu adaptasi. Detail lengkap alur ada di `CLAUDE.dimsum.reference.md` section 7.3–7.5 kalau perlu referensi implementasi.

### 7.5 QR Table Ordering (rencana Fase E)

Design blueprint lengkap di `FASE_E_QR_TABLE_ORDERING.md` — ringkasan modul:

- **6 tabel baru**: `mejas` (master meja + QR token), `bills` (sesi per meja), `bill_items` (item dalam bill), `order_queues` (antrian approve dari QR), `table_events` (audit trail), `kitchen_display_settings`
- **2 kolom baru**: `items.waktu_siap_menit`, `cabangs.qr_ordering_active`
- **Endpoint publik (guest, no auth)**: `GET /order/{cabang}/{meja}`, `POST /order/submit`, `GET /order/status/{queue_id}`
- **Endpoint kasir**: layout meja, approve/reject queue, tandai terisi, transfer bill, bayar (close bill), print struk
- **Endpoint master**: CRUD meja, generate/print QR
- **Endpoint kitchen**: display auto-refresh, tandai siap/komplain
- **7 sub-fase**: E1 Master Meja+QR → E2 Public Menu Page → E3 Kasir Layout+Approve Queue → E4 Bill Session+Bayar → E5 Kitchen Display → E6 Timer+Notifikasi+Estimasi → E7 Testing E2E+Polish (total ~2 minggu, lihat TODO 12.9 untuk checklist per sub-fase)

---

## 8. KONVENSI CODING

Sama persis dengan warisan Dimsum/Berkah Mulyo — teknologi tidak berubah, jadi konvensi tetap berlaku:

### 8.1 Bahasa
- **Kode**: English (nama variable, function, class)
- **Comment**: Indonesian atau English (konsisten per file)
- **UI text**: Bahasa Indonesia (semua label, message, error)
- **Nama tabel & kolom**: mengikuti pola warisan (Indonesia, snake_case) — misal: `cabangs`, `karyawans`, `setorans`

### 8.2 Naming
| Item | Convention | Contoh |
|------|-----------|--------|
| Model | PascalCase, singular | `Cabang`, `Karyawan`, `Setoran` |
| Controller | PascalCase + Controller | `PosController`, `SetoranController` |
| Table | snake_case, plural | `cabangs`, `karyawans`, `setorans` |
| Column | snake_case | `nama_item`, `harga_jual` |
| Route | kebab-case | `/pos/kasir`, `/setoran/approve` |
| View | dot-notation, snake_case | `pos.kasir`, `setoran.index` |

### 8.3 Struktur Folder
```
app/
  Http/Controllers/       # semua controller
  Models/                 # semua model
  Services/               # business logic
  Traits/                 # HasAuditLog, HasCabang, FillsDeletedBy
  Observers/              # observer untuk history/audit
  Enums/                  # RoleUser, TipePembayaran, dll
resources/views/
  layouts/                # template utama
  pos/                    # view POS
  setoran/                # view setoran
  dashboard/               # view dashboard
database/
  migrations/             # migration files
  seeders/                # seeder files
```

### 8.4 Pattern Coding yang Dipakai
- **Observer pattern**: terdaftar di `AppServiceProvider` untuk history/audit
- **Trait `HasAuditLog`**: dipakai di banyak model
- **Trait `FillsDeletedBy`**: dipakai di banyak model
- **Gate pattern**: permission di-cache 1 jam (`all_permission_names`), delegasi ke `$user->hasPermission()`
- **DB::transaction()**: WAJIB untuk operasi multi-tabel
- **🔴 Nested `<form>` HTML DILARANG** — HTML5 parser membuang tag form bersarang tapi child input-nya (termasuk `_method`) tetap masuk ke form luar, bisa menyebabkan method-spoofing salah sasaran (pernah jadi bug KRITIS data-ghost di Dimsum). Kalau butuh tombol submit "di dalam" card form lain, pisahkan jadi sibling + pakai HTML5 `form="id-form-lain"` attribute.

---

## 9. TESTING & QUALITY

### 9.1 Sebelum commit/lanjut fitur
- [ ] Semua migration jalan tanpa error (`php artisan migrate:status`)
- [ ] Semua seeder jalan tanpa error
- [ ] Login berhasil pakai user admin
- [ ] Menu terkait fitur baru tampil di sidebar (kalau permission benar)
- [ ] Cek `storage/logs/laravel.log` — tidak ada error baru
- [ ] Manual test alur end-to-end (misal: input transaksi POS → cek stok berkurang → cek masuk laporan)
- [ ] Filter cabang: pastikan query pakai `where('cabang_id', ...)` (SECURITY!)

### 9.2 Aturan Scope & Metode Test (WAJIB)

**Scope — apa yang WAJIB ditest:**
1. Semua yang dibuat/diedit/diubah di sesi berjalan
2. Fitur existing yang **berpotensi terdampak** oleh perubahan struktur

**Metode — WAJIB simulasi HTTP request beneran, BUKAN cuma Tinker:**
- Pakai Laravel Feature Test (`tests/Feature/...`) dengan `actingAs($user)->get(...)`/`post(...)` + assertions
- Upload file: `UploadedFile::fake()` + `Storage::fake('public')`

**Database test — WAJIB pakai `erp_coffeeshop_test` (MySQL disposable), BUKAN sqlite in-memory default Laravel:**
- Codebase ini banyak migration raw MySQL-only (`ALTER TABLE ... MODIFY COLUMN ENUM`, dll) yang **tidak jalan di sqlite**
- Setup: `.env.testing` (`DB_CONNECTION=mysql`, `DB_DATABASE=erp_coffeeshop_test` — database terpisah dari `erp_coffeeshop` dev)
- Test class pakai trait `Illuminate\Foundation\Testing\DatabaseTransactions` (BUKAN `RefreshDatabase`)

**Kalau ada test FAILED:** STOP, investigasi dulu apakah itu bug nyata atau asumsi test yang salah, baru lanjut.

---

## 10. TROUBLESHOOTING UMUM

### 10.1 Error saat migrate
- Foreign key gagal → cek urutan file migration, tabel yang dirujuk harus dibuat DULU
- Index/constraint conflict → cek migration `fix_*_soft_delete_unique_constraint`

### 10.2 Error saat artisan
- "Table 'cache' doesn't exist" → set `CACHE_STORE=file` atau `database` di `.env`, jalankan `config:clear`
- ".env invalid" → biasanya `APP_NAME` yang ada spasi tapi tidak diapit `"..."`
- **MySQL/XAMPP belum jalan** → `SQLSTATE[HY000] [2002] No connection could be made` — cek `netstat -an | grep 3306`, start mysqld via XAMPP Control Panel kalau belum listening

### 10.3 Halaman blank / 500
- Cek `storage/logs/laravel.log`
- Cek `APP_DEBUG=true` di `.env` untuk lihat stack trace
- Cek permission folder `storage/` dan `bootstrap/cache/`

### 10.4 Menu tidak muncul
- Cek permission user (`users` → `role` → `role_permissions`)
- Cek blade sidebar (`@can` directive)
- Clear cache: `php artisan cache:clear`

### 10.5 Foto produk/upload tidak tampil (404) padahal sudah ke-upload
- Cek apakah `public/storage` beneran SYMLINK ke `storage/app/public`
- Kalau di production shared hosting (symlink diblokir provider), lihat solusi override `asset()` yang sudah dikerjakan di Dimsum (`CLAUDE.dimsum.reference.md` 4.18) — kemungkinan besar perlu direplikasi kalau Kopi Drip juga deploy ke shared hosting serupa

### 10.6 Data cabang lain kelihatan (SECURITY BUG!)
- Cek query di controller → apakah ada `where('cabang_id', ...)`
- Jangan pakai `Model::all()` mentah untuk data cabang-spesifik
- CabangScope DORMANT — filter WAJIB manual

---

## 11. KONTAK & GAYA KOMUNIKASI

- **Owner project**: user (yang chat dengan Claude)
- Semua keputusan bisnis (fitur, alur, prioritas) harus konfirmasi dulu ke owner
- Bahasa Indonesia, step-by-step, jelaskan konteks perubahan besar
- JANGAN langsung eksekusi perubahan besar — konfirmasi dulu (kecuali instruksi eksplisit "kerjakan sekaligus")

---

## 12. TODO LIST (untuk masa depan)

### 12.1 Fase D — Rebranding Warna CSS View — ✅ Selesai (2026-09-22)
- [x] Ganti hardcode warna hitam `#1A1A1A` + oranye `#FF6B00` + krim `#FFF8E7` (warisan Dimsum) → palette Kopi Drip (12 file, 64 occurrence)
- [x] Revisi: sidebar balik ke hitam `#1A1A1A` (bukan hijau tua `#1B4332`) — keputusan Owner, 7 file + `config/laravelpwa.php`, 16 occurrence. Palette final: hitam + accent hijau `#2D6A4F` + cream `#F5F0E6` (lihat 6.1)

### 12.2 Audit Sinkronisasi Stok 3 Level (belum dikerjakan)
- [ ] Level 1 — `item_cabang`: pastikan ketersediaan menu per outlet ter-setup benar (saat ini item baru dari seeder belum di-assign eksplisit ke cabang, fallback "aktif di semua cabang" berlaku — perlu direview apakah ini perilaku yang diinginkan untuk semua 44 item)
- [ ] Level 2 — resep bahan baku: `ResepBumbuSeeder` masih kosong (lihat 12.4), begitu diisi perlu dicek expand-nya benar ke stok
- [ ] Level 3 — `stock_transfers`: alur Gudang Pusat/Roastery → Outlet perlu ditest end-to-end dengan data Kopi Drip

### 12.3 Isi Stok Awal untuk Testing (belum dikerjakan)
- [ ] Dummy pembelian bahan baku (susu, gula, sirup, kemasan) via modul Pembelian (PO)
- [ ] Dummy stok Green Bean & hasil roasting awal (Roasted Bean) — bisa manual dulu sebelum Modul Roastery jadi (12.7)

### 12.4 Resep Produksi per Menu (belum dikerjakan, BUTUH INPUT OWNER)
- [ ] Perlu takaran real dari Owner: gram biji kopi, ml susu, gram gula, dll per gelas untuk tiap 20 menu kopi/manual brew/non-kopi
- [ ] `ResepBumbuSeeder.php` sengaja dibiarkan kosong sampai data ini tersedia (lihat 4.2 B5)
- [ ] Setelah resep terisi, POS baru bisa auto-potong stok bahan baku per transaksi

### 12.5 Setup Modul Roastery (design selesai 2026-09-24, menunggu approval Owner — lihat `FASE_ROASTERY_DESIGN.md`)
- [x] Desain model "Roasting Batch" — selesai, lihat `FASE_ROASTERY_DESIGN.md`
- [x] Jawaban Owner atas 5 pertanyaan + coding R1–R5 — ✅ selesai 2026-09-24 (lihat 4.20)
- [ ] **R6** — biaya operasional roasting (gas/listrik/tenaga kerja) masuk cost/kg
- [x] Distribusi awal roasted curah ke 5 outlet — ✅ selesai 2026-09-24 (lihat 4.21); sisa: roasting rutin untuk isi ulang stok outlet
- [x] Resep susu/gula/sirup/kemasan menu kopi, manual brew & non-kopi — ✅ selesai 2026-09-24 (lihat 4.22)
- [ ] Uji klik UI batch di browser; takaran es batu/sedotan/tas takeaway belum ada
- [ ] Perlu diskusi dengan Owner: berapa shrinkage rate tipikal, siapa yang input batch (Admin Gudang/Roastery), bagaimana alur stok Green Bean → Roasted Bean tercatat

### 12.6 Adaptasi Varian Menu Kopi (belum dikerjakan)
- [ ] Isi `ItemAttribute`/`ItemAttributeValue`/`ItemVariant` untuk "Kopi Sidikalang" (KPI-008, sudah `punya_varian=true`) dan menu kopi lain yang perlu varian
- [ ] Atribut: Size (S/M/L), Susu (Fullcream/Skim/Oat/Almond), Gula (Less/Normal/Extra), Es (Less/Normal/No Ice), Extra Shot
- [ ] Putuskan: `harga_override` per kombinasi varian (mis. susu oat +5rb, size L +3rb) — perlu konfirmasi harga dari Owner

### 12.7 Data Operasional yang Masih Placeholder (perlu diisi Owner via UI)
- [ ] Alamat & GPS 6 cabang — sekarang masih "TBD" (`CabangSeeder.php`)
- [ ] Rename 6 user placeholder "(TBD)" — Manajer/Kasir/Barista Outlet 1 & 2 — jadi nama karyawan real
- [ ] Isi data karyawan HR real (saat ini masih data dummy warisan `HRSeeder.php` — Budi Santoso, Siti Rahayu, dst, belum diganti nama real)

### 12.8 Keputusan Produk yang Perlu Diambil Owner
- [ ] **Modul/label "Frozen"** (tipe transaksi warisan Dimsum) — hapus total, atau ganti jadi delivery (Gojek/Grab, yang sudah dihapus dari enum `TipePembayaran` di Dimsum tapi bisa diaktifkan lagi kalau relevan untuk Kopi Drip)
- [ ] Review menyeluruh label di UI POS & tempat lain — pastikan tidak ada sisa istilah "Frozen"/dimsum-spesifik yang kelewat dari Fase A/B (butuh test manual di browser, bukan cuma grep source code, karena sebagian teks di-generate dinamis)

### 12.9 Fase E — QR Table Ordering (E1 selesai, E2-E7 menunggu instruksi lanjut)

Design lengkap: `FASE_E_QR_TABLE_ORDERING.md`. Estimasi total ~2 minggu.

- [ ] **E1** — Master Meja + QR Generator — 🟡 Backend & kelengkapan (sidebar/panduan/tooltip) selesai, gap ditemukan & difix (2026-09-23, lihat 4.8-4.9), TAPI belum dicentang selesai sampai Owner konfirmasi verifikasi visual di browser (login → menu Master Meja muncul di sidebar → tombol Cara Pakai & tooltip berfungsi)
- [x] **E2** — Public Menu Page — ✅ Selesai (2026-09-23): route guest tanpa auth, view mobile-first (vanilla CSS/JS), cart client-side, submit ke `order_queues`, halaman status + polling 3 detik. Semua verifikasi 3.7 PASS (route/submit/DB/polling). Bug QR-tidak-tampil-di-PDF (E1) sekalian difix. Detail lengkap lihat 4.10
- [x] **E3** — Kasir POS Extended — ✅ Selesai (2026-09-23): layout meja visual (grid warna status, timer live), approve/reject queue, tandai terisi, transfer bill (fungsional, UI dropdown polish di E4/E7), tambah item walk-in, print struk 2-halaman, bayar/close bill (reuse `PenjualanService`). Full E2E test PASS (order→approve→bayar→stok terpotong→kas bertambah). 3 bug + 1 blocker data (Kas) ditemukan & difix. Detail lengkap lihat 4.12
- **E4** — Bill Unification (Approach C Hybrid, lihat 4.13) — audit + audit dependency SELESAI, 4 sub-fase disepakati:
  - [x] **E4.1** — Migration `orders.meja_id` FK — ✅ Selesai (2026-09-23): kolom + FK + index, model relasi `meja()`, `nomor_meja` string dipertahankan (backward-compat). Detail lihat 4.13
  - [x] **E4.2** — POS dropdown meja — ✅ Selesai (2026-09-23). Detail lihat 4.14
  - [x] **E4.3** — Gabung Save Bill + Bayar + Cancel ke `BillService` — ✅ Selesai (2026-09-23). Detail lihat 4.14
  - [x] **E4.4** — Testing E2E — ✅ Selesai (2026-09-23), 7/7 skenario PASS termasuk split payment. Detail lihat 4.14
- [x] **E5** — Kitchen Display — ✅ Selesai (2026-09-23). Detail lihat 4.15
- [x] **E6** — Timer + Notifikasi + Estimasi — ✅ Selesai (2026-09-23). Detail lihat 4.16
- [x] **E7** — Testing E2E — ✅ Selesai autonomous (2026-09-24, lihat 4.17), 12 skenario PASS. Sisa: uji klik UI nyata di browser oleh Owner sebelum uji coba produksi

### 12.10 Audit Retroaktif Aturan 3.8

- [x] Inventarisasi seluruh menu authenticated existing — ✅ 90 link sidebar (2026-09-24)
- [x] Cek tiap menu: tombol Cara Pakai, konten panduan di DB, tooltip form — ✅
- [x] Fix gap: 19 permission, 13 panduan, 23 tooltip (6 menu), 12 tombol panduan — ✅ lihat 4.23
- [ ] Sisa (keputusan Owner): tooltip untuk Pelanggan, Permintaan Stok, Penilaian 360°, Pemakaian Perlengkapan, POS, Riwayat Order (sengaja di-skip); adaptasi panduan Program Loyalty dari "jasa giling" ke Kopi Drip; verifikasi visual di browser

---

## 13. CHECKLIST SEBELUM MULAI FITUR BARU

Setiap kali mulai kerja fitur baru, Claude Code WAJIB:

- [ ] Baca ulang bagian relevan di file ini
- [ ] Cek pola serupa di codebase warisan Dimsum/Berkah Mulyo (`CLAUDE.dimsum.reference.md` sebagai referensi implementasi kalau perlu)
- [ ] Konfirmasi scope & detail ke user (kalau belum jelas)
- [ ] Bikin plan (list file yang akan diubah/dibuat)
- [ ] Eksekusi dengan test bertahap
- [ ] Update section "Riwayat" (section 4) & TODO (section 12) di file ini
- [ ] Tambah permission + panduan + tooltip untuk fitur baru
- [ ] Cek filter cabang manual di query (SECURITY!)
- [ ] Kabari user ketika selesai & minta test

---

## 14. REFERENSI FILE PENTING

| File | Isi |
|------|-----|
| `CLAUDE.md` (ini) | Single source of truth Kopi Drip — visi, aturan, spesifikasi, riwayat |
| `CLAUDE.dimsum.reference.md` | Arsip CLAUDE.md dari `erp-dimsum` (D'mentai) — referensi implementasi & riwayat audit lengkap, TIDAK lagi jadi sumber kebenaran project ini |
| `CLAUDE.berkahmulyo.backup.md` | Arsip CLAUDE.md dari Berkah Mulyo (2 lapis warisan lebih jauh) — referensi historis |
| `.env` | Config lingkungan (APP_NAME, DB, dll) |
| `routes/web.php` | Peta URL sistem |
| `resources/views/layouts/app.blade.php` | Sidebar utama — peta menu sistem |
| `app/Providers/AppServiceProvider.php` | Observer + Gate + Recurring trigger |
| `bootstrap/app.php` | Middleware alias + routing config |

---

**🚧 PROJECT DALAM PENGEMBANGAN** — Fase A, B, D selesai (2026-09-22), Fase C (dokumentasi) sedang berjalan. Audit sinkronisasi stok menyusul. Modul Roastery & varian menu kopi masih perlu dibangun/diisi sebelum go-live.
