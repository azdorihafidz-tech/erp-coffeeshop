# FASE E — QR Table Ordering System

> Design blueprint. Belum ada implementasi kode — dokumen ini dibuat untuk disepakati dulu sebelum E1 mulai. Rujukan aturan kerja & verifikasi: `CLAUDE.md` section 3.7.

**Tanggal dibuat**: 2026-09-22
**Status**: 📋 Design blueprint — menunggu approval untuk mulai E1

---

## RINGKASAN EKSEKUTIF

- **Nama fitur**: QR Table Ordering System
- **Owner**: Kopi Drip Sidikalang
- **Estimasi**: ~2 minggu kerja (7 sub-fase, E1–E7)
- **Dependencies**: Fitur cabang, item, kategori, penjualan, printer (**SUDAH ADA** di codebase, reuse — bukan bangun ulang)

---

## FITUR UTAMA

1. Master Meja + QR Generator (permanent sticker & temporary QR)
2. Public Menu Page via QR (guest access, no login)
3. Bill Session Manager (open → add → transfer → close)
4. Kasir POS Dashboard extended (layout meja visual + approve queue)
5. Kitchen Display (optional per outlet, dengan 2 status: Siap / Komplain)
6. Timer per Meja (waktu terisi otomatis)
7. Notifikasi Kasir (order baru, komplain barista, meja lama tanpa order)
8. Estimasi Waktu Siap (per menu → total per order)

---

## FLOW UTAMA

### Flow A: QR Customer Self-Order

```
1. Pelanggan duduk di Meja 5
2. Scan QR permanent di meja
3. Buka halaman menu Kopi Drip (guest, no login)
4. Header: "Meja 5 - Outlet 1 - Kopi Drip Sidikalang"
5. Browse menu 9 kategori (sync dari POS master)
6. Add ke keranjang, pilih varian (size, susu, gula)
7. Checkout: pilih Bayar Dulu (QRIS) ATAU Bayar di Kasir (open bill)
8. Submit → order masuk QUEUE APPROVE di kasir
9. Kasir approve → order aktif di sistem, bill dibuka
10. Kasir print 2 struk: (a) customer copy tempel meja, (b) dapur copy
11. Kalau kitchen display aktif: order muncul di tablet dapur
12. Barista tandai "Siap" per item saat selesai
13. Pelanggan boleh nambah order (scan QR lagi) → append ke bill sama
14. Selesai → bayar di kasir → bill ditutup → meja status kosong
```

### Flow B: Walk-in Manual (Kasir input)

```
1. Pelanggan langsung ke kasir
2. Kasir tanya "Meja mana?"
3. Kasir buka POS → klik layout meja → pilih Meja 5
4. Input pesanan seperti POS biasa
5. Sub-tipe transaksi: "Dine-in via Kasir"
6. Bill dibuka, print struk, dst (same as flow A step 10+)
```

### Flow C: Kasir Tandai Meja Terisi (belum ada order)

```
1. Pelanggan duduk dulu, belum pesan
2. Kasir klik Meja 5 di layout → tombol "Tandai Terisi"
3. Timer meja mulai jalan (waktu duduk)
4. Bill masih kosong (belum ada item)
5. Nanti pelanggan pesan (scan QR atau ke kasir) → item masuk bill
```

### Flow D: Transfer Meja (Pindah)

```
1. Pelanggan pindah dari Meja 5 ke Meja 8 (misal Meja 5 rusak, atau minta pindah)
2. Kasir klik Meja 5 → tombol "Transfer" → pilih Meja 8
3. Bill ikut pindah, timer meja lama disimpan sebagai record, timer meja baru mulai
4. Meja 5 status kosong, Meja 8 terisi dengan bill existing
```

---

## ARSITEKTUR DATABASE

### Tabel Baru

**1. `mejas`** — master meja per cabang
| Kolom | Detail |
|---|---|
| id | PK |
| cabang_id | FK cabangs |
| nomor_meja | int |
| nama_meja | mis. "Meja 5" |
| kapasitas | int |
| lokasi | enum: indoor/outdoor/vip |
| qr_token | unique random 20 char |
| qr_type | enum: permanent/temporary |
| status | enum: aktif/nonaktif |
| created_at, updated_at | |

**2. `bills`** — sesi bill per meja
| Kolom | Detail |
|---|---|
| id | PK |
| meja_id | FK mejas |
| cabang_id | FK cabangs |
| nomor_bill | auto sequence per cabang |
| status | enum: open/waiting_payment/closed/cancelled |
| sub_total, diskon, pajak, total | |
| sumber_awal | enum: qr/kasir |
| created_by | user_id kasir yang buka, nullable kalau dari QR |
| started_at, closed_at | |
| closed_by | user_id kasir yang close |

> **Catatan**: 1 meja bisa punya banyak bill history, tapi maksimal 1 bill berstatus `open` atau `waiting_payment` dalam satu waktu (constraint aplikasi, bukan DB unique — karena histori `closed`/`cancelled` boleh banyak).

