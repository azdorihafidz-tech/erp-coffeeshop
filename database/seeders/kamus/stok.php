<?php

/**
 * Kamus Menu — kelompok Stok & Gudang (porting dimsum 4.50, disesuaikan coffeeshop:
 * barang GLOBAL untuk semua cabang (bukan per cabang), Harga Beli Terakhir (bukan Harga Standar),
 * Kirim ke Cabang dari Gudang Pusat / Roastery, Stok Opname tanpa Head Office).
 * Master Meja ada di kamus/meja.php. Fakta diambil dari kode.
 */
return [

    [
        'slug' => 'dashboard-stok', 'judul' => 'Dashboard Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 10,
        'letak' => 'Stok & Gudang → Dashboard Stok',
        'ringkasan' => 'Ringkasan kondisi stok: barang hampir habis, nilai stok per lokasi, barang jarang bergerak, umur stok, tren harga.',
        'izin' => ['stok.view'],
        'route_names' => ['stok.dashboard', 'stok.dashboard.*', 'stok.aging', 'stok.top-movement', 'stok.trend-harga', 'stok.stok-mati'],
        'terkait' => ['stok-barang', 'bahan-baku', 'laporan-stok'],
        'kata_kunci' => 'dashboard stok stok menipis stok habis nilai stok persediaan slow moving stok mati aging umur stok tren harga',
        'konten' => <<<'MD'
## Fungsi
Melihat kondisi stok dengan cepat sebelum belanja atau memeriksa gudang.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Stok**. Data mengikuti cabang di pojok kanan atas.
- Yang dihitung hanya **Bahan Baku, Kemasan, Tambahan Gratis**. Produk Jual tidak punya stok sendiri (stoknya dihitung dari bahan), jadi tidak tampil.
- Peringatan "hampir habis" hanya untuk barang yang **Qty Minimum-nya diisi** (lebih dari 0) di Stok Barang.

## Yang dihitung
- **Nilai stok** = harga FIFO setiap batch yang tersisa (sama dengan Neraca/Closing). Barang tanpa batch memakai Harga Beli Terakhir.
- **Stok mati / jarang bergerak** = barang yang lama tidak keluar.

## Hasil
Hanya menampilkan data. Tidak mengubah stok.
MD,
    ],

    [
        'slug' => 'stok-barang', 'judul' => 'Stok Barang', 'kelompok' => 'Stok & Gudang', 'urutan' => 20,
        'letak' => 'Stok & Gudang → Stok Barang',
        'ringkasan' => 'Jumlah stok setiap barang per lokasi, kartu stok (riwayat keluar-masuk), atur Qty Minimum, dan reset stok.',
        'izin' => ['stok.view'],
        'route_names' => ['stok.index', 'stok.kartu'],
        'terkait' => ['penyesuaian-stok', 'stok-opname', 'dashboard-stok', 'bahan-baku', 'cara-menambah-stok', 'cara-mengurangi-stok'],
        'kata_kunci' => 'stok barang jumlah stok sisa stok kartu stok riwayat stok qty minimum batas minimum reset stok',
        'konten' => <<<'MD'
## Fungsi
Melihat sisa stok setiap barang di setiap lokasi, dan riwayat keluar-masuknya (**Kartu Stok**).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Stok**. Tombol lain punya centangan sendiri:
  - **Set Threshold Stok Minimum per Lokasi** — mengisi Qty Minimum.
  - **Hapus/Reset Stok ke 0 (Destruktif)** — tombol Reset.
  - **Penyesuaian Stok** — tombol Penyesuaian (lihat Kamus "Penyesuaian Stok").
- Yang tampil: Bahan Baku, Kemasan, Tambahan Gratis di lokasi aktif.
- Barang muncul di sini setelah **pertama kali punya stok** di lokasi itu (PO diterima, Penyesuaian, Transfer/Kiriman diterima, Produksi, Roastery).

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Kartu Stok | Hanya melihat riwayat. Baris dari Stok Opname punya tautan nomor SO |
| Set Qty Minimum | Menentukan batas "hampir habis" di lokasi ini → peringatan di Dashboard Stok, Dashboard Owner, Laporan Stok Minimum |
| **Reset ke 0** | Wajib ketik nama barang. Semua batch barang itu di lokasi ini dihapus, stok jadi 0, tercatat di kartu stok. **Tidak** mencatat rugi di keuangan. Ditolak kalau barang sedang di Stok Opname yang berjalan |

## Jangan
- Jangan pakai Reset untuk hitung fisik — pakai **Stok Opname** atau **Penyesuaian Stok** supaya selisihnya tercatat dengan alasan.
MD,
    ],

    [
        'slug' => 'penyesuaian-stok', 'judul' => 'Penyesuaian Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 25,
        'letak' => 'Stok & Gudang → Stok Barang → tombol Penyesuaian Stok',
        'ringkasan' => 'Menyamakan stok di sistem dengan hasil hitung fisik untuk satu barang. Selisihnya dicatat dengan alasan; rusak/hilang dicatat sebagai beban.',
        'izin' => ['stok.adjustment'],
        'route_names' => ['stok.adjustment', 'stok.adjustment.*'],
        'terkait' => ['stok-opname', 'stok-barang', 'pemakaian-perlengkapan', 'cara-menambah-stok', 'cara-mengurangi-stok'],
        'kata_kunci' => 'penyesuaian stok adjustment hitung fisik selisih stok rusak hilang susut kadaluarsa koreksi stok stok awal',
        'konten' => <<<'MD'
## Fungsi
Mengisi **jumlah stok fisik** satu barang, lalu sistem menghitung selisihnya dengan stok di sistem dan mencatatnya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Penyesuaian Stok**.
- Pilih lokasi di pojok kanan atas. Barang yang bisa dipilih: semua barang aktif.
- Kalau barang punya **Unit Beli** (pack/dus/karton), jumlah fisik bisa diisi per pack — otomatis diubah ke satuan pakai.

## Alasan & akibatnya ke keuangan
| Alasan | Stok | Catatan keuangan |
|---|---|---|
| Susut, Rusak/Kadaluarsa, Hilang/Dicuri | Naik/turun sesuai selisih | Selisih turun → **Beban Kerugian Stok**; selisih naik → **Koreksi Stok Masuk**. Tanpa kas (saldo kas tidak berubah), tapi masuk laporan keuangan |
| Salah Hitung, Audit | Naik/turun | Selisih **turun** → **Selisih Persediaan** (tanpa kas, masuk Laba Rugi Formal). Selisih **naik** tidak dicatat ke keuangan |
| Lainnya | Naik/turun | Hanya dicatat kalau "Catat sebagai biaya rugi" dicentang |

- **Stok turun** — batch tertua dipotong dulu (FIFO), atau pilih batch sendiri.
- **Stok naik** — dibuat batch baru dengan harga: harga yang diisi sendiri, atau **Harga Beli Terakhir** barang. Bisa juga menambah ke batch yang sudah ada.
- Barang yang sedang ada di **Stok Opname** yang belum selesai di lokasi itu **tidak bisa** disesuaikan di sini (juga Reset) — selesaikan/batalkan opname itu dulu.

## Wajib
- Pilih alasan yang benar — alasan menentukan apakah selisih jadi beban/pemasukan di laporan.
- Untuk **stok awal** saat mulai pakai aplikasi: isi harga (atau isi Harga Beli Terakhir di Bahan Baku dulu), supaya modal (HPP) penjualan tidak 0.

## Jangan
- Untuk hitung fisik rutin (akhir bulan) pakai **Stok Opname**: ada hitung buta, rincian penyebab, foto, persetujuan, dan berita acara.
- Jangan pakai Penyesuaian untuk barang yang dipakai sehari-hari (gas, sabun) — pakai **Pemakaian Perlengkapan**.
- Jangan pakai Penyesuaian untuk memindahkan barang ke lokasi lain — pakai **Transfer Stok**.
MD,
    ],

    [
        'slug' => 'stok-opname', 'judul' => 'Stok Opname', 'kelompok' => 'Stok & Gudang', 'urutan' => 26,
        'letak' => 'Stok & Gudang → Stok Opname',
        'ringkasan' => 'Hitung fisik stok per lokasi dengan hitung buta, rincian penyebab selisih, foto, dan persetujuan. Kerugian masuk Laba Rugi tanpa mengurangi kas.',
        'izin' => ['stok_opname.view', 'stok_opname.hitung', 'stok_opname.ajukan', 'stok_opname.setujui'],
        'route_names' => ['stok-opname.*'],
        'terkait' => ['penyesuaian-stok', 'stok-barang', 'stok-rusak', 'laporan-kerugian-stok', 'ganti-rugi-stok', 'cara-mengurangi-stok'],
        'kata_kunci' => 'stok opname stock opname hitung fisik hitung stok cek stok selisih stok barang hilang barang rusak basi kedaluwarsa kulkas mati pendingin susut sampel tester makan karyawan salah catat lebih berita acara akhir bulan audit stok',
        'konten' => <<<'MD'
## Fungsi
Mencocokkan stok sistem dengan hitungan fisik di satu lokasi. Setiap selisih wajib dirinci penyebabnya, lalu disetujui. Saat disetujui stok disesuaikan dan kerugiannya dicatat sebagai **beban tanpa kas** — muncul di Laba Rugi Formal, saldo kas tidak berubah. Lokasi tetap boleh berjualan selama opname.

## Supaya menu ini muncul & bisa dipakai
- Menu muncul kalau punya salah satu centangan: **Lihat Stok Opname**, **Hitung Stok Opname**, **Mulai & Periksa Stok Opname**, atau **Setujui / Tolak Stok Opname**.
- **Mulai** opname & isi rincian: **Mulai & Periksa Stok Opname**. **Mengisi jumlah**: **Hitung Stok Opname** dan dipilih sebagai penghitung (pemeriksa juga boleh menghitung).
- **Setujui/Tolak**: **Setujui / Tolak Stok Opname** (bawaan: Owner & Admin Pusat). Orang yang ikut menghitung tidak bisa menyetujui opname itu, kecuali Owner.
- **Batalkan**: **Batalkan Stok Opname**.
- Lokasi: outlet, Gudang Pusat, dan Roastery yang menjadi akses user (**Head Office tidak bisa**).
- Barang yang dihitung: barang aktif bertipe Bahan Baku/Kemasan/Tambahan Gratis yang dicentang *Lacak Stok*, ditambah barang apa pun yang masih punya stok di lokasi itu (mis. pack biji kopi Roastery). Produk Jual berkomposisi tidak dihitung — yang dihitung bahannya.
- **Hitung buta** & **batas nilai wajib foto barang hilang** diatur di **Pengaturan Umum**.

## Alur
Mulai → **Sedang dihitung** (isi jumlah / scan kode) → pemeriksa cek selisih, minta hitung ulang, rincikan penyebab → **Ajukan** → **Menunggu persetujuan** → **Setujui** (selesai, dikunci) atau **Tolak** (kembali dihitung). **Batalkan** bisa sebelum disetujui.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Keuangan | Kas |
|---|---|---|---|
| Mulai Opname | Belum berubah. Barang yang dipilih terkunci dari Penyesuaian Stok & Reset | — | — |
| Isi jumlah / scan | Belum berubah. Stok sistem saat itu dicatat per barang | — | — |
| Ajukan / Tolak / Batalkan | Belum berubah | — | — |
| **Setujui** | Disesuaikan sebesar **selisih** (fisik − sistem saat dihitung), bukan menimpa angka. Kurang → batch tertua dipotong (FIFO). Rusak/Basi/Kulkas mati → masuk **Stok Rusak**. Lebih → batch baru harga pokok rata-rata | 1 baris per penyebab, tanpa kas: Kerugian Stok – Rusak / Basi / Hilang, Penyusutan Stok, Promosi & Sampel, Makan Karyawan (Beban Konsumsi), Selisih Persediaan; kelebihan → Selisih Lebih Persediaan (pendapatan lain) | Tidak berubah |
| **Berita Acara** (PDF/Excel) | Tidak berubah | — | — |

- Kartu stok mencatat tiap penyesuaian dengan nomor SO yang bisa diklik.
- Berita Acara sebelum disetujui bertanda **SEMENTARA**; penghitung yang hitung buta baru bisa mengunduhnya setelah opname selesai.
- Rangkuman kerugian per periode: **Laporan → Kerugian Stok**. Barang hilang bisa dibebankan ke karyawan: **Ganti Rugi Stok**.

## Dashboard & pengingat
- Tombol **Dashboard** di daftar opname: status bulan ini per lokasi (sudah / menunggu persetujuan / sedang dihitung / **belum** = merah), kerugian per penyebab vs bulan lalu, grafik akurasi 6 bulan, 10 barang paling sering selisih, stok rusak tertahan, ringkasan ganti rugi.
- **Pengingat akhir bulan** (Pengaturan Umum; bawaan H-3 jam 08.00): lokasi yang belum memulai opname bulan itu → notifikasi ke kepala lokasi (kalau kosong: Manajer Cabang lokasi itu) + Owner, sekali per orang per hari, dan angka merah di menu.

## Wajib
- Rincian penyebab barang yang kurang harus **sama dengan selisih** (boleh dipecah).
- **Foto** untuk Rusak, Basi/Kedaluwarsa, Kulkas mati; untuk Hilang kalau nilainya mencapai batas di Pengaturan Umum.
- Semua barang dalam opname harus dihitung (atau dikeluarkan dengan alasan) sebelum diajukan.

## Jangan
- Jangan menyetujui kalau hari itu sudah masuk **Closing Periode Final** — akan ditolak; minta Owner membuka kunci atau setujui setelah periode itu.
- Jangan mencatat sisa produksi/roasting lewat opname — itu dicatat di hasil nyata menu **Produksi** / Roastery.
- Kalau stok keburu terjual sehingga tidak cukup dikurangi saat disetujui, persetujuan ditolak: tolak opname dan hitung ulang barang itu.

## Menu yang mirip & bedanya
- **Penyesuaian Stok**: koreksi cepat satu barang, langsung berlaku tanpa persetujuan & tanpa rincian penyebab.
- **Pemakaian Perlengkapan**: mencatat barang habis pakai sehari-hari, bukan selisih hitung.
MD,
    ],

    [
        'slug' => 'stok-rusak', 'judul' => 'Stok Rusak', 'kelompok' => 'Stok & Gudang', 'urutan' => 27,
        'letak' => 'Stok & Gudang → Stok Rusak',
        'ringkasan' => 'Barang rusak/basi/kulkas mati dari Stok Opname ditahan di sini. Tindak lanjut: kembali ke pemasok (ganti barang / uang / potong tagihan), musnahkan, atau dipakai lagi.',
        'izin' => ['stok_opname.stok_rusak', 'stok_opname.view'],
        'route_names' => ['stok-rusak.*'],
        'terkait' => ['stok-opname', 'laporan-kerugian-stok', 'supplier'],
        'kata_kunci' => 'stok rusak barang rusak barang basi kedaluwarsa kulkas mati buang barang musnahkan berita acara pemusnahan retur ke pemasok supplier ganti barang potong tagihan dipakai lagi',
        'konten' => <<<'MD'
## Fungsi
Menahan barang yang dicatat Rusak, Basi/Kedaluwarsa, atau Kulkas mati saat **Stok Opname disetujui**, lalu mencatat tindak lanjutnya. Barang di sini tidak termasuk stok jual. Kerugiannya sudah dicatat saat opname disetujui.

## Supaya menu ini muncul & bisa dipakai
- Menu muncul dengan centangan **Kelola Stok Rusak** atau **Lihat Stok Opname**; tombol **Tindak lanjut** hanya dengan **Kelola Stok Rusak**.
- Isinya baru ada setelah ada Stok Opname yang **disetujui** dengan penyebab Rusak/Basi/Kulkas mati.
- Hanya lokasi yang menjadi akses user. Untuk uang dari pemasok, lokasi harus punya **Kas** aktif.

## Apa yang terjadi kalau tombol diklik
| Tindakan | Stok | Keuangan | Kas |
|---|---|---|---|
| Diganti barang (pemasok) | Masuk lagi ke stok jual, harga pokok semula | Kerugian dibatalkan (baris minus di akun beban penyebabnya) | — |
| Dibayar uang (pemasok) | — | Pemulihan Kerugian Stok (pendapatan lain) | Kas terpilih **bertambah** |
| Dipotong tagihan (pemasok) | — | Pemulihan Kerugian Stok, nomor PO wajib | Kas yang membayar PO **bertambah** |
| Dimusnahkan | — | Tidak ada (sudah dicatat saat opname) | — |
| Dipakai lagi (hanya penyebab Rusak) | Masuk lagi ke stok jual, harga pokok semula | Kerugian dibatalkan | — |

Tindakan berkeuangan ditolak kalau hari ini sudah masuk Closing Periode Final.

## Wajib
- Foto untuk pemusnahan. Berita Acara Pemusnahan (PDF) dibuat otomatis dengan nomor `BA-TahunBulan-Urut`.
- Nomor PO / faktur untuk potong tagihan.

## Jangan
- Jangan memakai lagi barang basi, kedaluwarsa, atau kena kulkas mati — sistem menolaknya.
- Jangan buang barang rusak lewat Penyesuaian Stok kalau sudah tercatat di sini — kerugiannya akan dobel.

## Menu yang mirip & bedanya
- **Retur Cabang**: mengembalikan kiriman dari cabang ke gudang/roastery (antar lokasi sendiri), bukan ke pemasok.
MD,
    ],

    [
        'slug' => 'permintaan-stok', 'judul' => 'Permintaan Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 30,
        'letak' => 'Stok & Gudang → Permintaan Stok',
        'ringkasan' => 'Cabang meminta bahan ke gudang/lokasi lain. Setelah disetujui, pengirim memprosesnya lewat Transfer Stok (tanpa jual-beli).',
        'izin' => ['stok.request'],
        'route_names' => ['stock-request.*'],
        'terkait' => ['transfer-stok', 'kiriman-cabang', 'purchase-order', 'cara-memindahkan-barang'],
        'kata_kunci' => 'permintaan stok request stok minta barang minta bahan cabang minta ke gudang',
        'konten' => <<<'MD'
## Fungsi
Cabang mengajukan daftar bahan yang dibutuhkan. Pengirim menyetujui jumlahnya, lalu mengirim lewat **Transfer Stok**.

## Supaya menu ini muncul & bisa dipakai
- Membuat permintaan: centangan **Buat Permintaan Stok** (daftar permintaan butuh **Lihat Stok**).
- Menyetujui / menolak: centangan **Transfer Stok**.
- Pilih **cabang peminta** di pojok kanan atas. Barang yang bisa diminta: Bahan Baku, Kemasan, Tambahan Gratis.

## Alur
Pending → **Disetujui** (jumlah boleh diubah) / **Ditolak** → pengirim membuat Transfer dari permintaan itu → Transfer dikirim → cabang Terima → permintaan **Diterima**.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas |
|---|---|---|
| Buat / Setujui / Tolak / Batalkan permintaan | Belum berubah | — |
| Transfer dari permintaan | Lihat Kamus **Transfer Stok** | Tidak ada uang |

## Menu yang mirip & bedanya
- **Kiriman Cabang** — gudang/roastery **menjual** produk ke cabang (ada untung pengirim, kas cabang berkurang).
- **Purchase Order** — membeli dari supplier luar.
MD,
    ],

    [
        'slug' => 'transfer-stok', 'judul' => 'Transfer Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 40,
        'letak' => 'Stok & Gudang → Transfer Stok',
        'ringkasan' => 'Memindahkan bahan antar lokasi sendiri tanpa jual-beli: stok asal berkurang saat Kirim, stok tujuan bertambah saat Terima.',
        'izin' => ['stok.transfer'],
        'route_names' => ['stock-transfer.*'],
        'terkait' => ['permintaan-stok', 'kiriman-cabang', 'stok-barang', 'cara-memindahkan-barang'],
        'kata_kunci' => 'transfer stok pindah barang pindah bahan mutasi stok kirim bahan gudang ke cabang antar cabang roasted bean biji kopi',
        'konten' => <<<'MD'
## Fungsi
Memindahkan bahan dari satu lokasi ke lokasi lain milik usaha sendiri (mis. biji kopi roasted dari Roastery/Gudang ke outlet). **Tidak ada uang yang bergerak** dan pengirim tidak mendapat untung.

## Supaya menu ini muncul & bisa dipakai
- Membuat, mengirim: centangan **Transfer Stok** + **Lihat Stok**.
- Menerima di lokasi tujuan / membatalkan: centangan **Transfer Stok** atau **Buat Permintaan Stok**, dan user boleh mengakses lokasi itu.
- Barang yang bisa dipindah: Bahan Baku, Kemasan, Tambahan Gratis. Barang yang sama dipakai di semua lokasi, jadi tidak ada barang dobel.

## Alur
Draft → **Dikirim** → **Diterima** / Diterima Sebagian, atau Dibatalkan.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok asal | Stok tujuan | Kas |
|---|---|---|---|
| Simpan (Draft) | — | — | — |
| **Kirim** | Berkurang (FIFO) | — | — |
| **Terima** | — | Bertambah; modal batch = **Harga Beli Terakhir** barang | — |
| Terima Sebagian | — | Bertambah sesuai jumlah diterima | — |
| **Batalkan** (Draft/Dikirim) | Kalau sudah Dikirim: kembali | — | — |

Semua tercatat di Kartu Stok kedua lokasi. Tidak masuk laporan penjualan.

⚠️ Modal di lokasi tujuan memakai Harga Beli Terakhir, bukan modal FIFO yang keluar dari asal. Kalau harga beli berubah-ubah, HPP outlet bisa sedikit berbeda dari modal sebenarnya. Hasil Roastery/Produksi mengisi Harga Beli Terakhir dengan modal batch terakhirnya.

## Jangan
- Jangan memindahkan barang yang sama lewat Transfer **dan** Kiriman — stok tujuan jadi dobel.

## Menu yang mirip & bedanya
- **Kiriman Cabang** — gudang/roastery **menjual** produk ke cabang: kas cabang berkurang, pengirim untung.
- **Permintaan Stok** — cara cabang meminta; prosesnya tetap lewat Transfer ini.
MD,
    ],

    [
        'slug' => 'produksi', 'judul' => 'Produksi', 'kelompok' => 'Stok & Gudang', 'urutan' => 50,
        'letak' => 'Stok & Gudang → Produksi',
        'ringkasan' => 'Gudang mengolah bahan mentah jadi bahan setengah jadi (mis. saus, sirup racikan, cup kopi isi) berdasarkan Resep Produksi.',
        'izin' => ['produksi.view'],
        'route_names' => ['produksi.*'],
        'terkait' => ['bahan-baku', 'master-bumbu-pusat', 'kiriman-cabang', 'cara-membuat-resep'],
        'kata_kunci' => 'produksi masak olah bahan setengah jadi saus sirup racikan cup kopi isi batch produksi resep produksi hasil produksi',
        'konten' => <<<'MD'
## Fungsi
Mencatat pengolahan di gudang: bahan mentah berkurang, barang hasil bertambah dengan modal dari harga bahannya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Produksi**; mencatat perlu **Catat Produksi**; membatalkan perlu **Batalkan Produksi**; membuat/mengubah resep perlu **Kelola Resep Produksi**.
- Hanya di lokasi bertipe **Gudang Pusat** — pilih gudang di pojok kanan atas.
- **Barang hasil dan semua bahan** harus dibuat dulu di **Bahan Baku & Kemasan**.
- Buat **Resep Produksi** dulu (tab Resep Produksi): 1 barang hasil = 1 resep, tidak boleh memakai dirinya sendiri sebagai bahan. Takaran boleh gram/ml — dikonversi ke satuan stok.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas |
|---|---|---|
| **Catat Produksi** | Bahan = takaran resep × jumlah rencana, dipotong FIFO. Barang hasil bertambah sesuai **hasil nyata** | — |
| Modal hasil | Otomatis = total biaya bahan ÷ hasil nyata (atau diisi manual). **Harga Beli Terakhir** barang hasil ikut diperbarui | — |
| **Batalkan** (alasan wajib) | Bahan kembali, hasil keluar — hanya kalau stok hasil di gudang masih cukup | — |

Kalau stok satu bahan kurang, produksi ditolak dan pesan menyebut semua bahan yang kurang.

## Wajib
- Isi **hasil nyata** sesuai yang benar-benar jadi — modal per unit dihitung dari angka ini.

## Jangan
- Jangan batalkan produksi yang hasilnya sudah dipakai/dikirim — akan ditolak; koreksi lewat Penyesuaian Stok.

## Menu yang mirip & bedanya
- **Komposisi Produk Jual / Bumbu Pusat** — bahan dipotong **saat produk dijual** di POS. Produksi = bahan dipotong **saat diolah** di gudang, hasilnya jadi stok.
- **Roastery** — pengolahan biji kopi (processing, roasting, grinding, packing) punya menunya sendiri.
MD,
    ],

    [
        'slug' => 'kiriman-cabang', 'judul' => 'Kiriman Cabang (Kirim ke Cabang)', 'kelompok' => 'Stok & Gudang', 'urutan' => 60,
        'letak' => 'Stok & Gudang → Kiriman Cabang (dikirim dari POS Gudang Pusat / Roastery)',
        'ringkasan' => 'Gudang/Roastery menjual produk ke cabang lewat POS. Cabang menerima: stok cabang bertambah, kas cabang berkurang, pengirim dapat pendapatan.',
        'izin' => ['kiriman.view'],
        'route_names' => ['kiriman.*'],
        'terkait' => ['pos', 'retur-cabang', 'transfer-stok', 'cabang', 'cara-memindahkan-barang'],
        'kata_kunci' => 'kiriman cabang kirim ke cabang jual ke cabang gudang jual roastery jual ke outlet terima kiriman tolak kiriman internal untung gudang gerobak',
        'konten' => <<<'MD'
## Fungsi
Gudang Pusat atau Roastery menjual produk ke cabang sendiri (mis. gerobak, outlet). Cabang membayar dengan harga Produk Jual pengirim, jadi pengirim mendapat untung dan modal cabang = harga kirim. Kalau ingin tanpa untung, samakan harga jual dengan modalnya.

## Supaya menu ini muncul & bisa dipakai
- **Saklar wajib dinyalakan**: Pengaturan → Cabang & Gudang → Edit (Gudang Pusat / Roastery) → **Jenis Pembeli di POS**. Bawaannya mati — selama mati, pilihan "Kirim ke Cabang" tidak muncul di POS lokasi itu.
- Pengirim dan cabang tujuan wajib punya **Kas Tunai aktif**.
- Mengirim: centangan **Buat Order** (POS pengirim). Melihat menu: **Lihat Kiriman Cabang**. Menerima/menolak di cabang: **Terima / Tolak Kiriman dari Gudang**. Membatalkan dari pengirim: **Batalkan Order**.
- Cabang tujuan = lokasi bertipe Cabang yang aktif.

## Alur
POS pengirim → Jenis Pembeli **Kirim ke Cabang** → pilih Cabang Tujuan → **Kirim ke Cabang** (tanpa pembayaran) → di cabang: **Terima** (boleh sebagian) atau **Tolak**.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok pengirim | Stok cabang | Kas | Laporan |
|---|---|---|---|---|
| **Kirim** (POS) | Bahan produk dipotong | — | — | Belum dihitung (status Dikirim) |
| **Terima** | Sisa yang tidak diterima kembali | Bertambah; modal = harga kirim setelah diskon | Kas Tunai cabang **berkurang**; Kas Tunai pengirim **bertambah** | Jadi penjualan pengirim pada **tanggal Terima** |
| **Tolak** / Batalkan | Semua kembali | — | — | Tidak dihitung |

- Yang masuk ke cabang adalah **bahan di komposisi** produk. Produk tanpa komposisi yang punya stok sendiri (mis. pack biji kopi Roastery) masuk sebagai barang itu sendiri.
- Kas cabang boleh menjadi minus (ada peringatan).
- Di laporan "Semua Cabang" (Laporan Transaksi/Produk/Kategori, Closing Konsolidasi) kiriman dikeluarkan supaya omzet tidak dobel.

## Jangan
- Kiriman yang sudah diterima **tidak bisa dibatalkan/diedit** — kalau barang basi/rusak/kelebihan, pakai **Retur Cabang**.

## Menu yang mirip & bedanya
- **Transfer Stok** — pindah bahan tanpa uang & tanpa untung.
MD,
    ],

    [
        'slug' => 'retur-cabang', 'judul' => 'Retur Cabang', 'kelompok' => 'Stok & Gudang', 'urutan' => 70,
        'letak' => 'Stok & Gudang → Retur Cabang (dibuat dari kiriman yang sudah diterima)',
        'ringkasan' => 'Cabang mengembalikan barang kiriman gudang/roastery (basi, rusak, kelebihan). Uangnya bisa dikembalikan atau tidak.',
        'izin' => ['retur.view'],
        'route_names' => ['retur-cabang.*'],
        'terkait' => ['kiriman-cabang', 'riwayat-order'],
        'kata_kunci' => 'retur cabang kembalikan barang barang basi barang rusak kelebihan kirim refund kiriman',
        'konten' => <<<'MD'
## Fungsi
Mengembalikan sebagian/seluruh barang dari **kiriman yang sudah diterima**.

## Supaya menu ini muncul & bisa dipakai
- Membuat/membatalkan retur (cabang): **Buat / Batalkan Retur ke Gudang**. Menerima/menolak (pengirim): **Terima / Tolak Retur dari Cabang**. Melihat: **Lihat Retur Cabang**.
- Retur hanya bisa dari kiriman berstatus **diterima**, maksimal sejumlah yang diterima dan belum pernah diretur.
- Untuk retur dengan uang kembali: pengirim dan cabang punya Kas Tunai aktif.

## Alur
Cabang buat retur (pilih alasan dan **uang kembali Ya/Tidak**) → status Menunggu → pengirim **Terima** atau **Tolak**.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok cabang | Stok pengirim | Kas |
|---|---|---|---|
| **Kirim Retur** (cabang) | Berkurang | — | — |
| **Terima**, uang kembali **Ya** | — | Bertambah dengan modal asli pengirim | Kas pengirim berkurang → kas cabang bertambah. Ada order koreksi minus di pengirim (pendapatan & untung ikut turun) |
| **Terima**, uang kembali **Tidak** | — | Bertambah dengan modal Rp0 (rugi ditanggung cabang) | — |
| **Tolak** / Batalkan | Kembali | — | — |

## Jangan
- Order koreksi retur tidak bisa dibatalkan, diedit, atau dihapus.
- Barang basi yang sudah diterima pengirim tetap jadi stoknya — buang lewat **Penyesuaian Stok** (alasan Rusak/Kadaluarsa) atau Stok Opname.
MD,
    ],

    [
        'slug' => 'bahan-baku', 'judul' => 'Bahan Baku & Kemasan', 'kelompok' => 'Stok & Gudang', 'urutan' => 80,
        'letak' => 'Stok & Gudang → Bahan Baku & Kemasan',
        'ringkasan' => 'Data semua barang yang dibeli dan disimpan: bahan baku, kemasan, tambahan gratis, perlengkapan. Termasuk Harga Beli Terakhir dan unit beli.',
        'izin' => ['master.bahan_baku.view'],
        'route_names' => ['master.bahan-baku.*'],
        'terkait' => ['produk-jual', 'stok-barang', 'purchase-order', 'pemakaian-perlengkapan', 'cara-mengatur-harga'],
        'kata_kunci' => 'bahan baku kemasan perlengkapan tambahan gratis sedotan cup tutup susu biji kopi master barang harga beli unit beli pack dus karton kode item satuan kategori',
        'konten' => <<<'MD'
## Fungsi
Mendaftarkan barang yang dibeli dan disimpan stoknya. Barang di sini dipakai di PO, komposisi Produk Jual, Bumbu Pusat, Produksi, Roastery, Transfer, dan Stok Opname.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Bahan Baku & Kemasan**.
- Barang berlaku untuk **semua lokasi** — stoknya dicatat per lokasi.
- **Kode Item unik**.

## Isian penting & akibatnya
| Isian | Akibat |
|---|---|
| Tipe: Bahan Baku / Kemasan | Bisa dibeli, distok, dipakai di komposisi |
| Tipe: **Tambahan Gratis** | Juga tampil di POS bagian "Item Tambahan" (harga Rp0). Kalau **Lacak Stok** dicentang: stok cabang dipotong saat dibayar & bertanda "Habis" kalau kosong. Tidak dicentang: stoknya tidak dihitung |
| Jenis: Bahan Baku / **Perlengkapan** | Perlengkapan (gas, sabun, ATK) bisa dicatat di menu Pemakaian Perlengkapan |
| Lacak Stok | Tidak dicentang: tidak bisa dipilih di Pemakaian Perlengkapan, tidak ikut Stok Opname, dan (Tambahan Gratis) tidak dipotong di POS |
| **Satuan** | Satuan stok & satuan harga — harga selalu per satuan ini |
| **Unit Beli** + isi (mis. karton = 12 liter) | Di PO & Penyesuaian bisa isi per karton, otomatis diubah ke satuan |
| **Harga Beli Terakhir** | Diperbarui otomatis setiap PO diterima (dan hasil Produksi/Roastery). Dipakai: perkiraan modal komposisi, harga awal PO, batch stok naik di Penyesuaian, batch di lokasi tujuan Transfer, dan fallback kalau stok tidak punya batch |
| Kategori (+) | Tombol + membuat kategori baru |

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Simpan | Barang siap dipakai; stoknya 0 sampai ada PO/Penyesuaian/Transfer |
| **Hapus** | Hanya untuk barang yang **belum dipakai**: ditolak kalau masih ada stok, dipakai di komposisi produk / Bumbu Pusat / Resep Produksi, atau ada di PO yang belum selesai (pesan menyebut alasannya). Barang masuk Data Terhapus (bisa dipulihkan); riwayat order & PO tetap |

## Wajib
- Satuan harga = satuan stok. Contoh: stok dalam gram → harga per gram. Kalau stok gram tapi harga diisi per kg, modal jadi 1.000× lipat.

## Jangan
- Barang yang sudah tidak dipakai lagi cukup **dinonaktifkan** — riwayatnya tetap aman.
- Jangan membuat barang jadi (mis. air mineral botol) sebagai Produk Jual saja — buat di sini sebagai Bahan Baku, lalu buat Produk Jual dengan komposisi 1 barang itu.

## Menu yang mirip & bedanya
- **Produk Jual** — yang dijual di POS (punya harga jual & komposisi). Bahan Baku = yang dibeli & disimpan.
- **Semua Barang (Overview)** — hanya melihat semua barang sekaligus.
MD,
    ],

    [
        'slug' => 'produk-jual', 'judul' => 'Produk Jual', 'kelompok' => 'Stok & Gudang', 'urutan' => 90,
        'letak' => 'Stok & Gudang → Produk Jual',
        'ringkasan' => 'Produk yang dijual di POS: harga jual (umum & per cabang), cabang yang menjual, foto, kategori, varian, estimasi waktu siap, dan komposisi bahan yang dipotong saat terjual.',
        'izin' => ['master.produk_jual.view'],
        'route_names' => ['master.produk-jual.*'],
        'terkait' => ['pos', 'bahan-baku', 'master-bumbu-pusat', 'label-cup', 'cara-produk-di-banyak-cabang', 'cara-produk-tidak-muncul-di-pos', 'cara-membuat-resep', 'cara-mengatur-harga'],
        'kata_kunci' => 'produk jual menu produk harga jual harga per cabang resep komposisi varian ukuran foto produk kategori produk import bumbu hpp modal estimasi waktu siap',
        'konten' => <<<'MD'
## Fungsi
Mendaftarkan produk yang dijual di POS. Produk jual **tidak punya stok sendiri** — saat terjual, bahan di **komposisinya** yang dipotong (kecuali produk tanpa komposisi yang stoknya diisi, mis. pack biji kopi).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Produk Jual**.
- Produk berlaku untuk semua cabang; centang **cabang yang menjual** dan (opsional) **harga per cabang** di form. Produk yang tidak pernah diatur cabangnya = dijual di semua cabang dengan harga jual umum.
- Bahan di komposisi harus sudah ada di **Bahan Baku & Kemasan**.

## Isian penting & akibatnya
| Isian | Akibat |
|---|---|
| Tipe: **Produk Jual** | Tampil di grid utama POS |
| Tipe: **Produk Tambahan** | Tampil di bagian "Item Tambahan" (berbayar, mis. extra shot). Komposisinya dipotong saat terjual; tanpa komposisi memakai stoknya sendiri |
| Harga Jual / harga per cabang | Harga di POS & menu QR. Kasir hanya bisa mengubahnya di POS kalau punya centangan Ubah Harga |
| Status Aktif | Tidak aktif = hilang dari POS |
| Kategori | Chip filter di POS, Laporan Kategori, dan **Label Cup** (kategori yang dicetak diatur per cabang) |
| Jenis Menu (opsional) | Kelompok untuk Laporan Laba Rugi "Per Jenis Menu". Namanya ikut tersimpan di order saat dijual |
| **Estimasi waktu siap** (menit) | Ditampilkan di menu QR pelanggan & Kitchen Display (batas kartu berkedip) |
| **Komposisi** | Bahan + takaran per 1 produk (cari bahan dengan nama/kode/kategori; takaran gram/ml dikonversi ke satuan stok). Menentukan stok yang dipotong, tanda "Habis", dan HPP |
| Import dari Bumbu Pusat | Menautkan Bumbu Pusat (ubah bumbu → semua produk ikut); sebelum import tampil perkiraan modal per cabang |
| **Varian** | Kombinasi varian bisa punya harga sendiri. Komposisi & stok tetap mengikuti produk induk |
| Foto | Tampil di grid POS & menu QR |

Kolom **Harga Beli** & **Total HPP** di komposisi = perkiraan dari **Harga Beli Terakhir** bahan. HPP sebenarnya dihitung dari batch FIFO saat penjualan. Harga beli 0 ditandai merah.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Simpan | Produk muncul di POS cabang yang dicentang |
| Hapus | Produk masuk Data Terhapus (bisa dipulihkan). Riwayat order tetap utuh; foto tidak dihapus |

## Wajib
- Setiap produk jual **harus punya komposisi** (atau stok sendiri). Tanpa keduanya, produk selalu "Habis" di POS.

## Jangan
- Produk yang berhenti dijual cukup **dinonaktifkan** (bisa diaktifkan lagi kapan saja).

## Menu yang mirip & bedanya
- **Master Bumbu Pusat** — resep bersama yang ditautkan ke banyak produk.
- **Bahan Baku & Kemasan** — barang yang dibeli & distok.
MD,
    ],

    [
        'slug' => 'semua-barang', 'judul' => 'Semua Barang (Overview)', 'kelompok' => 'Stok & Gudang', 'urutan' => 100,
        'letak' => 'Stok & Gudang → Semua Barang',
        'ringkasan' => 'Daftar semua barang (bahan & produk) dalam satu tabel. Hanya untuk melihat — tambah/ubah lewat Bahan Baku atau Produk Jual.',
        'izin' => ['item.view'],
        'route_names' => ['item.*'],
        'terkait' => ['bahan-baku', 'produk-jual'],
        'kata_kunci' => 'semua barang master barang lengkap overview daftar barang item',
        'konten' => <<<'MD'
## Fungsi
Melihat semua barang sekaligus (bahan, kemasan, tambahan, produk jual) untuk pemeriksaan.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Master Barang**.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Edit | Diarahkan ke form Bahan Baku atau Produk Jual sesuai tipenya |

Tidak ada tombol tambah/hapus di sini — membuka alamat tambah/hapus dialihkan kembali ke daftar. Centangan "Tambah/Edit/Hapus Master Barang" tidak dipakai — yang berlaku centangan di Bahan Baku & Produk Jual.
MD,
    ],

    [
        'slug' => 'pemakaian-perlengkapan', 'judul' => 'Pemakaian Perlengkapan', 'kelompok' => 'Stok & Gudang', 'urutan' => 110,
        'letak' => 'Stok & Gudang → Pemakaian Perlengkapan',
        'ringkasan' => 'Mencatat pemakaian barang habis pakai (gas, sabun, tisu dapur, ATK) supaya stoknya berkurang dan nilainya tercatat.',
        'izin' => ['pemakaian_perlengkapan.view'],
        'route_names' => ['pemakaian-perlengkapan.*'],
        'terkait' => ['bahan-baku', 'penyesuaian-stok', 'cara-mengurangi-stok'],
        'kata_kunci' => 'pemakaian perlengkapan barang habis pakai gas sabun tisu atk pakai barang',
        'konten' => <<<'MD'
## Fungsi
Mencatat barang yang dipakai untuk operasional (bukan dijual), misalnya gas, sabun, tisu dapur.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Pemakaian Perlengkapan**; mencatat perlu **Catat Pemakaian Perlengkapan**.
- Barang yang bisa dipilih hanya yang di **Bahan Baku & Kemasan** diberi **Jenis = Perlengkapan** dan **Lacak Stok** dicentang. Selama belum ada barang seperti itu, form tidak menampilkan pilihan barang.
- Lokasi yang bisa dipilih: lokasi milik user (Owner/Admin Pusat: semua).

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas | Laporan |
|---|---|---|---|
| **Simpan** | Berkurang (FIFO); nilai (modal) tercatat | — | Laporan Pemakaian Perlengkapan |

Kalau stok kurang, ditolak.

## Jangan
- Kalau salah catat, koreksi lewat **Penyesuaian Stok** (alasan Salah Hitung).

## Menu yang mirip & bedanya
- **Penyesuaian Stok** — untuk rusak/hilang/selisih hitung, bukan pemakaian rutin.
MD,
    ],
];
