# Rancangan Porting dari ERP Dimsum 4.50–4.53 ke ERP Coffeeshop

Sumber: `D:\xampp\htdocs\erp-dimsum\PORTING_DIMSUM_4.50-4.53.md` (folder dimsum hanya dibaca).
Status: **DISETUJUI OWNER 2026-11-16** ("setuju semua saran" K1–K4). Tahap A selesai (CLAUDE.md 4.57), Tahap B selesai (4.58).
Tanggal audit: 2026-11-16, program di commit `e3bc8c9`, data = salinan go-live di DB lokal `erp_coffeeshop`.

Keputusan bisnis di dokumen dimsum bagian 7 dianggap **sudah final** dan tidak ditanyakan ulang. Yang ditanyakan di bagian 9 hanya hal yang kondisinya **berbeda** di coffeeshop.

---

## 1. Ringkasan

Empat fitur dari dimsum akan dibuat juga di coffeeshop:

1. **Akun Tim IT.** Akun tersembunyi di atas Owner, untuk tim IT yang merawat aplikasi. Setiap tindakannya tercatat, dan catatan itu hanya bisa dilihat sesama Tim IT.
2. **Paket modul & masa langganan.** Tim IT menentukan modul yang aktif per instalasi dan sampai kapan langganan berlaku. Setelah masa tenggang habis, aplikasi menjadi **baca-saja**.
3. **Stok Opname lengkap.** Hitung fisik, rincian penyebab selisih, persetujuan, stok rusak, ganti rugi karyawan, laporan kerugian, dashboard, dan pengingat.
4. **Kamus Menu.** Penjelasan untuk **semua** menu sidebar: fungsi, syarat supaya muncul, alur, dan dampak tiap tombol. Isinya bisa dicari.

**Hasil audit terpenting:**
- **Ke-15 bug yang ditemukan dimsum juga ada di coffeeshop.** Coffeeshop di-fork dari dimsum sebelum bug itu diperbaiki.
- Ada **1 bug baru yang cukup serius** (bagian 3, no. 16): Laba Rugi Formal di semua instalasi **tidak menghitung beban apa pun** yang dicatat lewat Kas & Transaksi.

Setelah deploy, semua instalasi (go-live, demo, WONGKO PI) otomatis mendapat **semua modul tanpa batas waktu**, jadi tampilan dan cara kerja yang sudah jalan tidak berubah. Perbaikan bug memang mengubah beberapa perilaku (misalnya Bayar gaji jadi mengurangi kas, dan Laba Rugi Formal jadi memuat beban). Semuanya tertulis di bagian 3 dan 8.

---

## 2. Kondisi coffeeshop yang ditemukan