**3. `bill_items`** — item dalam bill (adaptasi dari `order_items`)
| Kolom | Detail |
|---|---|
| id | PK |
| bill_id | FK bills |
| item_id | FK items |
| qty, harga_satuan, subtotal | |
| varian | JSON |
| catatan | text nullable |
| status_dapur | enum: pending/siap/komplain |
| status_dapur_at, status_dapur_by | |
| urutan_masuk | int, untuk sortir "yang lebih dulu order" |

> **Catatan**: kalau bill di-close, baris ini ikut jadi `order_items`/penjualan final (lihat E4) — reuse struktur existing, bukan tabel paralel permanen.

**4. `order_queues`** — antrian order dari QR yang menunggu approve kasir
| Kolom | Detail |
|---|---|
| id | PK |
| meja_id | FK mejas |
| cabang_id | FK cabangs |
| payload | JSON isi keranjang |
| status | enum: pending/approved/rejected |
| created_at, approved_at, approved_by | |
| rejected_reason | text nullable |

**5. `table_events`** — log semua event per meja (audit trail)
| Kolom | Detail |
|---|---|
| id | PK |
| meja_id | FK mejas |
| bill_id | FK bills, nullable |
| event_type | enum: occupied/order_added/paid/transferred/vacated/kitchen_ready/kitchen_complaint |
| event_data | JSON |
| user_id | nullable |
| created_at | |

**6. `kitchen_display_settings`** — pengaturan kitchen display per cabang
| Kolom | Detail |
|---|---|
| id | PK |
| cabang_id | FK cabangs |
| is_active | boolean |
| auto_refresh_seconds | int |
| alert_sound_url | nullable |
| dst | |

### Kolom Tambahan di Tabel Existing

- **`items`**: kolom `waktu_siap_menit` (int, default 0, nullable) — estimasi waktu bikin
- **`cabangs`**: kolom `qr_ordering_active` (boolean, default false) — toggle per outlet, konsisten pola `dine_in_aktif`/`takeaway_aktif`/`frozen_aktif` yang sudah ada (CLAUDE.md 1.4, jangan hardcode)

---

## API ENDPOINTS

### Public (guest, no auth)
- `GET /order/{cabang_slug}/{meja_nomor}?token={qr_token}` — halaman menu
- `POST /order/submit` — submit order (via QR)
- `GET /order/status/{queue_id}` — cek status approve (pelanggan)

### Kasir (auth required)
- `GET /kasir/layout-meja/{cabang_id}` — layout visual meja
- `GET /kasir/meja/{meja_id}/detail` — detail meja + bill aktif
- `POST /kasir/meja/{meja_id}/tandai-terisi`
- `POST /kasir/order-queue/{queue_id}/approve`
- `POST /kasir/order-queue/{queue_id}/reject`
- `POST /kasir/bill/{bill_id}/tambah-item` — kasir input item ke bill existing (walk-in flow)
- `POST /kasir/bill/{bill_id}/transfer` — pindah bill ke meja lain
- `POST /kasir/bill/{bill_id}/bayar` — close bill (bayar)
- `GET /kasir/bill/{bill_id}/print-struk` — print 2 struk (customer + dapur)

### Master (owner/admin)
- CRUD `/master/meja` — kelola meja per cabang
- `POST /master/meja/{id}/generate-qr` — regenerate QR token
- `GET /master/meja/{id}/print-qr` — download PDF QR untuk print sticker
- `POST /master/meja/temporary` — generate QR temporary untuk event

### Kitchen Display
- `GET /kitchen/{cabang_id}` — halaman kitchen (auto-refresh)
- `POST /kitchen/bill-item/{id}/tandai-siap`
- `POST /kitchen/bill-item/{id}/komplain` — dengan alasan

---

## UI/UX SKETCH

### Halaman Public Menu (QR)
- Mobile-first responsive (customer pakai HP)
- Header: Logo Kopi Drip, nama outlet, "Meja X"
- Body: List kategori tab (Kopi, Manual Brew, dst), grid menu dengan foto/nama/harga
- Footer sticky: Keranjang (jumlah item, total)
- Checkout screen: Review order, pilih payment mode (Bayar Dulu QRIS / Open Bill), input catatan opsional
- Post-submit: "Order terkirim, menunggu konfirmasi kasir..." polling status

### Layout Meja di Kasir POS
- Grid visual, kotak-kotak meja per cabang
- Warna kotak sesuai status:
  - Abu-abu: Kosong
  - Hijau muda: Terisi < 30 menit
  - Kuning: Terisi 30–90 menit
  - Merah muda: Terisi > 90 menit (mungkin butuh follow-up)
  - Biru: Waiting Payment (bill sudah minta close)
- Setiap kotak menampilkan: nomor meja, timer (kalau terisi), jumlah item di bill
- Klik kotak → drawer/modal detail bill

### Kitchen Display
- Fullscreen, dark background (kontras di dapur)
- Grid card order pending
- Setiap card: Meja X, item list, timer sejak order masuk
- Tombol besar per item: [✅ Siap] [⚠️ Komplain]
- Auto-refresh tiap 10 detik (bisa disetel)

