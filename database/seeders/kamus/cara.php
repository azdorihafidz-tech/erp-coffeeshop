<?php

/**
 * Kamus Menu — kelompok "Cara Melakukan": pertanyaan yang punya LEBIH DARI 1 cara
 * (porting dimsum 4.50, disesuaikan coffeeshop: barang global, harga per cabang di Produk Jual,
 * Kirim ke Cabang dari Gudang/Roastery, rantai Roastery, Closing Periode).
 */
return [

    [
        'slug' => 'cara-memindahkan-barang', 'judul' => 'Bagaimana memindahkan barang dari gudang/roastery ke cabang?', 'kelompok' => 'Cara Melakukan', 'urutan' => 10,
        'letak' => 'Stok & Gudang / POS Gudang & Roastery',
        'ringkasan' => 'Ada 2 cara: Permintaan Stok → Transfer (tanpa jual-beli), atau Kirim ke Cabang dari POS (dijual, pengirim untung).',
        'izin' => ['stok.request', 'stok.transfer', 'kiriman.view'],
        'route_names' => [],
        'terkait' => ['transfer-stok', 'permintaan-stok', 'kiriman-cabang', 'cabang'],
        'kata_kunci' => 'pindah barang kirim barang gudang ke cabang roastery ke outlet distribusi kirim bahan antar cabang mutasi biji kopi',
        'konten' => <<<'MD'
## Ada 2 cara

| | ① Permintaan Stok → Transfer | ② Kirim ke Cabang |
|---|---|---|
| Dimulai oleh | Cabang minta (atau pengirim langsung Transfer) | Gudang Pusat / Roastery, dari POS |
| Barang | Bahan Baku, Kemasan, Tambahan Gratis | Produk Jual pengirim (bahan komposisinya / pack bersatok sendiri) |
| Harga | Harga Beli Terakhir (tanpa untung) | Harga jual pengirim |
| Untung pengirim | ❌ | ✅ otomatis |
| Kas cabang berkurang | ❌ | ✅ saat Terima |
| Masuk laporan penjualan pengirim | ❌ | ✅ |
| Stok pengirim berkurang | Saat Kirim | Saat Kirim |
| Stok cabang bertambah | Saat Terima | Saat Terima |
| Perlu diaktifkan | — | Saklar **Jenis Pembeli di POS** di Cabang → Edit (gudang/roastery) + Kas Tunai kedua sisi |

## Pilih yang mana?
- Sekadar memindahkan bahan (mis. roasted bean curah ke outlet) tanpa jual-beli → **①**
- Gudang/Roastery menjual ke cabang dan harus ada untung → **②**

Barang yang sama dipakai di semua lokasi, jadi tidak ada barang dobel saat dipindah.

## Jangan
- Jangan memakai 2 cara untuk barang yang sama (mis. Transfer lalu Kirim juga) — stok cabang jadi dobel.
MD,
    ],

    [
        'slug' => 'cara-menambah-stok', 'judul' => 'Bagaimana menambah stok?', 'kelompok' => 'Cara Melakukan', 'urutan' => 20,
        'letak' => 'Pembelian / Stok & Gudang / Roastery',
        'ringkasan' => 'PO diterima, Penyesuaian Stok, Stok Opname (lebih), Produksi, Roastery, Transfer/Kiriman/Retur diterima. Harga (modal) batchnya berbeda-beda.',
        'izin' => ['stok.view', 'pembelian.view', 'stok.adjustment', 'produksi.view', 'kiriman.view', 'roastery.batch.view'],
        'route_names' => [],
        'terkait' => ['purchase-order', 'penyesuaian-stok', 'produksi', 'transfer-stok', 'kiriman-cabang', 'beli-cherry', 'batch-roasting'],
        'kata_kunci' => 'tambah stok isi stok stok masuk stok awal barang masuk restock',
        'konten' => <<<'MD'
## Ada beberapa cara

| Cara | Kapan dipakai | Harga (modal) batch | Kas |
|---|---|---|---|
| **PO → Terima** | Beli dari supplier | Harga di PO | Berkurang saat Catat Pembayaran |
| **Penyesuaian Stok** (naik) | Stok awal, koreksi | Harga diisi sendiri / Harga Beli Terakhir | Tidak berubah |
| **Stok Opname** (fisik lebih) | Hitung fisik rutin | Harga pokok rata-rata | Tidak berubah |
| **Produksi** (gudang) | Membuat bahan setengah jadi | Biaya bahan ÷ hasil | Tidak berubah |
| **Roastery** (Beli Buah Kopi diterima, Processing, Roasting, Grinding, Packing) | Rantai biji kopi | Harga buah / modal tahap sebelumnya | Tidak berubah (bayar petani dicatat manual) |
| **Transfer** diterima | Dikirim dari lokasi sendiri | Harga Beli Terakhir | Tidak berubah |
| **Kiriman** diterima | Dibeli dari gudang/roastery | Harga kirim | Kas cabang berkurang |

## Pilih yang mana?
- Barang dibeli dari supplier → **PO**. Ini satu-satunya cara yang mencatat harga beli & pembayaran supplier.
- Mulai pakai aplikasi (stok awal) → **Penyesuaian Stok** dengan harga diisi.

## Wajib
- Pastikan batch punya **harga > 0**. Batch Rp0 membuat modal (HPP) penjualan 0 dan laba terlihat terlalu besar.

## Jangan
- Jangan menambah stok barang yang dibeli lewat Penyesuaian — harga beli & pembayaran supplier tidak tercatat.
MD,
    ],

    [
        'slug' => 'cara-mengurangi-stok', 'judul' => 'Bagaimana mengurangi stok?', 'kelompok' => 'Cara Melakukan', 'urutan' => 30,
        'letak' => 'POS / Stok & Gudang / Roastery',
        'ringkasan' => 'Dijual di POS, Pemakaian Perlengkapan, Stok Opname, Penyesuaian Stok, Transfer/Kiriman keluar, Retur, dan dipakai di Produksi/Roastery. Pencatatan keuangannya beda.',
        'izin' => ['stok.view', 'order.create', 'pemakaian_perlengkapan.view', 'stok.adjustment', 'stok_opname.ajukan'],
        'route_names' => [],
        'terkait' => ['pos', 'pemakaian-perlengkapan', 'stok-opname', 'penyesuaian-stok', 'transfer-stok', 'retur-cabang'],
        'kata_kunci' => 'kurangi stok stok keluar buang barang barang rusak barang hilang pakai barang susu basi',
        'konten' => <<<'MD'
## Ada beberapa cara

| Cara | Untuk apa | Tercatat sebagai |
|---|---|---|
| **Dijual di POS** | Bahan komposisi produk | HPP penjualan |
| **Pemakaian Perlengkapan** | Gas, sabun, tisu dapur, ATK (Jenis = Perlengkapan) | Laporan Pemakaian Perlengkapan |
| **Stok Opname** | Hitung fisik rutin: rusak, basi, kulkas mati, hilang, susut, sampel, makan karyawan, salah catat (pakai foto & persetujuan) | Beban per penyebab tanpa kas; barang rusak/basi ditahan di Stok Rusak |
| **Penyesuaian Stok** | Koreksi cepat satu barang | Rusak/hilang/susut → Beban Kerugian Stok; salah hitung/audit yang menurunkan stok → Selisih Persediaan |
| **Transfer / Kiriman** (Kirim) | Pindah ke lokasi lain | Pindah stok (Kiriman: penjualan pengirim) |
| **Retur Cabang** | Kembalikan kiriman | Pindah stok, uang bisa kembali |
| **Produksi / Roastery** | Bahan diolah | Menjadi modal barang hasil |

Stok Barang juga punya **Reset ke 0**, tapi tidak mencatat alasan/beban — hindari.

## Perlu diketahui
- **Tambahan Gratis** (sedotan, tisu) hanya terpotong di POS kalau **Lacak Stok**-nya dicentang. Kalau tidak dicentang, stoknya tidak dihitung.

## Jangan
- Jangan membuang barang basi dengan Reset — pakai **Stok Opname** (penyebab Basi/Kedaluwarsa) atau Penyesuaian alasan **Rusak/Kadaluarsa** supaya nilainya masuk beban.
MD,
    ],

    [
        'slug' => 'cara-membatalkan-transaksi', 'judul' => 'Bagaimana membatalkan atau memperbaiki transaksi penjualan?', 'kelompok' => 'Cara Melakukan', 'urutan' => 40,
        'letak' => 'Penjualan → Riwayat Order / POS / Layout Meja',
        'ringkasan' => 'Ada 4 pilihan: Batalkan, Hapus, Edit, Batalkan Bill Tersimpan. Akibatnya ke stok & kas sangat berbeda.',
        'izin' => ['order.view', 'order.create'],
        'route_names' => [],
        'terkait' => ['riwayat-order', 'pos', 'layout-meja', 'closing'],
        'kata_kunci' => 'batal order batalkan transaksi salah input salah transaksi hapus order koreksi order refund cancel',
        'konten' => <<<'MD'
## Ada 4 pilihan

| Pilihan | Untuk order | Stok | Kas | Laporan |
|---|---|---|---|---|
| **Batalkan** | Sudah dibayar (Selesai) | ✅ Kembali | ✅ Uang dikeluarkan | Keluar dari laporan |
| **Hapus** | Hanya Pending / Dibatalkan | Tidak berubah | Tidak berubah | Disembunyikan |
| **Edit** | Pending: diskon & metode bayar. Selesai/Dibatalkan: hanya nama & catatan | — | Tidak berubah | Pending: total ikut diskon |
| **Batalkan Bill Tersimpan** | Bill Pending di POS / bill meja | — | — | — |

## Pilih yang mana?
- Salah input order yang sudah dibayar → **Batalkan**, lalu buat order baru yang benar, lalu **Tandai Pengganti**.
- Order salah yang sudah dibatalkan dan ingin dibersihkan → **Hapus**.

## Syarat
- Selain Owner, Batalkan hanya untuk order **yang dibuat hari ini** (bawaan: Owner, Admin Pusat, Manajer Cabang).
- Tanggal yang sudah masuk **Closing Periode Final** terkunci — minta Owner Buka Kunci dulu.
- Kiriman ke cabang yang sudah diterima tidak bisa dibatalkan → **Retur Cabang**.

## Jangan
- Diskon/metode bayar order yang sudah dibayar dikunci — koreksinya selalu lewat Batalkan + order baru.
MD,
    ],

    [
        'slug' => 'cara-produk-di-banyak-cabang', 'judul' => 'Bagaimana mengatur produk dan harganya di banyak cabang?', 'kelompok' => 'Cara Melakukan', 'urutan' => 50,
        'letak' => 'Stok & Gudang → Produk Jual',
        'ringkasan' => 'Produk & bahan berlaku untuk semua cabang. Di form Produk Jual, centang cabang yang menjual dan isi harga khusus per cabang kalau berbeda.',
        'izin' => ['master.produk_jual.view'],
        'route_names' => [],
        'terkait' => ['produk-jual', 'bahan-baku', 'cabang', 'cara-mengatur-harga'],
        'kata_kunci' => 'produk semua cabang cabang baru produk per cabang harga per cabang menu beda cabang outlet kecil',
        'konten' => <<<'MD'
## Cara kerjanya
- Produk Jual & Bahan Baku dibuat **sekali** dan berlaku untuk semua lokasi. Stok dicatat per lokasi.
- Di form **Produk Jual**, bagian cabang:
  - Centang/hilangkan cabang yang **menjual** produk itu (mis. outlet kecil tidak menjual makanan berat).
  - Isi **harga per cabang** kalau berbeda; kosong = harga jual umum.
- Produk yang belum pernah diatur cabangnya = dijual di **semua cabang** dengan harga umum.

## Cabang baru
1. Buat cabang (Pengaturan → Cabang & Gudang) + kas Tunai/Transfer/QRIS.
2. Produk otomatis tersedia; hilangkan centang cabang baru di produk yang tidak dijual di sana.
3. Isi stok bahan di cabang baru (Transfer/Kiriman/PO/Penyesuaian) — tanpa stok, produk tampil "Habis".

## Jangan
- Jangan membuat produk dobel bernama sama untuk cabang lain — pakai centang cabang & harga per cabang di produk yang sama.
MD,
    ],

    [
        'slug' => 'cara-produk-tidak-muncul-di-pos', 'judul' => 'Kenapa produk tidak muncul / Habis di POS?', 'kelompok' => 'Cara Melakukan', 'urutan' => 60,
        'letak' => 'POS / Produk Jual / Bahan Baku',
        'ringkasan' => 'Daftar pemeriksaan: cabang aktif, status aktif, cabang yang menjual, tipe, kategori, komposisi, stok bahan, kas default.',
        'izin' => ['order.create', 'master.produk_jual.view'],
        'route_names' => [],
        'terkait' => ['pos', 'produk-jual', 'bahan-baku', 'stok-barang', 'transfer-stok'],
        'kata_kunci' => 'produk tidak muncul produk hilang dari pos stok habis di pos tidak bisa diklik menu tidak muncul kategori tidak muncul tidak bisa bayar kopi habis',
        'konten' => <<<'MD'
## Produk tidak muncul sama sekali
1. **Cabang** di pojok kanan atas sudah benar?
2. Produk **Aktif**? (Produk Jual → Edit → Status Aktif)
3. Cabang ini **dicentang** sebagai cabang yang menjual produk itu? (Produk Jual → Edit → bagian cabang)
4. **Tipe** Produk Jual atau Produk Tambahan? Bahan Baku/Kemasan tidak tampil di POS (kecuali Tambahan Gratis).
5. Sedang memilih **chip kategori** lain? Klik chip "Semua".

## Produk tampil tapi "Habis"
1. Produk punya **komposisi**? Tanpa komposisi produk memakai stoknya sendiri (biasanya 0, kecuali pack Roastery).
   **Tambahan Gratis** dengan Lacak Stok dicentang → cek stoknya di cabang ini, atau hilangkan centang Lacak Stok.
2. Stok **setiap bahan** komposisi cukup di cabang ini? Menu kopi butuh **roasted bean** di outlet — kirim dari Roastery/Gudang lewat Transfer Stok. Lihat tulisan "terbatas: nama bahan" di POS, lalu cek Stok Barang.
3. Satuan takaran benar? (gram vs kg, ml vs liter)
4. Bahan sedang dihitung di Stok Opname? Itu tidak memblokir penjualan — cek stoknya saja.

## Tidak bisa bayar
- "Cabang belum punya Kas default …" → buat/aktifkan kas dengan Default untuk Tunai/Transfer/QRIS.
- Tanggal sudah masuk Closing Periode Final → transaksi ditolak; minta Owner Buka Kunci.
- Ada pita "Mode baca-saja" → langganan habis, hubungi tim IT.

## Kategori tidak muncul sebagai chip
- Kategori baru tampil kalau sudah punya minimal 1 produk aktif.
MD,
    ],

    [
        'slug' => 'cara-membuat-resep', 'judul' => 'Bagaimana membuat resep / komposisi?', 'kelompok' => 'Cara Melakukan', 'urutan' => 70,
        'letak' => 'Produk Jual / Master Bumbu Pusat / Produksi',
        'ringkasan' => 'Ada 3 tempat resep: komposisi Produk Jual, Master Bumbu Pusat, Resep Produksi. Bedanya kapan bahan dipotong.',
        'izin' => ['master.produk_jual.view', 'master.resep_bumbu.view', 'produksi.view'],
        'route_names' => [],
        'terkait' => ['produk-jual', 'master-bumbu-pusat', 'produksi'],
        'kata_kunci' => 'resep komposisi takaran bahan per gelas per porsi hpp modal bumbu sirup racikan',
        'konten' => <<<'MD'
## Ada 3 tempat resep

| | Komposisi Produk Jual | Master Bumbu Pusat | Resep Produksi |
|---|---|---|---|
| Untuk | 1 produk (mis. Cappuccino: 18 g biji, 150 ml susu, cup, tutup) | Campuran yang dipakai banyak produk | Mengolah bahan jadi stok di gudang |
| Bahan dipotong | Saat produk dijual di POS | Saat produk yang menautkannya dijual | Saat klik Catat Produksi |
| Hasilnya | Modal (HPP) per order | Ikut ke HPP produk | Stok barang hasil bertambah |
| Ubah resep | Hanya produk itu | **Semua produk yang menautkan** langsung berubah | Produksi berikutnya |
| Lokasi stok | Cabang tempat produk dijual | Cabang tempat produk dijual | Hanya Gudang Pusat |

## Pilih yang mana?
- Bahan langsung dipakai saat menyajikan → **Komposisi Produk Jual**.
- Banyak produk memakai campuran yang sama → buat **Bumbu Pusat**, lalu Import ke tiap produk.
- Gudang membuat dulu dalam jumlah besar lalu dikirim → **Resep Produksi**; hasilnya (barang setengah jadi) dipakai di komposisi produk.

## Wajib
- Takaran per **1 porsi/1 gelas**. Satuan takaran boleh gram/ml — dikonversi otomatis ke satuan stok bahan (kg/liter/gram).
MD,
    ],

    [
        'slug' => 'cara-mengatur-harga', 'judul' => 'Bagaimana mengatur harga?', 'kelompok' => 'Cara Melakukan', 'urutan' => 80,
        'letak' => 'Bahan Baku / Produk Jual / Role & Hak Akses',
        'ringkasan' => 'Harga jual (umum, per cabang, varian), Harga Beli Terakhir bahan, harga PO, dan izin kasir mengubah harga di POS.',
        'izin' => ['master.produk_jual.view', 'master.bahan_baku.view'],
        'route_names' => [],
        'terkait' => ['produk-jual', 'bahan-baku', 'purchase-order', 'pos', 'cara-produk-di-banyak-cabang'],
        'kata_kunci' => 'harga harga jual harga beli harga per cabang harga beli terakhir harga varian ubah harga promo diskon',
        'konten' => <<<'MD'
## Jenis harga

| Harga | Diatur di | Dipakai untuk |
|---|---|---|
| **Harga Jual** | Produk Jual | Harga di POS & menu QR semua cabang |
| **Harga per cabang** | Produk Jual → bagian cabang | Menggantikan harga jual di cabang itu (kosong = harga jual) |
| **Harga Varian** | Produk Jual → Varian | Harga kombinasi varian (kosong = harga produk) |
| **Harga Beli Terakhir** | Bahan Baku & Kemasan (diperbarui otomatis tiap PO diterima & hasil Produksi/Roastery) | Perkiraan modal di komposisi; harga awal form PO; batch Penyesuaian naik & Transfer |
| **Harga PO** | Form PO | Modal batch yang sebenarnya (FIFO) |
| Ubah harga di POS | Centangan **Ubah Harga Jual di POS** | Tanpa centangan, kasir tidak bisa mengubah harga |

## Jangan
- Untuk potongan/promo, pakai kolom **Diskon** di POS, bukan mengubah harga.
- Jangan mengira Harga Beli Terakhir = modal sebenarnya: modal penjualan selalu dari batch FIFO.
MD,
    ],

    [
        'slug' => 'cara-mengirim-uang-ke-pusat', 'judul' => 'Bagaimana memindahkan uang (cabang ke pusat, antar kas)?', 'kelompok' => 'Cara Melakukan', 'urutan' => 90,
        'letak' => 'Keuangan',
        'ringkasan' => 'Ada 3 cara: Setoran Kasir, Transfer Dana, Transfer Antar Kas. Bedanya di tujuan, persetujuan, dan pengecekan selisih.',
        'izin' => ['setoran_kasir.view', 'setoran.view', 'transfer_antar_kas.view'],
        'route_names' => [],
        'terkait' => ['setoran-kasir', 'transfer-dana', 'transfer-antar-kas', 'kas-transaksi'],
        'kata_kunci' => 'setor uang kirim uang ke pusat pindah uang uang kasir setoran harian transfer bank',
        'konten' => <<<'MD'
## Ada 3 cara

| | Setoran Kasir | Transfer Dana | Transfer Antar Kas |
|---|---|---|---|
| Untuk | Uang tunai penjualan harian kasir | Uang bebas antar cabang | Antar kas di cabang yang sama |
| Jumlah | Dihitung sistem dari POS, kasir isi uang nyata | Diisi sendiri | Diisi sendiri |
| Cek selisih | ✅ | ❌ | ❌ |
| Persetujuan | Pusat Approve/Reject | Penerima Terima/Tolak | Tidak perlu |
| Kas asal berkurang | Saat **Approve** | Saat **Kirim** | Saat Simpan |
| Kas tujuan bertambah | Saat Approve | Saat Terima | Saat Simpan |
| Butuh Gudang Pusat | ✅ (kas tunai pusat) | ❌ | ❌ |

## Pilih yang mana?
- Kasir menyerahkan uang penjualan hari itu ke pusat → **Setoran Kasir**.
- Uang lain dikirim ke cabang lain (modal, pinjaman) → **Transfer Dana**.
- Uang laci disetor ke rekening bank cabang yang sama → **Transfer Antar Kas**.
- Usaha 1 cabang tanpa Gudang Pusat → **Transfer Antar Kas**.

## Jangan
- Jangan memakai 2 cara untuk uang yang sama (mis. Setoran Kasir lalu Transfer Dana lagi) — kas pusat jadi dobel.
MD,
    ],

    [
        'slug' => 'cara-mencatat-uang', 'judul' => 'Bagaimana mencatat uang masuk/keluar?', 'kelompok' => 'Cara Melakukan', 'urutan' => 100,
        'letak' => 'Keuangan / POS / Pembelian / SDM',
        'ringkasan' => 'Sebagian otomatis (POS, PO, setoran, gaji, kiriman), sebagian manual (Kas & Transaksi, Transaksi Berulang). Ini daftarnya.',
        'izin' => ['keuangan.view'],
        'route_names' => [],
        'terkait' => ['kas-transaksi', 'transaksi-berulang', 'purchase-order', 'pos', 'penggajian'],
        'kata_kunci' => 'catat pengeluaran catat pemasukan biaya operasional bayar listrik bayar gaji bayar supplier bayar petani uang keluar uang masuk',
        'konten' => <<<'MD'
## Yang tercatat otomatis (jangan dicatat lagi manual)
| Kejadian | Dicatat oleh |
|---|---|
| Penjualan ke pelanggan | POS (saat order/bill dibayar) |
| Bayar supplier | Purchase Order → Catat Pembayaran |
| Setor/kirim uang | Setoran Kasir / Transfer Dana / Transfer Antar Kas |
| Penjualan gudang/roastery ke cabang | Kiriman Cabang (saat cabang Terima) |
| Gaji karyawan | Penggajian → **Bayar** (pilih kas) |
| Barang rusak/hilang | Penyesuaian Stok / Stok Opname (beban tanpa kas) |

## Yang dicatat manual
| Cara | Untuk |
|---|---|
| **Kas & Transaksi → Tambah Transaksi** | Biaya sekali-sekali (listrik, perbaikan, belanja kecil), **pembayaran ke petani** (Beli Buah Kopi tidak mengubah kas), gas & upah roasting, pemasukan lain |
| **Transaksi Berulang** | Biaya rutin yang sama (sewa, langganan) |

## Wajib
- Pilih **kas** yang benar dan **kategori** yang tepat — kategori menentukan baris di laporan & BEP.

## Jangan
- Jangan mencatat pembelian bahan dari supplier lewat Tambah Transaksi saja — buat **PO** supaya stok & modal (HPP) ikut tercatat; bayarnya lewat "Catat Pembayaran" di PO.
MD,
    ],

    [
        'slug' => 'cara-melihat-untung', 'judul' => 'Bagaimana melihat untung?', 'kelompok' => 'Cara Melakukan', 'urutan' => 110,
        'letak' => 'Penjualan / Laporan / Keuangan',
        'ringkasan' => 'Ada 5 laporan yang menampilkan "untung" dengan hitungan berbeda. Ini bedanya dan kapan dipakai.',
        'izin' => ['penjualan.laporan.keuntungan', 'laporan.laba_rugi.view', 'laporan.laba_rugi_formal.view', 'closing.view', 'laporan.view'],
        'route_names' => [],
        'terkait' => ['laporan-transaksi-kasir', 'laporan-laba-rugi', 'laporan-laba-rugi-formal', 'closing', 'laporan-keuangan'],
        'kata_kunci' => 'untung laba keuntungan rugi profit margin laba bersih laba kotor',
        'konten' => <<<'MD'
## Ada 5 laporan

| Laporan | Untung = | Sudah dikurangi biaya operasional? | Bisa berubah? |
|---|---|---|---|
| **Penjualan → Laporan Transaksi/Produk/Kategori** | Pendapatan (setelah diskon) − HPP bahan | ❌ | Ya (berjalan) |
| **Laporan → Laba Rugi** | Omzet baris order (sebelum diskon order) − HPP bahan | ❌ | Ya |
| **Laporan → Laba Rugi Formal** | Pendapatan − HPP − beban per akun COA (termasuk gaji, sewa, kerugian stok) | ✅ | Ya |
| **Closing Periode** | Omzet bersih − HPP − beban (tanpa transfer internal) | ✅ | **Tidak** — dibekukan saat dikunci (Final) |
| Laporan → Keuangan / Keuangan → Laporan Keuangan | Uang masuk − uang keluar usaha (tanpa transfer internal & saldo awal), **berbasis kas** | ✅ (yang sudah dicatat di kas) | Ya — pembelian bahan dihitung saat dibayar, bukan saat dipakai (HPP) |

## Pilih yang mana?
- Produk mana yang paling menguntungkan → **Laporan Produk** (keuntungan) atau **Laba Rugi per produk**.
- Untung bersih bulan ini → **Laba Rugi Formal**, lalu bekukan dengan **Closing Periode**.
- Angka resmi yang tidak boleh berubah lagi → **Closing Periode** Final.

## Wajib (supaya untung akurat)
- Setiap bahan punya **harga batch > 0** (PO dengan harga benar / stok awal dengan harga).
- Biaya operasional (sewa, listrik, pembelian aset, bayar petani) **dicatat di Kas & Transaksi** dengan kategori yang tepat. Gaji tercatat otomatis saat slip di-**Bayar** di menu Penggajian.
MD,
    ],

    [
        'slug' => 'cara-fitur-tidak-ada', 'judul' => 'Kenapa menu atau fitur tidak ada?', 'kelompok' => 'Cara Melakukan', 'urutan' => 5,
        'letak' => 'Semua menu',
        'ringkasan' => 'Tiga penyebab: tidak termasuk paket aplikasi, belum dicentang di Role & Hak Akses, atau aplikasi sedang baca-saja karena langganan habis.',
        'izin' => [],
        'route_names' => [],
        'terkait' => ['role-hak-akses', 'manajemen-user'],
        'kata_kunci' => 'menu tidak ada fitur hilang tidak bisa dibuka tidak termasuk paket akses ditolak 403 tombol tidak muncul tidak bisa simpan baca saja langganan habis',
        'konten' => <<<'MD'
## Ada 3 penyebab

| Penyebab | Tandanya | Cara mengatasi |
|---|---|---|
| **Tidak termasuk paket** aplikasi usaha Anda | Menu tidak ada untuk **siapa pun**, termasuk Owner. Membuka alamatnya menampilkan "Fitur … tidak termasuk paket Anda" | Hubungi tim IT penyedia aplikasi. Data lama tetap aman |
| **Belum dicentang** untuk role Anda | Owner melihat menunya, Anda tidak | Minta Owner mencentang di **Pengaturan → Role & Hak Akses** |
| **Baca-saja** (langganan habis) | Ada pita di atas halaman; menu terlihat tapi tombol Simpan/transaksi ditolak | Hubungi tim IT untuk memperpanjang langganan |

## Perlu diketahui
- Sebelum baca-saja ada peringatan mulai 14 hari sebelum langganan berakhir, lalu masa tenggang (bawaan 7 hari) di mana aplikasi masih normal.
- Saat baca-saja Anda tetap bisa login, melihat data, dan mengunduh laporan.
- Beberapa menu juga butuh saklar di menu lain (mis. Antrian Produksi, Kitchen Display, Label Cup, Jenis Pembeli di Cabang → Edit) — lihat bagian "Supaya menu ini muncul" di Kamus menu itu.
MD,
    ],
];