| Bagian | Kondisi coffeeshop | Beda dengan dimsum | Akibat untuk porting |
|---|---|---|---|
| **Role** | Kolom `users.role` berupa **ENUM** 7 role (owner, admin_pusat, admin_gudang, manajer_cabang, kasir, operator_produksi, helper). **Tidak ada role buatan.** | Dimsum: kolom teks + role buatan (4.38) | Role `teknisi` butuh **migration ubah ENUM** (dengan `down()` aman). Daftar role di Role & Hak Akses, form User, dan form Karyawan diambil dari `RoleUser::cases()`, jadi perlu satu fungsi "role untuk pelanggan" yang tidak memuat Tim IT. |
| **Cek Owner** | `Gate::before` dan `hasPermission`/`getPermissions` di User. Ada **49 pengecekan Owner di 30 file** dan **16 grup route** `role:owner…`. | Serupa (dimsum 26 tempat) | Semua diganti `aksesPenuh()` (Owner atau Tim IT), kecuali penerima notifikasi Owner dan penjaga "Owner terakhir". |
| **Stok** | FIFO per batch (`stock_batches`), kartu stok (`stock_movements` + referensi), qty desimal 3 angka. Barang **global** + ketersediaan per cabang (`item_cabang`). Tidak ada barcode maupun kedaluwarsa per batch. Lokasi = cabang bertipe cabang/gudang_pusat/roastery/head_office. | Dimsum: barang per cabang (4.39) | Cakupan opname = barang yang punya stok di lokasi itu (termasuk pack Roastery yang tipenya Produk Jual) + barang fisik aktif. "Scan" = ketik/scan kode barang + Enter. |
| **Penyesuaian Stok** | Alasan salah_hitung/audit **tidak** dicatat di keuangan, baik saat stok naik maupun turun. | Sama dengan dimsum sebelum perbaikan | Bug no. 15 diperbaiki (bagian 3). |
| **Fitur bersinggungan** | Produksi, Kiriman Cabang, Retur Cabang, Transfer, POS, Roastery (processing/roasting/grinding/packing), **Closing Periode** (kunci per cabang per tanggal lewat `ClosingLockService` + trait `TerkunciClosing`, transaksi keuangan di periode terkunci ditolak). | Dimsum: Closing + Tutup Buku | Persetujuan opname / tindakan stok rusak di hari yang sudah di-Closing Final → ditolak dengan pesan jelas (dicek di awal, bukan setengah jalan). |
| **Keuangan** | 1 baris per kejadian (`transaksi_keuangans`), kas per cabang, COA 59 akun. Laba Rugi Formal **menjumlah per akun tanpa melihat masuk/keluar** (sama dengan dimsum). | Sama | Pemasukan tidak boleh memakai kategori beban; pembatalan kerugian = baris minus di akun beban yang sama. Akun baru 6-1402…6-1406, 6-1501, 7-1103, 7-1104 **belum dipakai**, jadi bisa dibuat dengan nomor yang sama dengan dimsum. |
| **Penggajian** | Tombol **Bayar** hanya mengubah status, **kas tidak berkurang**. Generate ulang **menimpa** slip yang sudah Disetujui/Dibayar. Potongan telat per menit tidak dipakai. | Sama dengan dimsum sebelum 4.51 | Ganti rugi "potong gaji" butuh Bayar yang mengurangi kas → diperbaiki dulu (bug 8–10). Data go-live: **0 karyawan, 0 slip**, jadi tidak ada data lama yang terdampak. |
| **Notifikasi / log** | `NotificationService` (+ `getOwnerAndAdminPusat`), spatie activitylog, menu Audit Log, Pengaturan Umum (identitas + warna). | Sama | Dipakai ulang. |
| **Tugas terjadwal** | Hosting cPanel tanpa terminal. Transaksi Berulang dipicu saat aplikasi dibuka (`triggerRecurringIfNeeded`). `routes/console.php` punya jadwal, tapi kemungkinan besar tidak pernah jalan. | Sama | Pengingat opname memakai pola yang sama (dipicu saat aplikasi dibuka, sekali per jam) + `Schedule::call()` cadangan. |
| **Sidebar** | **105 link** di 13 bagian, termasuk **Roastery (8 menu)**, Meja & QR (Master Meja, Layout Meja, Antrian Kasir QR, Kitchen Display), dan Closing Periode. | Dimsum 102 link, tanpa Roastery & QR | Peta modul paket dan Kamus dibuat ulang untuk menu coffeeshop. |
| **Tes** | DB tes `erp_coffeeshop_test` + `DatabaseTransactions`. Baseline: **69 gagal lama** (sebagian besar tes era Dimsum Tahap 2.5/5/6/7 yang bergantung data contoh). | Dimsum 10 gagal | Setiap tahap dianggap lulus kalau yang gagal hanya ke-69 tes lama itu. |

---

## 3. Daftar bug (cek dari daftar dimsum + temuan baru)

Semua dicek langsung di kode coffeeshop.

