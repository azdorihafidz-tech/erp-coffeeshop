<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class ItemCategorySeeder extends Seeder
{
    /**
     * Kategori item Kopi Drip Sidikalang (Coffee Shop + Roastery + Eatery) - Fase B (2026-09-22)
     */
    public function run(): void
    {
        $categories = [
            ['kode_kategori' => 'KPI', 'nama_kategori' => 'Kopi',         'deskripsi' => 'Espresso, Latte, Cappuccino, Americano, dan minuman kopi lainnya'],
            ['kode_kategori' => 'MBW', 'nama_kategori' => 'Manual Brew',  'deskripsi' => 'V60, Aeropress, French Press, Tubruk'],
            ['kode_kategori' => 'NKP', 'nama_kategori' => 'Non-Kopi',     'deskripsi' => 'Teh, Cokelat, Matcha, Smoothie, Milkshake'],
            ['kode_kategori' => 'SNK', 'nama_kategori' => 'Snack',        'deskripsi' => 'Croissant, Cookies, Pastry, Roti Bakar (light meal)'],
            ['kode_kategori' => 'MKN', 'nama_kategori' => 'Makanan',      'deskripsi' => 'Nasi Goreng, Ayam Geprek, Sandwich (menu berat)'],
            ['kode_kategori' => 'RTB', 'nama_kategori' => 'Roasted Bean', 'deskripsi' => 'Biji kopi siap jual retail (250g, 500g, 1kg)'],
            ['kode_kategori' => 'GRB', 'nama_kategori' => 'Green Bean',   'deskripsi' => 'Biji kopi mentah (bahan baku roastery)'],
            ['kode_kategori' => 'BHN', 'nama_kategori' => 'Bahan Baku',   'deskripsi' => 'Susu, gula, sirup, es batu, topping, dan bahan lainnya'],
            ['kode_kategori' => 'KMS', 'nama_kategori' => 'Kemasan',      'deskripsi' => 'Cup, tutup, sedotan, tas takeaway'],
        ];

        foreach ($categories as $cat) {
            ItemCategory::updateOrCreate(['kode_kategori' => $cat['kode_kategori']], $cat);
        }
    }
}
