# Rancangan: Produksi Bahan Setengah Jadi + Kirim ke Cabang + Retur

**Status**: 📋 Rancangan (belum ada kode) — menunggu koreksi Owner
**Tanggal**: 2026-11-05
**Hasil diskusi**: Owner + Claude, sesi 2026-11-05

---

## 1. Masalah yang mau diselesaikan

1. **Saus Padang** dibuat sekali banyak (mis. 5 liter) dari tepung, gula, garam, cabai, saus pedas, saus tomat, lalu disimpan dan dipakai di komposisi **Sate Ayam**. Sekarang sistem belum bisa mencatat "hari ini masak 5 liter" — bahan baku tidak berkurang dan modal Saus Padang tidak terhitung.
2. **Gerobak keliling**: gudang membuat **Cup Kopi Isi**, mengirimnya ke gerobak, gerobak menjual **Kopi Susu Gula Aren** (Cup Kopi Isi + susu + gula aren + pipet).
3. **Harga kirim gudang → cabang**: ada usaha yang gudangnya mengambil untung (harga dinaikkan), ada yang gudangnya hanya DC (harga sama dengan modal). Sekarang diakali lewat PO cabang + POS gudang. Perlu alur resmi, plus **retur**.

## 2. Keputusan Owner (ringkas)

| No | Keputusan |
|----|-----------|
| 1 | Menu baru **Produksi**, **hanya di gudang** |
| 2 | Saus Padang dan Cup Kopi Isi = jenis yang sama (**bahan setengah jadi**), satu menu Produksi untuk semuanya |
| 3 | Modal hasil produksi **otomatis** dari harga bahan, **bisa diubah manual** |
| 4 | Hasil sebenarnya bisa diisi (rencana 5 liter, jadi 4,8 liter) |
| 5 | Gerobak = **cabang tersendiri** (stok, kas, kasir, laporan sendiri) |
| 6 | Menu gerobak = **menu terpisah**, mis. "Kopi Susu Gula Aren (Gerobak)", aktif hanya di gerobak (Cara 1) |
| 7 | Kirim ke cabang = **gudang menjual Produk Jual** ke cabang; harga boleh naik atau sama dengan modal |
| 8 | Barang yang mau dikirim **wajib dibuatkan Produk Jual dulu** (sekali) |
| 9 | Persen otomatis (+10%) **ditunda** |
| 10 | Uang **langsung dipotong dari kas cabang** (utang belum dipakai) |
| 11 | Barang masuk ke cabang setelah cabang menekan **Terima** (a2) — **Roastery ikut** |
| 12 | Retur: **dipilih setiap kali** uang dikembalikan atau tidak (b3) |
| 13 | Barang basi: retur ke gudang → dibuang lewat **Penyesuaian Stok** di gudang |

## 3. Gambaran alur lengkap (contoh gerobak)

```
GUDANG                                   GEROBAK 1
──────                                   ─────────
PO beli biji kopi, cup, susu, pipet
        │
Produksi: buat 50 Cup Kopi Isi
  biji kopi −0,9 kg, cup −50
  Cup Kopi Isi +50 (modal Rp3.000/cup)
        │
POS gudang → "Kirim ke Cabang: Gerobak 1"
  Cup Kopi Isi (kirim ke cabang) ×50 @Rp3.500
  Susu (kirim ke cabang) ×2 L
  status: MENUNGGU DITERIMA
  stok gudang sudah berkurang ──────────►  muncul di "Barang Masuk dari Gudang"
                                           cabang cek barang → klik TERIMA
                                             Cup Kopi Isi +50 (modal Rp3.500)
                                             Susu +2 L
                                             kas tunai gerobak −Rp…
  pendapatan gudang tercatat ◄──────────── (saat Terima)
                                                   │
                                           POS gerobak: jual Kopi Susu Gula Aren (Gerobak)
                                             Cup Kopi Isi −1, susu −…, gula aren −…, pipet −1
                                                   │
                                           Sore: 5 cup basi → RETUR ke gudang
                                             pilih: uang kembali? Ya / Tidak
        ◄────────────────────────────────── 
Terima retur → Penyesuaian Stok (buang)
```

---

## 4. Bagian A — Menu Produksi

### 4.1 Resep Produksi