| No | Bug | Ada di coffeeshop? | Data go-live terdampak | Perbaikan (sama dengan dimsum kecuali disebut) |
|---|---|---|---|---|
| 1 | Edit order yang sudah dibayar: diskon/metode bisa diubah, observer menimpa jumlah transaksi kas tanpa mengubah saldo kas & setoran | **Ada.** Owner boleh edit order Selesai. | 0 order | Diskon & metode bayar hanya untuk order Pending; observer hanya menyamakan keterangan. |
| 2 | Hapus pelanggan ikut menghapus order-nya | **Ada** (tercatat di 4.48, menunggu keputusan) | 0 pelanggan | Hanya pelanggan yang dihapus; tetap ada saklar Aktif (kolomnya sudah ada). |
| 3 | Hapus barang ikut menghapus riwayat order/PO/stok | **Ada** | 4 barang terhapus (dicek saat deploy) | Ditolak kalau masih ada stok / dipakai komposisi / Resep Produksi / PO aktif; riwayat tetap. |
| 4 | Item Tambahan (tambahan_gratis / produk_tambahan) tidak memotong stok di POS | **Ada** | dicek saat deploy | Aturan potong stok bersama (`dipotongStokSendiri`). |
| 5 | Laba Rugi "per jenis menu" selalu "-" | **Ada** (produk tidak punya kolom jenis menu) | — | Kolom Jenis Menu di Produk Jual + simpanan saat order. |
| 6 | Dashboard Semua Cabang / Gudang bisa dibuka lewat alamat langsung | **Ada** | — | Cek akses di controller. |
| 7 | Form Edit Karyawan: email/role akun bisa diubah tanpa izin user, dan akun Owner bisa terubah | **Ada** | 0 karyawan | Butuh izin user.*, akun Owner tidak bisa diubah dari form Karyawan. |
| 8 | Bayar gaji tidak mengurangi kas | **Ada** | 0 slip | Bayar → pilih kas → transaksi Gaji; ada Batal Bayar. |
| 9 | Generate ulang slip menimpa slip Disetujui/Dibayar | **Ada** | 0 slip | Slip terkunci dilewati dan disebutkan namanya. |
| 10 | Potongan telat per menit tidak dipakai | **Ada** | 0 slip | Dipakai di slip. |
| 11 | Transaksi Berulang mengabaikan frekuensi, bisa dobel | **Ada** | 0 template | Jadwal per frekuensi, 1 transaksi per periode. |
| 12 | Hapus cabang menyembunyikan semua data cabang itu | **Ada** | — | Ditolak kalau cabang sudah beroperasi (pakai Nonaktifkan). |
| 13 | Neraca menghitung stok milik barang yang sudah dihapus | **Ada** | dicek saat deploy | Dikecualikan. |
| 14 | Laporan kas menjumlah transfer internal/setoran/saldo awal sebagai pemasukan/pengeluaran; `?cabang_id=` bisa membuka cabang lain | **Ada** | — | Transaksi internal dikecualikan + info; filter cabang dipaksa sesuai akses. |
| 15 | Penyesuaian Stok salah hitung/audit yang menurunkan stok tidak tercatat di keuangan | **Ada** | — | Dicatat sebagai Selisih Persediaan (6-1406). |
| **16 (baru)** | **Semua kategori transaksi tidak punya akun COA** (`kode_akun_coa` kosong di 20 kategori, termasuk Gaji, Sewa, Operasional, Lainnya, Beban Kerugian Stok). Penyebabnya: `KategoriCoaBackfillSeeder` tidak pernah terdaftar di DatabaseSeeder maupun Update Database. **Akibatnya Laba Rugi Formal, Buku Besar & Laporan Eksekutif tidak memuat beban apa pun yang dicatat lewat Kas & Transaksi**, hanya penjualan dan HPP. | **Ada di semua instalasi** (juga DB tes) | go-live: 1 transaksi berkategori | Jalankan seeder itu (aman, hanya mengisi yang masih kosong) di deploy & DatabaseSeeder. Tes yang memastikan beban muncul di Laba Rugi Formal. |

| **17 (baru, ditemukan saat Tahap A)** | Kasir **tanpa cabang** membuka Layout Meja / Antrian Kasir → error 500 (`KasirMejaController::resolveCabangId()` mengembalikan null) | **Ada** | 2 akun kasir contoh (`kasir.o1`, `kasir.o2`) tanpa cabang | Tampilkan pesan "akun belum punya cabang", bukan error. |

