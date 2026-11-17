<?php

/**
 * Kamus Menu — kelompok Pengaturan, Bantuan, Keamanan (porting dimsum 4.50, disesuaikan coffeeshop).
 * Fakta diambil dari kode. Kalau perilaku menu berubah, ubah juga teks di sini.
 */
return [

    // ===================== PENGATURAN =====================

    [
        'slug' => 'cabang', 'judul' => 'Cabang & Gudang', 'kelompok' => 'Pengaturan', 'urutan' => 10,
        'letak' => 'Pengaturan → Cabang & Gudang',
        'ringkasan' => 'Daftar lokasi usaha (cabang/outlet, Gudang Pusat, Roastery, Head Office) beserta pengaturan per lokasi: POS, struk, antrian, Kitchen Display, Label Cup, absensi, shift, Jenis Pembeli.',
        'izin' => ['cabang.view'],
        'route_names' => ['cabang.index', 'cabang.create', 'cabang.edit', 'cabang.show', 'cabang.toggle-aktif', 'cabang.assign-user'],
        'terkait' => ['pos', 'kiriman-cabang', 'scan-absensi', 'display-tv', 'kitchen-display', 'kas-transaksi', 'manajemen-user', 'cara-produk-di-banyak-cabang'],
        'kata_kunci' => 'cabang gudang outlet roastery head office lokasi tambah cabang pengaturan cabang config pos struk antrian label cup kitchen display gps radius absen jam masuk shift jenis pembeli kirim ke cabang wholesale service charge take away fee frozen',
        'konten' => <<<'MD'
## Fungsi
Mendaftarkan lokasi usaha. Banyak fitur di menu lain **hanya muncul kalau diaktifkan di sini**.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Cabang**.
- Tipe lokasi menentukan fiturnya:

| Tipe | Dipakai untuk |
|---|---|
| **Cabang** | Outlet yang berjualan (POS, meja, antrian, label cup) |
| **Gudang Pusat** | Produksi, Kirim ke Cabang / Wholesale dari POS gudang, Dashboard Gudang |
| **Roastery** | Menu Roastery (beli buah, processing, roasting, grinding, packing) dan penjualan pack dari POS |
| **Head Office** | Kantor. Tidak muncul di POS dan tidak bisa di-Stok Opname |

## Pengaturan di Edit Cabang & menu yang terpengaruh
| Pengaturan | Mengubah menu |
|---|---|
| Alamat GPS (latitude/longitude) + **Radius absensi** | Scan Absensi menolak absen di luar radius (kosong = tidak dicek) |
| **Jam masuk kerja** + **Shift Kerja** | Batas terlambat saat Scan Absensi |
| Sistem Antrian Produksi | Bagian sidebar Antrian Produksi + nomor antrian di POS |
| Running text | Teks berjalan di Display TV Antrian |
| **Kitchen Display**: aktif, jeda refresh | Layar dapur pesanan meja |
| Footer struk, izinkan sembunyi harga per item | Struk POS |
| **Label Cup**: aktif, kategori yang dicetak, ukuran stiker, geser posisi | Label gelas di POS, Layout Meja, Antrian Kasir QR, Detail Order |
| **Config POS**: Dine-in / Takeaway / Frozen, input nomor meja, take away fee, service charge | Pilihan tipe & total POS |
| **Jenis Pembeli di POS** (hanya Gudang Pusat & Roastery, bawaan mati) | Pilihan Retail / Wholesale / Kirim ke Cabang di POS lokasi itu |
| Kepala cabang, user di cabang ini | Siapa yang bisa memilih cabang ini di pojok kanan atas; penerima pengingat Stok Opname |

⚠️ **Pesan lewat QR di meja** juga harus dinyalakan per outlet, tapi saklarnya **tidak ada** di halaman ini — hubungi tim IT. Selama mati, pelanggan yang memindai QR melihat pesan "QR ordering belum aktif".

## Wajib setelah membuat cabang baru
1. Buat 3 kas (Default untuk Tunai, Transfer, QRIS) di Kas & Transaksi → Kelola Kas.
2. Isi stok awal bahan di lokasi itu (Penyesuaian Stok, PO, Transfer, atau Kiriman).
3. Tugaskan user ke cabang (Manajemen User / Edit Cabang).
4. Atur ketersediaan & harga produk per cabang di Produk Jual kalau berbeda (bawaan: produk dijual di semua cabang dengan harga yang sama).

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Nonaktifkan | Cabang hilang dari pilihan, data tetap |
| **Hapus** | Hanya untuk cabang yang **masih kosong** (salah dibuat). Ditolak kalau sudah punya order, PO, transaksi kas, pergerakan stok, absensi, atau slip gaji — pesan menyebut jumlahnya |

## Jangan
- Cabang yang tutup / tidak dipakai lagi: pakai **Nonaktifkan**, bukan hapus — laporannya tetap utuh.
MD,
    ],

    [
        'slug' => 'manajemen-user', 'judul' => 'Manajemen User', 'kelompok' => 'Pengaturan', 'urutan' => 20,
        'letak' => 'Pengaturan → Manajemen User',
        'ringkasan' => 'Akun login: email, password, role (hak akses), cabang yang boleh dibuka, aktif/nonaktif.',
        'izin' => ['user.view'],
        'route_names' => ['user.*'],
        'terkait' => ['role-hak-akses', 'karyawan', 'cabang', 'aktivitas-tim-it'],
        'kata_kunci' => 'user akun login password reset password role cabang user nonaktif tambah user kasir baru',
        'konten' => <<<'MD'
## Fungsi
Membuat dan mengelola akun yang bisa login.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus User** (reset password & aktif/nonaktif ikut centangan Edit).

## Aturan penting
- Role **Owner** hanya bisa diberikan, dan akun Owner hanya bisa diubah, dinonaktifkan, di-reset, atau dihapus **oleh Owner**.
- Role yang tidak boleh melihat semua cabang (mis. Manajer Cabang) hanya melihat user yang berbagi cabang dengannya, dan hanya bisa menugaskan ke cabangnya sendiri.
- Tidak bisa menghapus/menonaktifkan akun sendiri, atau menghapus satu-satunya Owner.
- **Cabang default** = cabang yang terpilih otomatis saat login. User tanpa cabang (selain Owner/Admin Pusat) tidak bisa memakai menu yang butuh cabang, mis. Layout Meja.
- Akun **Tim IT** penyedia aplikasi tidak tampil di daftar ini dan tidak bisa diubah dari sini.

## Apa yang terjadi
| Tombol | Akibat |
|---|---|
| Ganti role | Menu & tombol yang terlihat langsung berubah sesuai centangan role baru |
| Nonaktifkan | Tidak bisa login, data tetap |
| Reset password | Password baru minimal 8 karakter |

## Menu yang mirip & bedanya
- **Karyawan** — data kepegawaian; bisa membuat akun login juga (dengan izin Tambah User).
- **Role & Hak Akses** — mengatur centangan untuk tiap role.
MD,
    ],

    [
        'slug' => 'role-hak-akses', 'judul' => 'Role & Hak Akses', 'kelompok' => 'Pengaturan', 'urutan' => 30,
        'letak' => 'Pengaturan → Role & Hak Akses',
        'ringkasan' => 'Mengatur centangan izin (menu & tombol) untuk setiap role: Admin Pusat, Admin Gudang, Manajer Cabang, Kasir, Operator Produksi, Helper.',
        'izin' => ['@owner'],
        'route_names' => ['role.*'],
        'terkait' => ['manajemen-user', 'cara-fitur-tidak-ada'],
        'kata_kunci' => 'role hak akses izin permission centang menu tidak muncul akses ditolak 403 paket modul kasir manajer admin',
        'konten' => <<<'MD'
## Fungsi
Menentukan menu & tombol apa saja yang bisa dipakai setiap role.

## Supaya menu ini muncul & bisa dipakai
- **Khusus Owner** (Owner selalu bisa semua menu **yang termasuk paket** aplikasi usaha Anda).
- Centangan untuk modul yang **tidak termasuk paket** tidak ditampilkan dan menunya tidak muncul untuk siapa pun; centangan lamanya tetap tersimpan dan kembali kalau modul diaktifkan tim IT.

## Cara kerjanya
- Role tetap: **Admin Pusat, Admin Gudang, Manajer Cabang, Kasir, Operator Produksi, Helper** (Owner tidak diatur di sini). Role baru tidak bisa dibuat.
- Setiap menu punya centangan sendiri; tombol penting (hapus, batalkan, setujui, ubah harga di POS) juga punya centangan sendiri. Kalau tidak dicentang, menu tidak tampil dan alamatnya ditolak.
- Centangan izin **baru** dari pembaruan aplikasi dipasang ke role bawaan **hanya sekali** (saat izin itu pertama kali dibuat lewat Update Database). Centangan yang Anda cabut tidak kembali sendiri.

## Tips kalau ada yang bilang "menu tidak muncul"
1. Cek apakah menunya termasuk paket — kalau Owner pun tidak melihatnya, hubungi tim IT (lihat Kamus "Kenapa menu atau fitur tidak ada?").
2. Cek centangan menu itu di role user tersebut.
3. Cek **Kamus Menu** menu itu → bagian "Supaya menu ini muncul" (sering ada saklar di menu lain, mis. Antrian/Label Cup/Jenis Pembeli di Edit Cabang).
4. Cek cabang di pojok kanan atas.
5. Kalau tombol Simpan selalu ditolak dan ada pita "Mode baca-saja": langganan sudah habis — hubungi tim IT.
MD,
    ],

    [
        'slug' => 'pengaturan-umum', 'judul' => 'Pengaturan Umum', 'kelompok' => 'Pengaturan', 'urutan' => 40,
        'letak' => 'Pengaturan → Pengaturan Umum',
        'ringkasan' => 'Identitas usaha (nama, logo, tagline, alamat, telepon), warna tampilan, dan pengaturan Stok Opname.',
        'izin' => ['pengaturan.umum_lihat'],
        'route_names' => ['pengaturan.umum', 'pengaturan.umum.*'],
        'terkait' => ['stok-opname', 'display-tv'],
        'kata_kunci' => 'pengaturan umum logo nama usaha tagline alamat telepon warna tampilan tema ikon aplikasi stok opname hitung buta pengingat',
        'konten' => <<<'MD'
## Fungsi
Pengaturan yang berlaku untuk **seluruh usaha**.

## Supaya menu ini muncul & bisa dipakai
- Melihat: centangan **Lihat Pengaturan Umum**. Menyimpan: **Ubah Pengaturan Umum**.

## Isian & akibatnya
| Isian | Tampil di |
|---|---|
| Nama usaha, nama singkat, tagline, alamat, telepon | Sidebar, login, struk, label cup, PDF laporan, slip gaji, TV Antrian, menu QR pelanggan, aplikasi Kasir POS |
| Logo | Sidebar, login, struk, PDF, ikon aplikasi (PWA) — disimpan ulang sebagai PNG; SVG ditolak |
| **Warna tampilan** (warna utama, sidebar, latar; 6 tema siap pakai) | Sidebar, tombol utama, latar, grafik, PDF. Warna yang membuat tulisan susah dibaca ditolak. Kosong = warna bawaan |
| **Stok Opname**: hitung buta, batas nilai barang hilang wajib foto, pengingat (nyala/mati, H-1…H-10, jam) | Opname **baru** (hitung buta), aturan foto saat rincian, notifikasi pengingat akhir bulan ke kepala lokasi + Owner. Perubahan tercatat di log aktivitas |

## Menu yang mirip & bedanya
- **Edit Cabang** — pengaturan per lokasi (struk, POS, label cup, absensi).
MD,
    ],

    [
        'slug' => 'pengaturan-penggajian', 'judul' => 'Pengaturan Penggajian', 'kelompok' => 'Pengaturan', 'urutan' => 50,
        'letak' => 'Pengaturan → Pengaturan Penggajian',
        'ringkasan' => 'Tarif dasar perhitungan slip gaji: tarif lembur, potongan alpa per hari, potongan telat per menit, hari kerja per minggu.',
        'izin' => ['pengaturan_penggajian'],
        'route_names' => ['pengaturan.penggajian', 'pengaturan.penggajian.*'],
        'terkait' => ['penggajian', 'hari-libur'],
        'kata_kunci' => 'pengaturan penggajian tarif lembur potongan alpa hari kerja per minggu potongan telat',
        'konten' => <<<'MD'
## Fungsi
Angka dasar yang dipakai **Generate Gaji**.

## Supaya menu ini muncul & bisa dipakai
- **Khusus Owner** (dikunci di program), ditambah centangan **Pengaturan Tarif Penggajian** supaya menunya tampil.

## Isian & akibatnya
| Isian | Akibat di slip gaji |
|---|---|
| Tarif lembur per jam | Uang lembur = jam lembur × tarif (0 = gaji pokok ÷ 173 × 1,5) |
| Potongan alpa per hari | Dikalikan jumlah Alpha |
| Hari kerja per minggu (5/6) | 5 = Sabtu ikut libur saat menghitung hari kerja |
| Potongan telat per menit | Dikalikan total menit telat (dari Scan Absensi) → baris "Potongan Telat" di slip. 0 = tidak dipotong |

Perubahan berlaku untuk slip yang di-Generate **setelahnya**. Slip yang sudah Disetujui/Dibayar tidak dihitung ulang.
MD,
    ],

    [
        'slug' => 'tooltip-helper', 'judul' => 'Tooltip Helper', 'kelompok' => 'Pengaturan', 'urutan' => 60,
        'letak' => 'Pengaturan → Tooltip Helper',
        'ringkasan' => 'Mengubah teks bantuan kecil (ikon ?) yang muncul di samping isian form.',
        'izin' => ['@semua_cabang'],
        'route_names' => ['admin.tooltips.*'],
        'terkait' => ['panduan-helper'],
        'kata_kunci' => 'tooltip bantuan ikon tanda tanya teks bantuan',
        'konten' => <<<'MD'
## Fungsi
Mengedit isi tooltip (ikon ? di form).

## Supaya menu ini muncul & bisa dipakai
- Owner dan role **Admin Pusat** (tidak diatur lewat centangan).

## Perlu diketahui
- Teks bisa memakai penanda `{nama_usaha}` / `{nama_singkat}` — otomatis diganti nama usaha dari Pengaturan Umum.
- Pembaruan aplikasi bisa menulis ulang sebagian tooltip bawaan. Catat perubahan penting Anda.
MD,
    ],

    [
        'slug' => 'panduan-helper', 'judul' => 'Panduan Helper', 'kelompok' => 'Pengaturan', 'urutan' => 70,
        'letak' => 'Pengaturan → Panduan Helper',
        'ringkasan' => 'Mengubah isi panduan (tombol "Cara Pakai") setiap menu.',
        'izin' => ['@semua_cabang'],
        'route_names' => ['admin.panduan.*'],
        'terkait' => ['panduan', 'tooltip-helper'],
        'kata_kunci' => 'panduan helper edit panduan cara pakai',
        'konten' => <<<'MD'
## Fungsi
Menambah/mengedit/menonaktifkan panduan langkah-langkah.

## Supaya menu ini muncul & bisa dipakai
- Owner dan role **Admin Pusat** (tidak diatur lewat centangan).

## Perlu diketahui
- Pembaruan aplikasi (Update Database) bisa menambah bagian baru atau menulis ulang panduan tertentu. Kamus Menu **tidak** bisa diedit di sini — isinya ikut pembaruan aplikasi.
MD,
    ],

    // ===================== BANTUAN =====================

    [
        'slug' => 'panduan', 'judul' => 'Panduan', 'kelompok' => 'Bantuan', 'urutan' => 10,
        'letak' => 'Bantuan → Panduan (juga tombol "Cara Pakai" di setiap halaman)',
        'ringkasan' => 'Langkah-langkah cara memakai setiap menu.',
        'izin' => [],
        'route_names' => ['panduan.*'],
        'terkait' => ['kamus-menu', 'panduan-helper'],
        'kata_kunci' => 'panduan cara pakai langkah tutorial bantuan',
        'konten' => <<<'MD'
## Fungsi
Panduan langkah demi langkah ("klik ini, isi itu").

## Supaya menu ini muncul & bisa dipakai
- Bisa dibuka semua user yang login.

## Menu yang mirip & bedanya
- **Kamus Menu** — fungsi, syarat muncul, alur, dan **akibat** setiap tombol ke stok/kas/laporan, plus perbandingan beberapa cara. Panduan = cara klik.
MD,
    ],

    [
        'slug' => 'kamus-menu', 'judul' => 'Kamus Menu', 'kelompok' => 'Bantuan', 'urutan' => 20,
        'letak' => 'Bantuan → Kamus Menu (juga tombol "Kamus" di atas setiap halaman)',
        'ringkasan' => 'Penjelasan setiap menu: fungsi, syarat supaya muncul, alur, akibat tiap tombol, wajib, jangan, dan menu yang mirip.',
        'izin' => [],
        'route_names' => ['kamus-menu.*'],
        'terkait' => ['panduan', 'role-hak-akses'],
        'kata_kunci' => 'kamus menu penjelasan menu fungsi menu cari menu bantuan',
        'konten' => <<<'MD'
## Fungsi
Tempat mencari penjelasan menu dengan kata sehari-hari, mis. "pindah barang", "batal order", "untung".

## Supaya menu ini muncul & bisa dipakai
- Bisa dibuka semua user; yang tampil hanya penjelasan menu yang **boleh Anda buka**.

## Cara memakai
- Ketik pertanyaan di kotak cari → klik hasil.
- Bagian **Cara Melakukan** membandingkan beberapa cara untuk tujuan yang sama.
- Tombol **Kamus** di bagian atas halaman langsung membuka penjelasan halaman yang sedang dibuka.
MD,
    ],

    // ===================== KEAMANAN =====================

    [
        'slug' => 'audit-log', 'judul' => 'Audit Log', 'kelompok' => 'Keamanan', 'urutan' => 10,
        'letak' => 'Keamanan → Audit Log',
        'ringkasan' => 'Catatan siapa melakukan apa dan kapan (membuat, mengubah, menghapus data).',
        'izin' => ['lihat_audit_log'],
        'route_names' => ['audit-log.*'],
        'terkait' => ['data-terhapus'],
        'kata_kunci' => 'audit log riwayat aktivitas siapa yang mengubah jejak perubahan',
        'konten' => <<<'MD'
## Fungsi
Melacak perubahan data: siapa, kapan, data lama → data baru.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Audit Log** (unduh: **Export Audit Log**).

## Hasil
Hanya menampilkan data.
MD,
    ],

    [
        'slug' => 'data-terhapus', 'judul' => 'Data Terhapus', 'kelompok' => 'Keamanan', 'urutan' => 20,
        'letak' => 'Keamanan → Data Terhapus',
        'ringkasan' => 'Tempat data yang dihapus (order, barang, kas, karyawan, cabang, dll). Bisa dipulihkan atau dihapus permanen.',
        'izin' => ['lihat_data_terhapus'],
        'route_names' => ['trash.*'],
        'terkait' => ['audit-log', 'riwayat-order', 'bahan-baku', 'backup'],
        'kata_kunci' => 'data terhapus tempat sampah restore pulihkan hapus permanen trash',
        'konten' => <<<'MD'
## Fungsi
Hampir semua tombol Hapus di aplikasi hanya **menyembunyikan** data. Di sini data itu bisa dilihat dan dipulihkan.

## Supaya menu ini muncul & bisa dipakai
- Melihat: **Lihat Data Terhapus**. Pulihkan: **Restore Data Terhapus**. Hapus permanen: **Hapus Permanen Data**.
- Pilih jenis data di tab (Orders, Items, Kas, Karyawan, …).

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| **Pulihkan** | Data kembali beserta data yang dulu ikut terhapus bersamanya. Order yang dipulihkan kembali masuk laporan apa adanya |
| **Hapus Permanen** | Hilang selamanya, tidak bisa dibatalkan |

## Jangan
- Jangan memulihkan barang dobel yang sudah punya pengganti — stoknya bisa jadi dobel.
- Order yang dulu dihapus dalam keadaan Selesai (cara lama): pulihkan lalu **Batalkan** supaya stok & kas ikut kembali.
MD,
    ],

    [
        'slug' => 'backup', 'judul' => 'Backup Database', 'kelompok' => 'Keamanan', 'urutan' => 30,
        'letak' => 'Keamanan → Backup Database',
        'ringkasan' => 'Membuat, mengunduh, dan menghapus file cadangan database.',
        'izin' => ['lihat_backup'],
        'route_names' => ['backup.*'],
        'terkait' => ['data-terhapus', 'update-database'],
        'kata_kunci' => 'backup cadangan database unduh backup restore',
        'konten' => <<<'MD'
## Fungsi
Menyimpan salinan seluruh database.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Backup**, **Buat Backup**, **Download Backup**, **Hapus File Backup**.
- Backup otomatis harian hanya berjalan kalau server menjalankan jadwal otomatis (cron).

## Wajib
- **Unduh** backup secara rutin dan simpan di luar server, terutama sebelum pembaruan aplikasi (Update Database).

## Perlu diketahui
- Mengembalikan (restore) backup tidak bisa dari menu ini — dilakukan lewat hosting (phpMyAdmin).
MD,
    ],

    [
        'slug' => 'update-database', 'judul' => 'Update Database', 'kelompok' => 'Keamanan', 'urutan' => 40,
        'letak' => 'Keamanan → Update Database',
        'ringkasan' => 'Menyelesaikan pembaruan aplikasi di server: menjalankan perubahan database baru, menambah izin/panduan/tooltip baru, dan membersihkan cache.',
        'izin' => ['@owner'],
        'route_names' => ['update-database.*'],
        'terkait' => ['backup', 'role-hak-akses'],
        'kata_kunci' => 'update database pembaruan aplikasi migration migrasi deploy versi baru setelah update error kolom tidak ada',
        'konten' => <<<'MD'
## Fungsi
Setelah program baru dipasang di server (Update from Remote → Deploy), tombol di halaman ini menyelesaikan pembaruannya tanpa perlu terminal.

## Supaya menu ini muncul & bisa dipakai
- **Khusus Owner** (dan Tim IT).
- Halaman menampilkan dulu daftar perubahan database yang **belum dijalankan** dan izin baru yang akan dicentangkan.

## Apa yang terjadi kalau tombol diklik
| Langkah | Akibat |
|---|---|
| Perubahan database | Hanya yang **baru** dijalankan. Data lama tidak dihapus |
| Izin baru | Dibuat, lalu dicentangkan ke role bawaan **hanya saat izin itu pertama kali dibuat**. Centangan yang pernah Anda ubah tidak disentuh |
| Panduan, tooltip, akun COA, isi Stok Opname | Diperbarui dari program (panduan/tooltip bawaan bisa tertulis ulang) |
| Cache | Dibersihkan supaya menu & izin baru langsung terlihat |

Jumlah data penting ditampilkan sebelum & sesudah sebagai bukti tidak ada yang hilang.

## Wajib
- **Backup** database dulu.
- Jalankan setelah setiap pembaruan program, di **setiap** instalasi.

## Jangan
- Jangan menunda: menu baru bisa error/hilang sampai halaman ini dijalankan.
MD,
    ],
];