---

## RENCANA PENGERJAAN BERTAHAP

### E1: Master Meja + QR Generator (2–3 hari)
- Migration `mejas`, kolom `qr_token` unique
- Model `Meja` + relasi `Cabang`
- CRUD via `/master/meja`
- QR Code generator (pakai library `simplesoftwareio/simple-qrcode`)
- PDF print QR sticker (pakai `barryvdh/laravel-dompdf` yang sudah ada)
- Temporary QR generator
- **Verifikasi 3.7**: syntax, cache, error log, route smoke, smoke test create meja + generate QR

### E2: Public Menu Page (3–4 hari)
- Route `/order/{cabang}/{meja}` tanpa auth middleware
- View mobile-first
- Reuse struktur menu dari POS (query items + categories)
- Cart state (session-based, guest token)
- Submit endpoint → masuk `order_queues`
- Polling status endpoint
- **Verifikasi 3.7**: mobile responsive, guest access (no login), submit ke queue works

### E3: Kasir POS Extended (Layout Meja + Approve Queue) (3–4 hari)
- View layout meja (grid kotak dengan status warna)
- Endpoint approve/reject `order_queue`
- Endpoint tandai meja terisi manual
- Endpoint tambah item ke bill existing (walk-in)
- Endpoint transfer bill antar meja
- Detail bill modal
- **Verifikasi 3.7**: approve queue works, print struk 2 slip works

### E4: Bill Session Manager + Bayar (2 hari)
- Endpoint bayar bill → close bill → convert ke penjualan final
- Update stok potong lewat resep bumbu (LOGIC EXISTING, tinggal panggil)
- Sub-tipe transaksi: "Dine-in via QR", "Dine-in via Kasir"
- Update `TipeTransaksi` enum
- **Verifikasi 3.7**: bill close → penjualan sukses, stok terpotong benar

### E5: Kitchen Display (2 hari)
- View `/kitchen/{cabang_id}`
- Setting kitchen display per cabang (aktifkan/nonaktifkan)
- Endpoint tandai siap/komplain per `bill_item`
- Auto-refresh JS/polling
- **Verifikasi 3.7**: kitchen display auto-refresh, mark siap works

### E6: Timer + Notifikasi + Estimasi (2 hari)
- Timer meja: JS di layout kasir hitung dari `started_at`
- Notifikasi kasir: badge counter di header + alert sound saat queue masuk
- Estimasi waktu siap: hitung dari `waktu_siap_menit` item + antrian saat itu
- **Verifikasi 3.7**: timer akurat, notifikasi trigger benar

### E7: Testing End-to-End + Polish (2–3 hari)
- Full user journey test: scan QR → order → approve → bikin → siap → bayar
- Test edge case: pindah meja saat pending, batalkan order, dst
- Test multi-cabang isolasi
- Test kitchen display on/off per cabang
- UI polish (warna, spacing, animasi)
- Update `CLAUDE.md` riwayat Fase E selesai

---

## FAQ / EDGE CASE

- **Bagaimana kalau QR di-scan orang lewat (bukan pelanggan meja)?** → order masuk queue, kasir bisa reject dengan alasan "salah scan"
- **Bagaimana kalau internet mati di HP pelanggan?** → order gagal submit, retry otomatis / pakai kasir manual
- **Bagaimana kalau printer struk mati?** → order tetap masuk sistem, kasir bisa print ulang manual dari detail bill
- **Bagaimana kalau kasir approve order tapi bahan habis?** → kasir reject dengan alasan, refund kalau sudah bayar QRIS (proses manual via dashboard)
- **Bagaimana kalau meja belum di-generate QR-nya?** → tampilkan warning di master, tombol "Generate QR sekarang"
- **Bagaimana kalau 2 pelanggan scan QR meja yang sama bersamaan?** → 2 order queue terpisah, kasir putuskan mau approve keduanya atau reject salah satu

---

## Keputusan Kunci (disepakati sebelum E1 mulai)

- **Approve queue kasir** (bukan auto-approve) — order dari QR tidak langsung masuk sistem, harus dikonfirmasi kasir dulu (kontrol stok/kesiapan)
- **2 sub-tipe dine-in**: "Dine-in via QR" dan "Dine-in via Kasir" — beda sumber, sama-sama masuk `bills`
- **Session bill = 1 grup pelanggan** — bill di-close saat bayar → meja otomatis kembali status kosong
- **Kitchen Display opsional per outlet** — toggle `kitchen_display_settings.is_active`, tidak dipaksa semua outlet pakai
- **Reuse maksimal**: resep/potong-stok, split payment, printer struk, permission pattern — SEMUA pakai infrastruktur existing (CLAUDE.md 3.3), modul baru cuma untuk konsep meja/bill/queue/kitchen yang genuinely belum ada

---

**Status akhir dokumen**: menunggu approval Owner untuk mulai eksekusi E1. Jangan mulai coding sebelum ada instruksi eksplisit.