**Usulan:** semua diperbaiki dalam **Tahap P**, sebelum Stok Opname. Alasannya:
- Stok Opname bergantung pada no. 8 (ganti rugi potong gaji), no. 15, dan no. 16 (akun COA beban).
- Kamus nanti ditulis dari kode yang sudah benar.

---

## 4. Tahap A — Akun Tim IT

Mengikuti dimsum 4.53 Tahap A (termasuk perubahan `c5ae530`). Yang disesuaikan untuk coffeeshop:

- **Migration** `users.role` ENUM + `teknisi`.
  - `down()` menolak jalan kalau masih ada akun Tim IT, supaya akun tidak rusak.
  - `RoleUser::Teknisi` (label "Tim IT", akses semua cabang).
  - `RoleUser::untukPelanggan()` = semua role kecuali Teknisi. Dipakai Role & Hak Akses, form User, form Karyawan, RolePermissionSeeder, dan pilihan role lainnya.
- **`User::aksesPenuh()`** menggantikan 49 pengecekan Owner di 30 file, termasuk:
  - `Gate::before`, hasPermission, getPermissions;
  - kunci Closing (buka kunci), Update Database, Reset Go-Live;
  - Neraca, edit/batal order lintas hari, Data Terhapus.
  - `RoleMiddleware`: Tim IT lolos grup `role:owner…` (16 grup).
  - Yang **tetap** khusus Owner: penerima notifikasi Owner dan penjaga "Owner terakhir".
- **Tersembunyi dari pelanggan**, dengan `User::scopePelanggan()`:
  - Manajemen User (akun Tim IT → 404);
  - pilihan kepala cabang, penghitung/penyetuju opname, dan semua daftar user lain.
  - Owner tidak bisa mengubah atau menghapus akun Tim IT.
- **Menu Pengaturan → Tim IT** (`tim-it.*`, khusus Tim IT): tambah, ganti kata sandi (min 12 huruf + angka), aktif/nonaktif. Tidak bisa menonaktifkan diri sendiri atau akun aktif terakhir.
- **Keamanan → Aktivitas Tim IT** (khusus Tim IT):
  - Middleware mencatat setiap non-GET oleh Tim IT (route, url, method, IP, status). Isi formulir tidak disimpan.
  - Listener Login ikut mencatat.
  - Audit Log menyembunyikan catatan Tim IT dari user pelanggan.
- **Akun Tim IT pertama** dibuat skrip deploy dari `.env` (`TEKNISI_NAMA`, `TEKNISI_EMAIL`, `TEKNISI_PASSWORD`), **dibaca langsung dari file `.env`**. Kata sandi tidak dicetak. Kata sandi **berbeda di tiap instalasi** (go-live, demo, WONGKO PI).
- Verifikasi dua langkah **ditunda** (keputusan dimsum).

## 5. Tahap B — Paket modul & masa langganan

Mengikuti dimsum 4.53 Tahap B–C:
- tabel `lisensi` bersegel HMAC `APP_KEY`;
- `App\Support\Paket` sebagai titik pusat;
- penolakan modul mati di Gate/hasPermission, termasuk untuk Owner & Tim IT;
- middleware `CekPaket`;
- Role & Hak Akses menyembunyikan izin modul mati dan mempertahankan centangan tersembunyi;
- pita langganan H-14 → tenggang 7 hari → `ModeBacaSaja`;
- segel rusak = mode lunak;
- penjaga `PaketSidebarGuardTest`, dan `Paket::lupakan()` di `TestCase`.

**Daftar modul coffeeshop (usulan, dibuat ulang dari 105 link sidebar):**

**Inti (selalu aktif):** Dashboard, Cabang, User, Role & Hak Akses, Pengaturan Umum, **Kas & Transaksi + Kelola Kas** (POS membutuhkannya), Kamus Menu, Panduan, Keamanan (backup, data terhapus, Audit Log), Update Database, Notifikasi, Profil.