Diatur **di dalam menu Produksi** (tab "Resep Produksi"), **bukan** di form Master Bahan Baku — supaya tampilan Master Bahan Baku yang sudah go-live tidak berubah.

```
Resep Produksi: Saus Padang                 Hasil per resep: 1 liter
┌────────────────┬─────────┬────────┐
│ Bahan          │ Takaran │ Satuan │
├────────────────┼─────────┼────────┤
│ Tepung         │ 100     │ g      │
│ Gula           │  50     │ g      │
│ Garam          │  10     │ g      │
│ Cabai          │ 200     │ g      │
│ Saus Pedas     │ 150     │ ml     │
│ Saus Tomat     │ 300     │ ml     │
└────────────────┴─────────┴────────┘
Perkiraan modal per liter: Rp 18.450   (dari harga beli bahan)
```

- Barang hasil (Saus Padang, Cup Kopi Isi) **didaftarkan dulu di Master Bahan Baku** seperti bahan biasa (satuan liter/pcs). Barang yang punya Resep Produksi otomatis dianggap **bahan setengah jadi**.
- Satu barang hasil = satu resep. Bahan di resep boleh bahan setengah jadi lain (mis. Sirup Gula Aren buatan sendiri dipakai di resep lain).
- Konversi satuan memakai aturan yang sama dengan komposisi Produk Jual (g→kg, ml→liter, sesuai satuan stok bahan).

### 4.2 Catat Produksi

```
Produksi Baru — Gudang Pusat
Barang      : [Saus Padang ▼]
Rencana     : [5] liter          → kebutuhan bahan ditampilkan + cek stok cukup
──── setelah selesai masak ────
Hasil nyata : [4,8] liter
Modal/liter : Rp 19.219  (otomatis = total biaya bahan ÷ hasil nyata)  [ubah manual]
Catatan     : ...
[Simpan Produksi]
```

Saat **Simpan**:
1. Stok bahan baku gudang berkurang sesuai resep × rencana (modal FIFO yang sebenarnya, lewat `StokService::keluar()`).
2. Stok barang hasil bertambah sebanyak **hasil nyata**, dengan modal per unit = otomatis atau angka manual (`StokService::masuk()`).
3. `harga_beli_terakhir` barang hasil diperbarui (pola Roastery, supaya angka modal referensi ikut benar).
4. Ditolak kalau stok salah satu bahan tidak cukup (pesan jelas bahan mana yang kurang).

Kalau modal diubah manual, selisih dengan biaya bahan sebenarnya dicatat di riwayat produksi (supaya bisa ditelusuri), tetapi **tidak** membuat transaksi keuangan baru.

**Batalkan produksi**: hanya kalau barang hasil belum terpakai/terkirim — stok bahan dikembalikan, stok hasil ditarik.

### 4.3 Siapa yang boleh

- Lokasi: **hanya cabang tipe Gudang Pusat** (keputusan Owner). Roastery tetap memakai menu batch Roastery sendiri.
- Izin baru: `produksi.view`, `produksi.create`, `produksi.batalkan`, `produksi.resep.kelola`.
- Bawaan: Admin Pusat, Admin Gudang (semua); Operator Produksi (view + create). Owner selalu.

---

## 5. Bagian B — Kirim ke Cabang (lewat Produk Jual)

### 5.1 Persiapan (sekali per barang)

Buat Produk Jual biasa di Master Produk Jual, aktif **hanya di gudang**:

| Produk Jual | Harga | Komposisi |
|-------------|-------|-----------|
| Cup Kopi Isi (kirim ke cabang) | Rp3.500 (atau Rp3.000 kalau gudang tidak ambil untung) | Cup Kopi Isi 1 pcs |
| Susu UHT (kirim ke cabang) | Rp20.000 | Susu UHT 1 liter |

**Aturan barang yang masuk ke cabang**: bahan yang ada di **komposisi** Produk Jual itu, dikali qty. Jual 50 → cabang menerima 50 Cup Kopi Isi.
- Produk Jual tanpa komposisi **tidak bisa** dikirim ke cabang (ditolak dengan pesan).
- Kalau komposisi berisi lebih dari 1 bahan (paket), semua bahan masuk ke cabang; modal di cabang dibagi menurut porsi modal gudang setiap bahan.

### 5.2 Mencatat kiriman di POS gudang

Blok **"Jenis Pembeli"** yang sekarang hanya ada di POS Roastery dimunculkan juga di POS **Gudang Pusat**:

