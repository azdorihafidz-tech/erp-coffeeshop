<?php

/**
 * Kamus Menu — Meja & Pesan QR (khusus coffeeshop, Fase E): Layout Meja, Antrian Kasir QR,
 * Kitchen Display, Master Meja. Fakta diambil dari kode (KasirMejaController, BillService,
 * KitchenController, MejaController, PublicOrderController).
 */
return [

    [
        'slug' => 'layout-meja', 'judul' => 'Layout Meja', 'kelompok' => 'Penjualan', 'urutan' => 12,
        'letak' => 'Penjualan → Layout Meja',
        'ringkasan' => 'Denah meja outlet dengan warna status (kosong/terisi/menunggu bayar), lama duduk, isi bill per meja, tambah item, pindah meja, cetak & bayar bill.',
        'izin' => ['kasir.layout-meja.view'],
        'route_names' => ['kasir.layout-meja', 'kasir.layout-meja.*', 'kasir.meja.*', 'kasir.bill.*'],
        'terkait' => ['antrian-kasir-qr', 'master-meja', 'pos', 'kitchen-display', 'label-cup'],
        'kata_kunci' => 'layout meja denah meja meja terisi meja kosong bill meja open bill dine in pindah meja transfer meja tambah item tagihan meja timer',
        'konten' => <<<'MD'
## Fungsi
Melihat semua meja outlet sekaligus dan mengelola **bill** (tagihan berjalan) setiap meja — baik yang datang dari pesanan QR maupun yang dibuka kasir.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Layout Meja**. Tombol punya centangan sendiri: **Tandai Meja Terisi**, **Tambah Item ke Bill**, **Transfer Bill Antar Meja**, **Print Struk Bill**, **Bayar/Tutup Bill**.
- Meja harus dibuat dulu di **Stok & Gudang → Master Meja** (per outlet).
- Pilih outlet di pojok kanan atas. User tanpa cabang (selain Owner/Admin Pusat) dikembalikan ke Dashboard.

## Warna & angka
- Abu-abu = kosong; hijau = terisi (bill terbuka) dengan **lama duduk** yang berubah warna makin lama; tanda khusus = menunggu bayar.
- Angka di menu & di halaman diperbarui otomatis tiap ±10 detik: **pesanan QR menunggu** dan **komplain dari dapur** (bunyi beep saat bertambah).

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas | Lainnya |
|---|---|---|---|
| Tandai Terisi (tamu datang tanpa QR) | — | — | Bill baru terbuka di meja itu |
| Tambah Item | Belum dipotong | — | Item masuk bill (harga produk di outlet ini) + dikirim ke Kitchen Display. Label cup bisa dicetak ulang dari detail meja |
| Transfer ke meja lain | — | — | Bill pindah, meja asal kosong (tercatat) |
| Print Struk | — | — | Tagihan sementara |
| **Bayar** | Bahan semua item dipotong (FIFO) | Kas default metode bayar bertambah | Bill ditutup, jadi **order** di Riwayat Order, meja kosong lagi |

Bill juga bisa dibayar dari **POS** (pilih meja di Dine-in → bill meja itu dimuat). Item dari QR dan dari POS bergabung di bill yang sama.

## Jangan
- Jangan membuka bill baru untuk tamu yang masih di meja yang sama — tambahkan itemnya ke bill yang ada.

## Menu yang mirip & bedanya
- **Antrian Kasir (QR)** — pesanan dari HP pelanggan yang harus disetujui dulu.
- **POS** — jualan langsung; Save Bill Dine-in dengan meja juga membuka/menambah bill meja.
MD,
    ],

    [
        'slug' => 'antrian-kasir-qr', 'judul' => 'Antrian Kasir (Pesanan QR)', 'kelompok' => 'Penjualan', 'urutan' => 13,
        'letak' => 'Penjualan → Antrian Kasir',
        'ringkasan' => 'Pesanan yang dikirim pelanggan dari HP (scan QR di meja) menunggu disetujui kasir sebelum masuk bill meja & dapur.',
        'izin' => ['kasir.queue.view'],
        'route_names' => ['kasir.queue', 'kasir.queue.*'],
        'terkait' => ['layout-meja', 'master-meja', 'kitchen-display', 'label-cup'],
        'kata_kunci' => 'antrian kasir pesanan qr order qr scan qr meja approve pesanan tolak pesanan pesan dari hp menu online meja',
        'konten' => <<<'MD'
## Fungsi
Pelanggan memindai QR di meja → memilih menu di HP (tanpa login) → **Kirim**. Pesanan masuk ke sini sampai kasir **menyetujui** atau **menolak**.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Antrian Order (Kasir)**; menyetujui: **Approve Order (Kasir)**; menolak: **Reject Order (Kasir)**.
- Meja harus ada di **Master Meja** dengan QR yang masih berlaku (QR sementara punya batas waktu).
- **Pesan lewat QR harus dinyalakan per outlet**: Cabang → Edit → Config POS → **Aktifkan pesan lewat QR di meja** (bawaan mati). Selama mati, pelanggan melihat "QR ordering belum aktif".
- Produk yang tampil di HP pelanggan = produk yang tersedia & tidak Habis di outlet itu, dengan harga outlet itu.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| **Approve** | Kalau meja belum punya bill → bill baru terbuka; kalau sudah → item **digabung** ke bill yang ada. Item dikirim ke Kitchen Display, label cup tercetak kalau aktif. Stok & kas belum berubah (baru saat bill dibayar) |
| **Reject** (alasan wajib) | Pesanan ditolak, pelanggan melihat statusnya di HP |

Halaman status di HP pelanggan diperbarui otomatis (menunggu → disetujui/ditolak) dan menampilkan perkiraan waktu siap (dari **Estimasi waktu siap** di Produk Jual; yang dipakai yang paling lama, bukan dijumlah).

## Menu yang mirip & bedanya
- **Layout Meja** — semua bill meja yang sedang berjalan.
MD,
    ],

    [
        'slug' => 'kitchen-display', 'judul' => 'Kitchen Display', 'kelompok' => 'Antrian Produksi', 'urutan' => 50,
        'letak' => 'Antrian Produksi → Kitchen Display (terbuka di tab baru)',
        'ringkasan' => 'Layar dapur/bar: item pesanan meja yang belum siap, dikelompokkan per meja, dengan tombol Siap dan Komplain.',
        'izin' => ['kitchen.display.view'],
        'route_names' => ['kitchen.*'],
        'terkait' => ['layout-meja', 'antrian-kasir-qr', 'cabang', 'produk-jual'],
        'kata_kunci' => 'kitchen display layar dapur bar barista pesanan siap komplain item terlambat',
        'konten' => <<<'MD'
## Fungsi
Barista/dapur melihat item pesanan **meja** (dari QR maupun bill POS/Layout Meja) yang belum disiapkan, lalu menandainya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Kitchen Display**; tombol Siap/Komplain: **Tandai Item Siap/Komplain (Kitchen)**.
- Dinyalakan per outlet di **Cabang → Edit → Kitchen Display** (aktif + jeda refresh dalam detik).
- Pesanan takeaway biasa dari POS tidak tampil di sini — pakai **Antrian Produksi** untuk itu.

## Tampilan
- Diperbarui otomatis sesuai jeda refresh.
- Kartu meja **berkedip merah** kalau ada item yang melewati **Estimasi waktu siap** produknya (Produk Jual), atau 15 menit kalau produk tidak punya estimasi.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| **Siap** | Item hilang dari layar. Stok & kas tidak berubah |
| **Komplain** (alasan) | Item ditandai komplain → angka merah "komplain" di Layout Meja kasir |

## Menu yang mirip & bedanya
- **Kelola Antrian / Mode Produksi** — antrian per nomor order (termasuk takeaway), bukan per meja.
MD,
    ],

    [
        'slug' => 'master-meja', 'judul' => 'Master Meja', 'kelompok' => 'Stok & Gudang', 'urutan' => 95,
        'letak' => 'Stok & Gudang → Master Meja',
        'ringkasan' => 'Daftar meja per outlet (nomor, kapasitas, lokasi) beserta QR untuk pesan dari HP: cetak stiker, buat ulang QR, meja sementara.',
        'izin' => ['master.meja.view'],
        'route_names' => ['meja.*'],
        'terkait' => ['layout-meja', 'antrian-kasir-qr', 'pos'],
        'kata_kunci' => 'master meja tambah meja nomor meja kapasitas qr meja cetak qr stiker meja meja sementara temporary regenerate qr',
        'konten' => <<<'MD'
## Fungsi
Mendaftarkan meja di setiap outlet. Meja dipakai di POS (Dine-in), Layout Meja, dan pesan lewat QR.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Meja**, **Print QR Meja**. Hanya meja di outlet yang boleh Anda akses.
- Nomor meja **unik per outlet**.
- QR baru bisa dipakai pelanggan kalau outlet menyalakan **Aktifkan pesan lewat QR di meja** (Cabang → Edit → Config POS).

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Tambah | Meja baru + QR otomatis. Langsung muncul di dropdown meja POS & Layout Meja |
| **Print QR** | PDF stiker (±10×15 cm) berisi QR + nama meja & usaha, siap ditempel |
| **Generate ulang QR** | QR lama **tidak berfungsi lagi** — stiker lama harus diganti cetakan baru |
| Meja Sementara | Meja dengan QR yang kedaluwarsa di tanggal/jam yang dipilih (mis. acara). Setelah lewat, pelanggan melihat "QR kedaluwarsa" |
| Nonaktifkan / Hapus | Meja tidak bisa dipilih lagi; QR-nya menolak pesanan |

## Wajib
- Outlet tanpa meja tetap bisa Dine-in di POS dengan mengetik nomor meja (tanpa bill meja).

## Jangan
- Jangan Generate ulang QR kalau stiker baru belum siap dicetak — pelanggan yang memindai stiker lama akan ditolak.
MD,
    ],
];