| Modul | Menu yang ikut | Butuh modul |
|---|---|---|
| **Penjualan & POS** | POS, Riwayat Order, Pelanggan, Jenis Menu, Master Bumbu Pusat, Laporan Transaksi/Produk/Kategori kasir, Laporan Penjualan, Setoran Kasir, **Label Cup** | — |
| **Meja & Pesan QR** *(khusus coffeeshop)* | Master Meja, Layout Meja, Antrian Kasir (pesanan QR), Kitchen Display, halaman menu QR pelanggan | Penjualan |
| **Antrian Produksi** | Cek/Kelola Antrian, Mode Produksi, Display TV | Penjualan |
| **Program Loyalty** | Program Loyalty | Penjualan |
| **Stok & Gudang** | Dashboard Stok, Stok Barang, Penyesuaian, Permintaan & Transfer Stok, Bahan Baku, Produk Jual, Semua Barang, Pemakaian Perlengkapan, Laporan Stok / Perlengkapan / Konsumsi Bahan | — |
| **Produksi** | Produksi & Resep Produksi | Stok |
| **Kirim ke Cabang & Retur** | Kiriman Cabang, Retur Cabang, Jenis Pembeli di POS gudang | Stok, Penjualan |
| **Stok Opname** | Stok Opname, Stok Rusak, Laporan Kerugian Stok, Ganti Rugi Stok | Stok |
| **Roastery** *(khusus coffeeshop)* | Master Petani, Beli Buah Kopi, Processing Batch, Roasting Profile, Batch Roasting, Grinding, Packing, Dashboard Roastery | Stok |
| **Pembelian** | Purchase Order, Supplier, Dashboard PO | Stok |
| **Keuangan Lanjutan** | Transfer Antar Kas, Transfer Dana, Transaksi Berulang, Kategori Transaksi, **Closing Periode**, Analisis BEP, Dashboard Keuangan, Laporan Keuangan / Saldo Kas / Setoran / Audit Bukti / Setoran Harian | — |
| **Akuntansi** | COA, Neraca, Laba Rugi Formal, Buku Besar, Laporan Eksekutif | Keuangan Lanjutan |
| **Analisis Bisnis** | Laporan BEP & BEP Otomatis, Simulator BEP, Jam Ramai, Laba Rugi Produksi, Cabang vs Cabang, Dashboard Semua Cabang (analitik) | Penjualan |
| **SDM & Absensi** | Karyawan, Absensi (scan, manual, wajah, device), Shift, Hari Libur, Cuti, Laporan Absensi | — |
| **Penggajian** | Penggajian, Pengaturan Penggajian | SDM |
| **Penilaian 360°** | Evaluasi | SDM |
| **Aset** | Manajemen Aset, penyusutan, Laporan Aset | — |

**Paket cepat:**
- **Dasar** = Penjualan + Stok.
- **Menengah** = Dasar + Meja & QR, Antrian, Loyalty, Pembelian, Keuangan Lanjutan, SDM, Penggajian, Stok Opname.
- **Lengkap** = semua.
- Paket hanya tombol cepat; modul tetap bisa diatur satu per satu.

**Batasan yang perlu diketahui:** halaman menu QR untuk pelanggan (tanpa login) ikut ditutup kalau modul Meja & QR mati.

## 6. Tahap P — Perbaikan bug (prasyarat Stok Opname)

Ke-16 bug di bagian 3, diperbaiki dengan pola dimsum 4.50/4.51. Tiap perbaikan diberi:
- feature test;
- marker panduan `<!-- PERBAIKAN-450/451 -->` di panduan yang terkait.

Ini satu-satunya tahap yang **mengubah perilaku yang sudah dipakai**. Daftar perubahannya:

- **Bayar gaji:** sekarang wajib memilih kas dan saldo kas berkurang. Ada tombol Batal Bayar.
- **Edit order Selesai:** diskon dan metode bayar tidak bisa diubah lagi. Caranya: Batalkan, lalu buat order baru.
- **Hapus barang / cabang / pelanggan:** ditolak kalau masih dipakai. Pesannya menyarankan Nonaktifkan.
- **Laba Rugi Formal (no. 16):** mulai memuat beban dari Kas & Transaksi. Angka laba di laporan itu akan **turun** sebesar beban yang dicatat; angka barulah yang benar.
- **Laporan Keuangan (menu Keuangan):** transfer antar kas, setoran, dan saldo awal tidak lagi dihitung sebagai pemasukan/pengeluaran usaha.

