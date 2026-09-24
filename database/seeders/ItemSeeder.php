<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Item menu Kopi Drip Sidikalang (Coffee Shop + Roastery + Eatery) -
     * Fase B (2026-09-22). Foto sengaja NULL (placeholder ikon generik
     * ditangani di level view POS).
     *
     * "Kopi Sidikalang" (KPI-008) sengaja diberi `punya_varian=true` — jadi
     * item contoh untuk fitur Varian (Size S/M/L). Data kombinasi varian
     * belum diisi (lihat CLAUDE.md TODO 12.6, ItemVarianSeeder dead-code
     * warisan Dimsum sudah dihapus 2026-09-22).
     *
     * Tipe di sini pakai nilai `produk_jual`/`bahan_baku` (bukan lagi
     * produk_jadi/lainnya warisan lama). Kalau ada item baru ditambah,
     * pastikan tipe-nya konsisten dengan whitelist tipe di
     * `Item::bisaDijualDiCabang()` (lihat CLAUDE.md 8.3).
     */
    public function run(): void
    {
        $catId = fn (string $kode) => ItemCategory::where('kode_kategori', $kode)->value('id');

        $items = [
            // ===== Kopi (produk jual) =====
            ['kode_item' => 'KPI-001', 'nama_item' => 'Espresso',          'item_category_id' => $catId('KPI'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 15000, 'harga_beli_terakhir' => 4000, 'qty_minimum' => 20],
            ['kode_item' => 'KPI-002', 'nama_item' => 'Americano',         'item_category_id' => $catId('KPI'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 18000, 'harga_beli_terakhir' => 5000, 'qty_minimum' => 20],
            ['kode_item' => 'KPI-003', 'nama_item' => 'Kopi Susu',         'item_category_id' => $catId('KPI'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 20000, 'harga_beli_terakhir' => 6000, 'qty_minimum' => 30],
            ['kode_item' => 'KPI-004', 'nama_item' => 'Cappuccino',        'item_category_id' => $catId('KPI'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 25000, 'harga_beli_terakhir' => 8000, 'qty_minimum' => 20],
            ['kode_item' => 'KPI-005', 'nama_item' => 'Caffe Latte',       'item_category_id' => $catId('KPI'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 25000, 'harga_beli_terakhir' => 8000, 'qty_minimum' => 20],
            ['kode_item' => 'KPI-006', 'nama_item' => 'Vanilla Latte',     'item_category_id' => $catId('KPI'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 28000, 'harga_beli_terakhir' => 9000, 'qty_minimum' => 15],
            ['kode_item' => 'KPI-007', 'nama_item' => 'Caramel Macchiato', 'item_category_id' => $catId('KPI'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 30000, 'harga_beli_terakhir' => 10000, 'qty_minimum' => 15],
            ['kode_item' => 'KPI-008', 'nama_item' => 'Kopi Sidikalang',   'item_category_id' => $catId('KPI'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 22000, 'harga_beli_terakhir' => 6500, 'qty_minimum' => 25, 'punya_varian' => true],

            // ===== Manual Brew (produk jual) =====
            ['kode_item' => 'MBW-001', 'nama_item' => 'V60',            'item_category_id' => $catId('MBW'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 25000, 'harga_beli_terakhir' => 7000, 'qty_minimum' => 10],
            ['kode_item' => 'MBW-002', 'nama_item' => 'Aeropress',      'item_category_id' => $catId('MBW'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 25000, 'harga_beli_terakhir' => 7000, 'qty_minimum' => 10],
            ['kode_item' => 'MBW-003', 'nama_item' => 'French Press',   'item_category_id' => $catId('MBW'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 22000, 'harga_beli_terakhir' => 6000, 'qty_minimum' => 10],
            ['kode_item' => 'MBW-004', 'nama_item' => 'Kopi Tubruk',    'item_category_id' => $catId('MBW'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 15000, 'harga_beli_terakhir' => 4000, 'qty_minimum' => 15],

            // ===== Non-Kopi (produk jual) =====
            ['kode_item' => 'NKP-001', 'nama_item' => 'Es Teh Manis',   'item_category_id' => $catId('NKP'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 8000,  'harga_beli_terakhir' => 2000, 'qty_minimum' => 30],
            ['kode_item' => 'NKP-002', 'nama_item' => 'Teh Tarik',      'item_category_id' => $catId('NKP'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 15000, 'harga_beli_terakhir' => 4000, 'qty_minimum' => 20],
            ['kode_item' => 'NKP-003', 'nama_item' => 'Cokelat Panas',  'item_category_id' => $catId('NKP'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 20000, 'harga_beli_terakhir' => 6000, 'qty_minimum' => 15],
            ['kode_item' => 'NKP-004', 'nama_item' => 'Matcha Latte',   'item_category_id' => $catId('NKP'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 28000, 'harga_beli_terakhir' => 9000, 'qty_minimum' => 10],
            ['kode_item' => 'NKP-005', 'nama_item' => 'Air Mineral',    'item_category_id' => $catId('NKP'), 'tipe' => 'produk_jual', 'satuan' => 'botol', 'harga_jual' => 5000,  'harga_beli_terakhir' => 3000, 'qty_minimum' => 30],

            // ===== Snack (produk jual) =====
            ['kode_item' => 'SNK-001', 'nama_item' => 'Croissant Butter',  'item_category_id' => $catId('SNK'), 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 15000, 'harga_beli_terakhir' => 6000, 'qty_minimum' => 10],
            ['kode_item' => 'SNK-002', 'nama_item' => 'Croissant Cokelat', 'item_category_id' => $catId('SNK'), 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 18000, 'harga_beli_terakhir' => 7000, 'qty_minimum' => 10],
            ['kode_item' => 'SNK-003', 'nama_item' => 'Cookies Cokelat',   'item_category_id' => $catId('SNK'), 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 8000,  'harga_beli_terakhir' => 3000, 'qty_minimum' => 20],
            ['kode_item' => 'SNK-004', 'nama_item' => 'Roti Bakar',        'item_category_id' => $catId('SNK'), 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 12000, 'harga_beli_terakhir' => 4000, 'qty_minimum' => 15],

            // ===== Makanan (produk jual) =====
            ['kode_item' => 'MKN-001', 'nama_item' => 'Nasi Goreng Kampung', 'item_category_id' => $catId('MKN'), 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 25000, 'harga_beli_terakhir' => 10000, 'qty_minimum' => 10],
            ['kode_item' => 'MKN-002', 'nama_item' => 'Ayam Geprek',        'item_category_id' => $catId('MKN'), 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 28000, 'harga_beli_terakhir' => 12000, 'qty_minimum' => 10],
            ['kode_item' => 'MKN-003', 'nama_item' => 'Mie Goreng',         'item_category_id' => $catId('MKN'), 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 22000, 'harga_beli_terakhir' => 8000,  'qty_minimum' => 10],
            ['kode_item' => 'MKN-004', 'nama_item' => 'Sandwich Ayam',      'item_category_id' => $catId('MKN'), 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 25000, 'harga_beli_terakhir' => 10000, 'qty_minimum' => 8],

            // ===== Roasted Bean (produk jual, retail) =====
            ['kode_item' => 'RTB-001', 'nama_item' => 'Roasted Bean Sidikalang 250g', 'item_category_id' => $catId('RTB'), 'tipe' => 'produk_jual', 'satuan' => 'pack', 'harga_jual' => 75000,  'harga_beli_terakhir' => 45000,  'qty_minimum' => 5],
            ['kode_item' => 'RTB-002', 'nama_item' => 'Roasted Bean Sidikalang 500g', 'item_category_id' => $catId('RTB'), 'tipe' => 'produk_jual', 'satuan' => 'pack', 'harga_jual' => 140000, 'harga_beli_terakhir' => 85000,  'qty_minimum' => 5],
            ['kode_item' => 'RTB-003', 'nama_item' => 'Roasted Bean Sidikalang 1kg',  'item_category_id' => $catId('RTB'), 'tipe' => 'produk_jual', 'satuan' => 'pack', 'harga_jual' => 270000, 'harga_beli_terakhir' => 160000, 'qty_minimum' => 3],

            // ===== Green Bean (bahan baku roastery) =====
            ['kode_item' => 'GRB-001', 'nama_item' => 'Green Bean Arabika Sidikalang', 'item_category_id' => $catId('GRB'), 'tipe' => 'bahan_baku', 'satuan' => 'kg', 'harga_beli_terakhir' => 90000, 'qty_minimum' => 20],
            ['kode_item' => 'GRB-002', 'nama_item' => 'Green Bean Robusta',            'item_category_id' => $catId('GRB'), 'tipe' => 'bahan_baku', 'satuan' => 'kg', 'harga_beli_terakhir' => 60000, 'qty_minimum' => 15],

            // ===== Bahan Baku =====
            ['kode_item' => 'BHN-001', 'nama_item' => 'Susu UHT Full Cream', 'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'liter', 'harga_beli_terakhir' => 18000,  'qty_minimum' => 20],
            ['kode_item' => 'BHN-002', 'nama_item' => 'Susu UHT Skim',       'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'liter', 'harga_beli_terakhir' => 20000,  'qty_minimum' => 10],
            ['kode_item' => 'BHN-003', 'nama_item' => 'Susu Oat',            'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'liter', 'harga_beli_terakhir' => 40000,  'qty_minimum' => 5],
            ['kode_item' => 'BHN-004', 'nama_item' => 'Gula Pasir',          'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'kg',    'harga_beli_terakhir' => 15000,  'qty_minimum' => 10],
            ['kode_item' => 'BHN-005', 'nama_item' => 'Sirup Vanilla',       'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'botol', 'harga_beli_terakhir' => 45000,  'qty_minimum' => 3],
            ['kode_item' => 'BHN-006', 'nama_item' => 'Sirup Caramel',       'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'botol', 'harga_beli_terakhir' => 45000,  'qty_minimum' => 3],
            ['kode_item' => 'BHN-007', 'nama_item' => 'Cokelat Bubuk',       'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'kg',    'harga_beli_terakhir' => 80000,  'qty_minimum' => 3],
            ['kode_item' => 'BHN-008', 'nama_item' => 'Matcha Bubuk',        'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'kg',    'harga_beli_terakhir' => 250000, 'qty_minimum' => 2],
            ['kode_item' => 'BHN-009', 'nama_item' => 'Es Batu',             'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'kg',    'harga_beli_terakhir' => 3000,   'qty_minimum' => 10],

            // ===== Kemasan =====
            ['kode_item' => 'KMS-001', 'nama_item' => 'Cup 12 oz',    'item_category_id' => $catId('KMS'), 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 1500, 'qty_minimum' => 200],
            ['kode_item' => 'KMS-002', 'nama_item' => 'Cup 16 oz',    'item_category_id' => $catId('KMS'), 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 1800, 'qty_minimum' => 200],
            ['kode_item' => 'KMS-003', 'nama_item' => 'Tutup Cup',    'item_category_id' => $catId('KMS'), 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 500,  'qty_minimum' => 500],
            ['kode_item' => 'KMS-004', 'nama_item' => 'Sedotan',      'item_category_id' => $catId('KMS'), 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 300,  'qty_minimum' => 500],
            ['kode_item' => 'KMS-005', 'nama_item' => 'Tas Takeaway', 'item_category_id' => $catId('KMS'), 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 1000, 'qty_minimum' => 100],
        ];

        foreach ($items as $item) {
            Item::updateOrCreate(['kode_item' => $item['kode_item']], array_merge($item, ['is_active' => true]));
        }
    }
}
