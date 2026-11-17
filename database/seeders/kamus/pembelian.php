<?php

/**
 * Kamus Menu — kelompok Pembelian (porting dimsum 4.50, disesuaikan coffeeshop:
 * tanpa Supplier Internal / PO ke Gudang; Terima PO memperbarui Harga Beli Terakhir).
 * Fakta diambil dari kode. Kalau perilaku menu berubah, ubah juga teks di sini.
 */
return [

    [
        'slug' => 'purchase-order', 'judul' => 'Purchase Order (PO)', 'kelompok' => 'Pembelian', 'urutan' => 10,
        'letak' => 'Pembelian → Purchase Order',
        'ringkasan' => 'Mencatat pembelian ke supplier: buat PO, setujui, kirim, terima barang (stok bertambah), lalu catat pembayarannya.',
        'izin' => ['pembelian.view'],
        'route_names' => ['pembelian.index', 'pembelian.create', 'pembelian.show', 'pembelian.cari-transaksi'],
        'terkait' => ['supplier', 'dashboard-po', 'bahan-baku', 'kas-transaksi', 'cara-menambah-stok', 'cara-memindahkan-barang'],
        'kata_kunci' => 'purchase order po beli belanja pembelian supplier terima barang barang datang bayar supplier hutang pembelian mendesak pembelian langsung harga po green bean biji kopi susu',
        'konten' => <<<'MD'
## Fungsi
Mencatat pembelian barang ke supplier sampai barangnya masuk stok. PO yang sudah diterima menjadi **batch stok berharga** — harga inilah yang menjadi modal (HPP) saat barang dipakai.

## Supaya menu ini muncul & bisa dipakai
- Setiap langkah punya centangan sendiri: **Lihat Pembelian**, **Buat PO**, **Approve PO**, **Kirim PO ke Supplier**, **Terima Barang dari PO**, **Hapus Purchase Order** (juga untuk Batalkan), **Batal Bayar PO**.
- **Lokasi Tujuan** = cabang/gudang/roastery yang menerima barang. Barang yang bisa dipilih: Bahan Baku, Kemasan, Tambahan Gratis (Produk Jual tidak bisa dibeli lewat PO — stoknya dihitung dari bahan).
- **Supplier** harus dibuat dulu di menu Supplier.
- Harga awal di form = **Harga Beli Terakhir** barang. Kalau barang punya Unit Beli (pack/dus/karton), pilih unitnya — jumlah & harga disimpan dalam satuan pakai.

## Alur
Draft → **Disetujui** → **Dikirim Supplier** → **Diterima** (atau Dibatalkan sebelum dikirim).
Centang "Pembelian Langsung / Mendesak" hanya memberi tanda dan notifikasi khusus — alurnya tetap sama.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas | Lainnya |
|---|---|---|---|
| Simpan / Approve / Kirim ke Supplier | — | — | Notifikasi ke yang berwenang |
| **Terima** (boleh isi jumlah diterima per barang) | Lokasi tujuan **bertambah**, batch baru dengan harga PO | — | **Harga Beli Terakhir** barang diganti harga PO (dipakai perkiraan modal komposisi & harga transfer stok) |
| **Catat Pembayaran** (setelah Diterima) | — | Transaksi di Kas & Transaksi yang tertaut ke PO → kas yang dipilih **berkurang** | Status PO jadi "Sudah Dibayar" |
| Batal Bayar | — | Uang kembali ke kas, transaksi pembayaran dihapus | Perlu centangan Batal Bayar PO |
| Batalkan | — | — | Hanya saat Draft/Disetujui |

**Terima barang tidak mengurangi kas.** Uang baru keluar saat Catat Pembayaran.

## Wajib
- Isi harga sesuai satuan yang dipilih (per pack kalau unit pack).
- Setelah barang datang, **klik Terima** — tanpa itu stok tidak bertambah.

## Jangan
- PO tidak bisa diedit. Kalau salah sebelum dikirim, **Batalkan** lalu buat ulang. PO yang sudah Diterima tidak bisa dibatalkan — koreksi stoknya lewat Penyesuaian Stok / Stok Opname.
- Transaksi pembayaran PO tidak bisa diedit/dihapus dari Kas & Transaksi (ditolak) — pakai **Batal Bayar** di detail PO, lalu catat ulang.

## Menu yang mirip & bedanya
- **Permintaan Stok / Transfer** — memindahkan bahan antar lokasi sendiri tanpa pembayaran.
- **Kiriman Cabang** — gudang/roastery menjual produk ke cabang lewat POS.
- **Roastery → Beli Buah Kopi** — pembelian buah kopi (cherry) dari petani, alurnya sendiri.
MD,
    ],

    [
        'slug' => 'supplier', 'judul' => 'Supplier', 'kelompok' => 'Pembelian', 'urutan' => 20,
        'letak' => 'Pembelian → Supplier',
        'ringkasan' => 'Daftar pemasok yang dipilih saat membuat PO.',
        'izin' => ['pembelian.view'],
        'route_names' => ['supplier.*'],
        'terkait' => ['purchase-order', 'petani'],
        'kata_kunci' => 'supplier pemasok vendor toko langganan',
        'konten' => <<<'MD'
## Fungsi
Menyimpan data pemasok yang dipilih saat membuat PO.

## Supaya menu ini muncul & bisa dipakai
- Hanya role **Owner, Admin Pusat, Admin Gudang** (dikunci di program); linknya tampil untuk role dengan centangan **Lihat Pembelian**.

## Isian & akibatnya
| Isian | Akibat |
|---|---|
| Nama, kontak, alamat | Hanya informasi di PO |
| Nonaktifkan | Supplier tidak bisa dipilih di PO baru; PO lama tetap |

## Menu yang mirip & bedanya
- **Roastery → Master Petani** — pemasok buah kopi untuk menu Beli Buah Kopi.
MD,
    ],

    [
        'slug' => 'dashboard-po', 'judul' => 'Dashboard PO', 'kelompok' => 'Pembelian', 'urutan' => 30,
        'letak' => 'Pembelian → Dashboard PO',
        'ringkasan' => 'Memantau semua PO: yang menunggu approve, belum dikirim, belum diterima, dan belum dibayar.',
        'izin' => ['po_dashboard.view'],
        'route_names' => ['pembelian.po-dashboard.*'],
        'terkait' => ['purchase-order'],
        'kata_kunci' => 'dashboard po monitoring pembelian po tertunda belum diterima belum dibayar',
        'konten' => <<<'MD'
## Fungsi
Melihat PO yang masih tertunda di setiap tahap, supaya tidak ada yang terlupa. Bisa dicetak dan diekspor.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Dashboard PO**. Menautkan transaksi kas ke PO perlu **Link Transaksi ke PO**.

## Hasil
Hanya menampilkan data. Approve/Terima/Bayar tetap dilakukan di halaman detail PO.
MD,
    ],
];