## 7. Tahap C — Stok Opname

Mengikuti dimsum 4.52 Tahap A–E apa adanya: prinsip uang, alur, penyebab & akun, stok rusak, ganti rugi, laporan, dashboard, pengingat, dan semua keputusan P1–P15. Yang disesuaikan:

- **Cakupan "semua barang"** = barang yang punya stok di lokasi itu (termasuk pack Roastery yang tipenya Produk Jual) + barang fisik aktif (bahan baku, kemasan, tambahan gratis). Cakupan per kategori dan barang tertentu tetap ada.
- **Lokasi** = semua tipe kecuali Head Office: outlet, Gudang Pusat, Roastery.
- **Penyebab "Freezer mati"** diganti label **"Kulkas / pendingin mati"** (susu, sirup). Akun tetap Kerugian Stok – Rusak.
- **Penolakan hari yang sudah di-closing** memakai `ClosingLockService::kunciUntuk(cabang, tanggal)` milik coffeeshop. Persetujuan dan tindakan stok rusak yang berkeuangan ditolak dengan pesan "Buka kunci Closing Periode atau setujui setelah periode itu".
- **Satuan desimal** (kg, gram, liter, ml, dst.) memakai daftar faktor yang sama dengan `ResepBumbuItem::konversiKeSatuanStok`.
- **Penyetuju bawaan:** Owner + Admin Pusat. Penghitung tidak boleh menyetujui opname yang ia hitung sendiri, kecuali Owner/Tim IT.
- **Laporan Kerugian Stok** masuk menu Laporan, dan dipetakan ke modul Stok Opname.
- **Ganti rugi potong gaji** memakai Penggajian yang sudah diperbaiki di Tahap P.

## 8. Tahap D — Kamus Menu + Tahap E — aturan & deploy

**Tahap D:** tabel `kamus_menus`, isi di `database/seeders/kamus/{kelompok}.php`.
- Entri untuk **semua 105 link sidebar** + menu baru (Tim IT, Aktivitas Tim IT, Paket, Stok Opname, Dashboard Opname, Stok Rusak, Ganti Rugi, Laporan Kerugian) + kelompok **Cara Melakukan**. Perkiraan **±125 entri**.
- Penjaga `KamusMenuTest`: setiap link sidebar wajib punya entri.
- Tombol Kamus di topbar.
- Kelompok baru dibanding dimsum: **Roastery**, **Meja & Pesan QR**, dan Closing Periode versi coffeeshop.
- Kalau saat menulis Kamus ditemukan bug lain, bug itu **dilaporkan dulu** ke Owner sebelum diperbaiki.

**Tahap E:**
- **Aturan permanen** (dimsum bagian 8) ditambahkan ke CLAUDE.md coffeeshop bagian 3.6 dan checklist bagian 13.
- **Satu skrip deploy gabungan sekali pakai:**
  - isi: semua migration baru + seeder (Kamus, izin opname, paket, COA backfill) + akun Tim IT dari `.env` + laporan baca-saja;
  - pengaman: konfirmasi `?confirm=…`, lock di `storage/`, dan file dihapus setelah jalan.
- **Gladi** di salinan DB (migration baru dimundurkan). Lalu deploy **demo → go-live → WONGKO PI**, dengan link langsung per instalasi.

---

## 9. Pertanyaan yang perlu Bapak putuskan

Hanya hal yang kondisinya berbeda dari dimsum. Saran ada di kolom kanan; kalau setuju semua, cukup jawab **"setuju semua saran"**.