```
Jenis Pembeli: ( ) Umum  ( ) Grosir  (•) Kirim ke Cabang
Cabang tujuan: [Gerobak 1 ▼]
```

Klik **Kirim** (menggantikan "Bayar" untuk jenis ini — tidak ada pembayaran di gudang):
- Stok gudang langsung berkurang (barang sudah keluar, "dalam perjalanan").
- Order tercatat dengan status kirim **Menunggu Diterima**. Belum dihitung sebagai pendapatan gudang di laporan mana pun.
- Struk/surat jalan bisa dicetak.

### 5.3 Cabang menekan Terima

Halaman baru **"Barang Masuk dari Gudang"** di cabang (dan badge jumlah yang menunggu):

```
KRM-20261105-003  dari Gudang Pusat   5 Nov 10:15
  Cup Kopi Isi   50 pcs   @Rp3.500
  Susu UHT        2 L     @Rp20.000
  Total Rp215.000 — dipotong dari Kas Tunai Gerobak 1
[Terima]  [Tolak]
```

**Terima**:
1. Stok bahan masuk ke cabang, modal per unit = harga kirim (setelah diskon).
2. Kas Tunai cabang berkurang + transaksi pengeluaran "Pembelian dari Gudang".
3. Kas gudang bertambah + order menjadi **Selesai** → pendapatan gudang tercatat (di tanggal Terima).

**Tolak** (barang tidak jadi diterima): stok kembali ke gudang, order dibatalkan, tidak ada uang yang berpindah.

### 5.4 Roastery ikut a2

Penjualan Internal Roastery sekarang langsung memasukkan stok ke outlet dan memotong kas outlet saat dibayar. Diubah: outlet harus menekan **Terima** dulu, alurnya sama dengan 5.3.
- Dicek di salinan data go-live (2026-11-05): 1 cabang, **0 penjualan internal** → tidak ada data lama yang terpengaruh.
- Penjualan Internal lama (kalau ada di demo) dianggap sudah diterima.

### 5.5 Laporan

- Penjualan ke cabang dihitung sebagai pendapatan **gudang** di laporan per cabang, sejak diterima.
- Di **Semua Cabang** / Closing Konsolidasi tidak dihitung (supaya tidak dobel) — aturan yang sekarang berlaku untuk Roastery (`customer_type = internal`) langsung berlaku untuk gudang.
- Kiriman yang belum diterima tidak dihitung di laporan mana pun.

---

## 6. Bagian C — Retur Cabang → Gudang

```
Retur ke Gudang — Gerobak 1
Dari kiriman : [KRM-20261105-003 ▼]
  Cup Kopi Isi   diterima 50, sudah diretur 0   → retur [5]
Alasan       : [Basi ▼]  (Basi / Rusak / Kelebihan kirim / Lainnya)
Uang dikembalikan? (•) Ya   ( ) Tidak
[Kirim Retur]
```

- Retur hanya bisa dari kiriman yang sudah **diterima**, maksimal qty diterima dikurangi yang sudah diretur.
- **Kirim Retur** (di cabang): stok cabang berkurang. Status **Menunggu Diterima Gudang**.
- **Terima Retur** (di gudang): stok gudang bertambah.

| | Uang dikembalikan = **Ya** | Uang dikembalikan = **Tidak** |
|---|---|---|
| Kas cabang | + nilai retur | tidak berubah |
| Kas gudang | − nilai retur | tidak berubah |
| Pendapatan gudang | berkurang (baris "Retur dari cabang") | tidak berubah |
| Modal barang yang masuk ke gudang | modal asli gudang | Rp0 (rugi sudah ditanggung cabang) |
| Yang menanggung rugi barang basi | gudang | cabang |

- Barang basi/rusak lalu **dibuang lewat Penyesuaian Stok** di gudang (menu yang sudah ada). Barang yang masih bagus (mis. kelebihan kirim) tetap di stok gudang dan bisa dikirim lagi.

---

## 7. Bagian D — Gerobak (tanpa kode baru)

Owner mengisi sendiri lewat menu yang sudah ada:
1. **Cabang → Tambah**: "Gerobak 1", tipe Cabang.
2. Kas Tunai untuk gerobak (Keuangan → Kas).
3. User kasir gerobak (cabang default = Gerobak 1).
4. Produk Jual "Kopi Susu Gula Aren (Gerobak)" — komposisi Cup Kopi Isi 1 + susu + gula aren + pipet, aktif hanya di gerobak.

