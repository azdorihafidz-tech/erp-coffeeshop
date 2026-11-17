<?php

/**
 * Kamus Menu — kelompok Roastery (khusus coffeeshop, Roastery V1 + V2 Farm-to-Cup).
 * Fakta diambil dari kode (RoastingBatchService, CherryPurchaseService, ProcessingBatchService,
 * GrindingBatchService, PackingBatchService). Kalau perilaku berubah, ubah juga teks di sini.
 */
$lokasi = "Semua transaksi Roastery baru dicatat di lokasi berkode **RST001** (cabang tipe Roastery). Lokasi itu harus ada di Pengaturan → Cabang & Gudang.";
$tanpaKas = "Menu ini **tidak mengubah kas** — biaya (beli buah, gas, upah) dicatat sendiri di Kas & Transaksi.";

return [

    [
        'slug' => 'roastery-dashboard', 'judul' => 'Dashboard Roastery', 'kelompok' => 'Roastery', 'urutan' => 10,
        'letak' => 'Roastery → Dashboard Roastery',
        'ringkasan' => 'Ringkasan roasting bulan ini (jumlah batch, green dipakai, roasted dihasilkan, yield rata-rata, cost/kg) dan grafik tren yield & waste 30 hari.',
        'izin' => ['roastery.dashboard.view'],
        'route_names' => ['roastery.dashboard'],
        'terkait' => ['batch-roasting', 'roasting-profile'],
        'kata_kunci' => 'dashboard roastery yield waste susut roasting cost per kg biji kopi',
        'konten' => <<<'MD'
## Fungsi
Memantau hasil roasting: berapa green bean terpakai, berapa roasted yang jadi, dan berapa modal per kg.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Dashboard Roastery**.

## Yang dihitung
- **Yield** = berat roasted ÷ berat green. **Waste** = green − roasted (termasuk susut air saat disangrai).
- Hanya batch roasting yang **selesai**.

## Hasil
Hanya menampilkan data.
MD,
    ],

    [
        'slug' => 'batch-roasting', 'judul' => 'Batch Roasting', 'kelompok' => 'Roastery', 'urutan' => 20,
        'letak' => 'Roastery → Batch Roasting',
        'ringkasan' => 'Mencatat penyangraian green bean menjadi roasted bean curah (kg): stok green berkurang, stok roasted bertambah dengan modal per kg dari green yang dipakai.',
        'izin' => ['roastery.batch.view'],
        'route_names' => ['roastery.batch.*'],
        'terkait' => ['roasting-profile', 'processing-batch', 'grinding-batch', 'packing-batch', 'transfer-stok', 'roastery-dashboard'],
        'kata_kunci' => 'batch roasting roasting sangrai green bean roasted bean curah yield susut roast level light medium dark cost per kg',
        'konten' => <<<MD
## Fungsi
Mengubah stok **green bean** menjadi stok **roasted bean curah** (kg) yang siap digiling, dikemas, ditransfer ke outlet, atau dipakai komposisi menu kopi.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Buat / Selesaikan / Batalkan Batch Roasting**; pengemasan lama (V1): **Pengemasan Roasted Bean**.
- {$lokasi}
- Green bean harus punya stok di lokasi itu. Sumber green: **in-house** (dari Processing Batch yang selesai), **dibeli** (dari PO supplier), atau **stok awal/seed**.
- **Roasting Profile** dibuat dulu (perkiraan susut per roast level).

## Alur
Buat (Draft: green, jumlah, profile, sumber) → **Selesaikan** (isi berat roasted yang benar-benar jadi) — atau **Batalkan** selama masih Draft.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Lainnya |
|---|---|---|
| Buat (Draft) | Belum berubah | Perkiraan hasil dari susut profile |
| **Selesaikan** | Green **berkurang** (FIFO); roasted curah **bertambah** | Modal per kg = modal FIFO green ÷ berat roasted. **Harga Beli Terakhir** roasted curah diperbarui (dipakai Transfer ke outlet) |
| Batalkan (Draft) | — | — |

{$tanpaKas}

## Wajib
- Isi berat roasted sesuai timbangan — yield & modal dihitung dari angka ini.
- Setelah roasting, kirim roasted curah ke outlet lewat **Transfer Stok** supaya menu kopi di outlet tidak "Habis".

## Menu yang mirip & bedanya
- **Grinding Batch** — menggiling roasted curah jadi bubuk.
- **Packing Batch** — mengemas roasted/bubuk ke pack jual (250 g / 500 g / 1 kg).
MD,
    ],

    [
        'slug' => 'roasting-profile', 'judul' => 'Roasting Profile', 'kelompok' => 'Roastery', 'urutan' => 30,
        'letak' => 'Roastery → Roasting Profile',
        'ringkasan' => 'Daftar profil sangrai (roast level, perkiraan susut %) yang dipilih saat membuat Batch Roasting.',
        'izin' => ['roastery.profile.view'],
        'route_names' => ['roastery.profile.*'],
        'terkait' => ['batch-roasting'],
        'kata_kunci' => 'roasting profile roast level light medium dark susut persen profil sangrai',
        'konten' => <<<'MD'
## Fungsi
Menyimpan pengaturan sangrai yang dipakai berulang. Bawaan: Light (susut 12%), Medium (15%), Dark (18%).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Roasting Profile**.

## Akibat
- Susut % hanya untuk **perkiraan** hasil di form Batch Roasting. Hasil sebenarnya diisi saat batch diselesaikan.
- Tidak mengubah stok atau kas.
MD,
    ],

    [
        'slug' => 'petani', 'judul' => 'Master Petani', 'kelompok' => 'Roastery', 'urutan' => 40,
        'letak' => 'Roastery → Master Petani',
        'ringkasan' => 'Daftar petani pemasok buah kopi (nama, kontak, kebun, koordinat) yang dipilih saat Beli Buah Kopi.',
        'izin' => ['petani.view'],
        'route_names' => ['petani.*'],
        'terkait' => ['beli-cherry', 'supplier'],
        'kata_kunci' => 'master petani petani kopi kebun pemasok buah kopi cherry koordinat lokasi kebun',
        'konten' => <<<'MD'
## Fungsi
Menyimpan data petani supaya asal buah kopi tercatat.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Master Petani**.
- Berlaku untuk semua lokasi.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Tambah / Edit | Hanya data petani |
| Nonaktifkan | Tidak bisa dipilih di Beli Buah Kopi baru; transaksi lama tetap |

## Menu yang mirip & bedanya
- **Supplier** — pemasok barang lewat Purchase Order (mis. green bean jadi, susu, cup).
MD,
    ],

    [
        'slug' => 'beli-cherry', 'judul' => 'Beli Buah Kopi', 'kelompok' => 'Roastery', 'urutan' => 50,
        'letak' => 'Roastery → Beli Buah Kopi',
        'ringkasan' => 'Mencatat pembelian buah kopi (cherry) dari petani: disetujui, lalu diterima — stok cherry bertambah sesuai berat yang benar-benar diterima.',
        'izin' => ['beli-cherry.view'],
        'route_names' => ['beli-cherry.*'],
        'terkait' => ['petani', 'processing-batch', 'kas-transaksi'],
        'kata_kunci' => 'beli buah kopi beli cherry buah kopi petani panen kualitas grade terima cherry susut cherry',
        'konten' => <<<MD
## Fungsi
1 transaksi = 1 jenis buah (Arabika/Robusta) dari 1 petani, dengan harga per kg dan kualitas (A/B/C).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Buat Transaksi Beli Buah Kopi**, **Setujui Beli Buah Kopi**, **Terima Buah Kopi (Stok Masuk)**, **Batalkan Beli Buah Kopi**.
- Petani dibuat dulu di **Master Petani**. {$lokasi}

## Alur
Draft → **Disetujui** → **Diterima** (atau Dibatalkan sebelum diterima).

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas |
|---|---|---|
| Buat / Setujui / Batalkan | — | — |
| **Terima** (isi berat diterima) | Stok buah kopi **bertambah sebesar berat diterima** (bisa kurang dari yang dipesan karena susut di jalan), harga = harga per kg | Tidak berubah |

{$tanpaKas}

## Menu yang mirip & bedanya
- **Processing Batch** — mengolah buah kopi jadi green bean.
- **Purchase Order** — membeli green bean jadi dari supplier.
MD,
    ],

    [
        'slug' => 'processing-batch', 'judul' => 'Processing Batch', 'kelompok' => 'Roastery', 'urutan' => 60,
        'letak' => 'Roastery → Processing Batch',
        'ringkasan' => 'Mengolah buah kopi (cherry) menjadi green bean lewat tahap fermentasi, pengeringan, hulling, dan sortir (Washed/Natural/Honey/Wine).',
        'izin' => ['processing-batch.view'],
        'route_names' => ['processing-batch.*'],
        'terkait' => ['beli-cherry', 'batch-roasting'],
        'kata_kunci' => 'processing batch pengolahan buah kopi cherry green bean washed natural honey wine anaerobic fermentasi drying jemur hulling sortir defect yield',
        'konten' => <<<MD
## Fungsi
Mencatat pengolahan buah kopi sampai menjadi green bean, lengkap dengan catatan setiap tahap dan modal per kg green.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Buat Processing Batch**, **Mulai Processing (Potong Cherry)**, **Update Tahap Processing**, **Selesaikan Sortir (Stok Masuk)**, **Batalkan Processing Batch**.
- Stok buah kopi Arabika harus ada (dari **Beli Buah Kopi**). {$lokasi}

## Alur
Draft → **Mulai** → (Honey/Wine: **Fermentasi** → ) **Pengeringan** → **Hulling** → **Sortir** → Selesai. Washed/Natural langsung ke Pengeringan.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Lainnya |
|---|---|---|
| **Mulai** | Buah kopi **berkurang** (FIFO); modalnya dicatat | Tahap berikutnya sesuai metode |
| Selesai fermentasi / pengeringan / hulling | — | Hanya tahap & catatan (mis. suhu fermentasi) |
| **Selesai Sortir** (isi berat green & defect) | Green bean **bertambah** | Yield & modal per kg green dihitung; **Harga Beli Terakhir** green diperbarui |
| **Batalkan** (sebelum selesai) | Buah kopi yang sudah terpotong **kembali** | — |

Yield buah basah → green bean yang wajar sekitar **15–20%** (mis. 10 kg buah → 1,8 kg green).

{$tanpaKas}

## Menu yang mirip & bedanya
- **Batch Roasting** — langkah berikutnya: green bean disangrai.
MD,
    ],

    [
        'slug' => 'grinding-batch', 'judul' => 'Grinding Batch', 'kelompok' => 'Roastery', 'urutan' => 70,
        'letak' => 'Roastery → Grinding Batch',
        'ringkasan' => 'Menggiling roasted bean curah menjadi bubuk (Medium/Fine/Extra Fine): stok roasted berkurang, stok bubuk bertambah dengan modal FIFO.',
        'izin' => ['grinding-batch.view'],
        'route_names' => ['grinding-batch.*'],
        'terkait' => ['batch-roasting', 'packing-batch'],
        'kata_kunci' => 'grinding batch giling bubuk kopi ground medium fine extra fine roasted curah',
        'konten' => <<<MD
## Fungsi
Mencatat penggilingan roasted bean menjadi bubuk kopi.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Buat / Selesaikan / Batalkan Grinding Batch**.
- Stok roasted curah harus ada. {$lokasi}

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok |
|---|---|
| Buat (Draft) | Belum berubah |
| **Selesaikan** (isi berat bubuk jadi) | Roasted curah **berkurang** (FIFO); bubuk **bertambah** dengan modal = modal roasted ÷ berat bubuk |
| Batalkan (Draft) | — |

{$tanpaKas}

## Menu yang mirip & bedanya
- **Packing Batch** — mengemas bubuk/biji ke pack jual.
MD,
    ],

    [
        'slug' => 'packing-batch', 'judul' => 'Packing Batch', 'kelompok' => 'Roastery', 'urutan' => 80,
        'letak' => 'Roastery → Packing Batch',
        'ringkasan' => 'Mengemas roasted bean / bubuk ke pack jual (250 g / 500 g / 1 kg). Stok pack bertambah dengan modal = modal bahan + biaya kemasan.',
        'izin' => ['packing-batch.view'],
        'route_names' => ['packing-batch.*'],
        'terkait' => ['grinding-batch', 'batch-roasting', 'pos', 'kiriman-cabang'],
        'kata_kunci' => 'packing batch kemas pack 250 gram 500 gram 1 kg biji kopi kemasan retail pack jual',
        'konten' => <<<MD
## Fungsi
Membuat stok **pack** siap jual (Produk Jual tanpa komposisi) dari roasted bean atau bubuk.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Buat / Selesaikan / Batalkan Packing Batch**.
- Stok bahan (roasted curah / bubuk) harus ada. {$lokasi}

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Lainnya |
|---|---|---|
| Buat (Draft) | Belum berubah | Pilih bahan, ukuran pack, jumlah pack |
| **Selesaikan** | Bahan **berkurang** sebesar berat pack × jumlah (FIFO); pack **bertambah** | Modal per pack = modal per kg bahan × berat pack + **biaya kemasan Rp 2.000** (tetap). Harga Beli Terakhir pack diperbarui |
| Batalkan (Draft) | — | — |

- Stok kemasan (kantong, label) **tidak** dipotong otomatis — biaya kemasan hanya angka tetap di modal.
- "Waste" di layar = sisa bahan yang belum dikemas; sisanya tetap di stok bahan.

{$tanpaKas}

## Setelah dikemas
Pack dijual dari **POS Roastery** (retail/wholesale) atau dikirim ke outlet lewat **Kirim ke Cabang**.
MD,
    ],
];