| No | Pertanyaan | Saran |
|---|---|---|
| **K1** | Pembagian modul paket (bagian 5). Ada 2 modul khusus coffeeshop: **Roastery** dan **Meja & Pesan QR**. Label Cup masuk modul Penjualan; Closing Periode masuk Keuangan Lanjutan. | Setuju seperti tabel. |
| **K2** | Bug no. 1–16 diperbaiki semua di **Tahap P** sebelum Stok Opname (dimsum memperbaikinya sambil menulis Kamus). | Ya, semua di Tahap P. Urutan kerja jadi **A → B → P → C → D → E**. |
| **K3** | Bug no. 16 mengubah angka **Laba Rugi Formal** di go-live (beban mulai terhitung, laba di laporan itu turun ke angka sebenarnya). Closing Periode yang sudah dikunci **tidak** berubah, karena isinya arsip. | Perbaiki. Saya sebutkan di laporan deploy supaya Bapak tahu kenapa angkanya berubah. |
| **K4** | Akun Tim IT: email & kata sandi diisi Bapak sendiri di `.env` tiap instalasi (3 tempat), **kata sandi berbeda per instalasi**. Satu akun per orang IT. | Ya. Saya siapkan contoh baris `.env`; kata sandinya Bapak yang membuat. |

---

## 10. Risiko & penanganan

| Risiko | Penanganan |
|---|---|
| Mengubah 49 pengecekan Owner | Satu fungsi `aksesPenuh()`, tiap tempat diuji; perilaku Owner tidak berubah. |
| Migration ENUM role gagal / rollback merusak akun | `down()` menolak kalau ada akun Tim IT. Rollback diuji di DB tes. |
| Izin/route lupa dipetakan ke modul | Dianggap inti (selalu aktif). Penjaga sidebar per modul. |
| Mode baca-saja memblokir POS | Instalasi lama = tanpa batas. Pita peringatan H-14 + tenggang 7 hari. Tim IT tidak terkena. |
| Kode yang jalan di semua halaman (pita langganan, badge opname, catatan Tim IT) sebelum Update Database | Cek tabel/kolom dulu (aturan 4.51). Diuji dengan migration dimundurkan. |
| Persetujuan opname di hari yang sudah di-Closing | Dicek di awal, tolak utuh. |
| Stok keburu terjual sebelum opname disetujui | Diterapkan selisih; kalau stok sudah tidak cukup → tolak utuh dengan pesan. |
| Angka Laba Rugi Formal berubah (no. 16) | Disampaikan ke Owner; arsip Closing tidak berubah. |
| Waktu pengerjaan panjang (±125 entri Kamus) | Dikerjakan per tahap dengan commit & push terpisah sesuai urutan. |

---

## 11. Hasil tes awal (sebelum porting)

Regresi lengkap (tanpa tes RefreshDatabase) pada 2026-11-16, commit `e3bc8c9`: **465 lulus, 69 gagal**. Daftar yang gagal sama persis dengan baseline sebelumnya (0 baru). Semuanya tes lama era Dimsum yang bergantung data contoh/perilaku lama, dan bukan bagian pekerjaan ini:

| Kelas tes | Gagal |
|---|---|
| Tahap7\Bug2BillTersimpanTest | 9 |
| Tahap5\SetoranKasirHttpTest | 9 |
| Tahap25\MasterProdukJualHttpTest | 7 |
| Tahap25\PosImpactTest | 6 |
| Tahap7\BepLoyaltyExtendTest | 5 |
| Tahap6\DashboardOwnerHttpTest | 5 |
| Tahap25\MasterBahanBakuHttpTest | 5 |
| Tahap7\HapusGojekGrabTest | 4 |
| Tahap25\SchemaAndKlasifikasiTest | 4 |
| Tahap7\PanduanButtonEmbedTest | 3 |
| Tahap7\ErrorPagesAndLogoSyncTest | 3 |
| Tahap7\KodeKategoriWajibTest | 2 |
| Tahap7\EndToEndFlowTest | 2 |
| Tahap25\ItemLegacyRouteTest | 2 |
| Tahap7\RenameJenisMenuMasterBumbuPusatTest, ImportBumbuPusatTest, Tahap25\MasterResepBumbuRedirectTest | 1 + 1 + 1 |

Setiap tahap dianggap lulus regresi kalau yang gagal hanya ke-69 tes ini. Kalau sebuah perbaikan di Tahap P membuat salah satunya lulus, itu dicatat sebagai bonus, bukan masalah.