Catatan: POS gerobak butuh internet. Kebutuhan offline dibahas terpisah (diskusi angkringan).

---

## 8. Data yang ditambah (teknis)

| Tabel / kolom | Isi |
|---|---|
| `resep_produksi` | item_id hasil (unik), qty_hasil per resep, satuan, catatan |
| `resep_produksi_items` | resep_produksi_id, item_id bahan, qty, satuan |
| `produksi_batches` | kode PRD-YYYYMM-XXXX, cabang_id, item_id hasil, qty_rencana, qty_hasil, biaya_bahan, modal_per_unit, modal_manual (bool), status (selesai/dibatalkan), created_by |
| `produksi_batch_items` | bahan yang terpakai + qty + biaya FIFO (riwayat) |
| `orders.status_kirim` | nullable: `menunggu` / `diterima` / `ditolak` — hanya untuk penjualan ke cabang; kosong untuk order biasa |
| `orders.diterima_at`, `orders.diterima_by` | waktu & user yang menekan Terima |
| `retur_internal` + `retur_internal_items` | order_id kiriman, dari/ke cabang, alasan, uang_kembali (bool), status (menunggu/diterima), qty per item |

- Tidak ada kolom yang diubah/dihapus di tabel lama. Semua migration bisa di-rollback.
- `Order::terhitung()` (aturan bersama laporan, 4.48) ditambah: penjualan ke cabang yang `status_kirim` masih `menunggu`/`ditolak` tidak dihitung → semua laporan otomatis ikut.
- Resep Produksi **sengaja tabel terpisah** dari `resep_bumbu` (komposisi Produk Jual). Artinya berbeda: komposisi = dipotong **saat menu terjual**, resep produksi = dipakai **saat memasak**. `Item::resep` (relasi ke `resep_bumbu`) dipakai POS (`PenjualanService`), cek "bisa dijual" (`Item`), dan Master Produk Jual untuk menganggap barang itu "menu yang punya komposisi". Kalau Saus Padang ikut tabel yang sama, bagian-bagian itu bisa salah membaca Saus Padang sebagai menu. Tabel terpisah = 0 perubahan pada logika POS yang sudah go-live.
- Semua mutasi stok lewat `StokService` (FIFO + kartu stok), semua multi-tabel dalam `DB::transaction()`, filter cabang manual (CLAUDE.md 3.4).

## 9. Urutan pengerjaan

| Tahap | Isi | Menyentuh fitur go-live? |
|---|---|---|
| 1 | Menu Produksi + Resep Produksi | Tidak (menu baru) |
| 2 | Kirim ke Cabang dari POS gudang + halaman Barang Masuk (Terima/Tolak) + Roastery ikut a2 + aturan laporan | **Ya**: POS (blok Jenis Pembeli), alur Internal Roastery, `Order::terhitung()` |
| 3 | Retur cabang → gudang | Tidak (menu baru) |
| 4 | Panduan, tooltip, izin, uji alur lengkap (produksi → kirim → terima → gerobak jual → retur → buang) | — |

Setiap tahap: test Feature + regresi + commit lokal. Push & deploy hanya setelah Owner setuju.

## 10. Pertanyaan yang masih terbuka

1. **Terima sebagian**: kalau yang sampai hanya 48 dari 50 cup — (a) cabang hanya boleh Terima semua atau Tolak semua; (b) cabang mengisi jumlah yang sampai, sisanya kembali ke stok gudang; (c) sisanya dianggap hilang di jalan (dibuang). Usul Claude: (b).
2. **Barang setengah jadi di PO**: apakah Saus Padang/Cup Kopi Isi kadang dibeli jadi dari supplier? Kalau tidak pernah, disembunyikan dari pilihan barang PO. Usul Claude: tetap tampil (lebih fleksibel), tidak ada yang rusak.
3. **Kas cabang tidak cukup** saat Terima: tetap boleh (kas boleh minus, sama seperti Roastery sekarang) atau ditolak? Usul Claude: tetap boleh, dengan peringatan.
4. **Siapa yang boleh Terima/Tolak di cabang**: usul Claude — Kasir, Manajer Cabang, Admin Pusat (izin baru `kiriman.terima`).
