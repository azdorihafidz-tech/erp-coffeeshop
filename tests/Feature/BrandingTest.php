<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Panduan;
use App\Models\PengaturanUmum;
use App\Models\Tooltip;
use App\Support\Branding;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint Identitas Usaha (2026-11-03) — nama & logo usaha dari Pengaturan
 * Umum, bukan ditulis di kode, supaya program bisa dipakai usaha lain.
 */
class BrandingTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    /** File logo asli di public/uploads/branding — disimpan dulu, dikembalikan di tearDown. */
    private array $cadangan = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (glob(public_path(Branding::FOLDER . '/*.png')) ?: [] as $f) {
            $this->cadangan[$f] = file_get_contents($f);
        }

        PengaturanUmum::query()->delete();
        PengaturanUmum::create([
            'nama_perusahaan' => 'Toko Contoh Makmur',
            'nama_singkat'    => 'Contoh',
            'tagline'         => 'Bakery & Cafe',
            'telepon'         => '0811-000-111',
        ]);
        app(Branding::class)->lupakan();
    }

    protected function tearDown(): void
    {
        foreach (glob(public_path(Branding::FOLDER . '/*.png')) ?: [] as $f) {
            if (!isset($this->cadangan[$f])) {
                @unlink($f);
            }
        }
        foreach ($this->cadangan as $f => $isi) {
            file_put_contents($f, $isi);
        }
        app(Branding::class)->lupakan();

        parent::tearDown();
    }

    public function test_login_dan_sidebar_memakai_nama_dari_pengaturan_umum(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('<title>Toko Contoh Makmur</title>', false)
            ->assertSee('Masuk ke ERP Contoh')
            ->assertDontSee('Kopi Drip');

        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('pengaturan.umum'))->assertOk()
            ->assertSee('<div class="brand-name">Contoh</div>', false)
            ->assertSee('<div class="brand-sub">Bakery &amp; Cafe</div>', false)
            ->assertSee('<title>Contoh - Pengaturan Umum</title>', false);
    }

    public function test_tagline_kosong_tidak_menampilkan_baris_tagline(): void
    {
        PengaturanUmum::query()->update(['tagline' => null, 'nama_singkat' => null]);
        app(Branding::class)->lupakan();

        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('pengaturan.umum'))->assertOk()
            ->assertSee('<div class="brand-name">Toko Contoh Makmur</div>', false)
            ->assertDontSee('class="brand-sub"', false);
    }

    public function test_simpan_identitas_langsung_berlaku_tanpa_cache_lama(): void
    {
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('login'));

        $this->actingAs($owner)->put(route('pengaturan.umum.update'), [
            'nama_perusahaan' => 'Warung Baru Jaya',
            'nama_singkat'    => 'WBJ',
            'tagline'         => 'Enak Murah',
            'telepon'         => '0812',
        ])->assertRedirect(route('pengaturan.umum'));

        $this->assertDatabaseHas('pengaturan_umums', ['nama_perusahaan' => 'Warung Baru Jaya', 'nama_singkat' => 'WBJ', 'telepon' => '0812']);
        $this->getJson(route('app.info'))->assertOk()
            ->assertJson(['erp' => true, 'nama' => 'Warung Baru Jaya', 'nama_singkat' => 'WBJ', 'tagline' => 'Enak Murah']);
    }

    public function test_upload_logo_membuat_png_dan_ikon_lalu_dipakai_di_halaman(): void
    {
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->put(route('pengaturan.umum.update'), [
            'nama_perusahaan' => 'Toko Contoh Makmur',
            'nama_singkat'    => 'Contoh',
            'logo'            => UploadedFile::fake()->image('logo.jpg', 1600, 800),
        ])->assertRedirect(route('pengaturan.umum'))->assertSessionHasNoErrors();

        $path = PengaturanUmum::first()->logo_path;
        $this->assertStringStartsWith(Branding::FOLDER . '/logo-', $path);
        $this->assertStringEndsWith('.png', $path);

        // Logo diperkecil maks 1024 px, ikon persegi tiap ukuran
        [$w, $h] = getimagesize(public_path($path));
        $this->assertSame([1024, 512], [$w, $h]);
        foreach (Branding::UKURAN_IKON as $u) {
            $this->assertSame([$u, $u], array_slice(getimagesize(public_path(Branding::FOLDER . "/icon-{$u}.png")), 0, 2));
        }

        $this->actingAs($owner)->get(route('pengaturan.umum'))->assertSee(asset($path), false)
            ->assertSee(asset(Branding::FOLDER . '/icon-96.png'), false);
        $this->getJson('/manifest.json')->assertOk()
            ->assertJsonPath('name', 'Toko Contoh Makmur')
            ->assertJsonPath('short_name', 'Contoh')
            ->assertJsonPath('icons.5.src', '/' . Branding::FOLDER . '/icon-192.png');

        // Hapus logo → kembali ke logo bawaan, file upload ikut dihapus
        $this->actingAs($owner)->put(route('pengaturan.umum.update'), [
            'nama_perusahaan' => 'Toko Contoh Makmur',
            'hapus_logo'      => '1',
        ])->assertRedirect();
        $this->assertNull(PengaturanUmum::first()->logo_path);
        $this->assertFileDoesNotExist(public_path($path));
        $this->assertFileDoesNotExist(public_path(Branding::FOLDER . '/icon-192.png'));
        $this->actingAs($owner)->get(route('pengaturan.umum'))->assertSee(asset('images/logo.png'), false);
    }

    public function test_logo_svg_ditolak(): void
    {
        $owner = $this->buatUser('owner');
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($owner)->put(route('pengaturan.umum.update'), [
            'nama_perusahaan' => 'Toko Contoh Makmur',
            'logo'            => $svg,
        ])->assertSessionHasErrors('logo');
        $this->assertNull(PengaturanUmum::first()->logo_path);
    }

    public function test_hanya_yang_berhak_bisa_mengubah(): void
    {
        $kasir = $this->buatUser('kasir', Cabang::where('tipe', 'cabang')->value('id'));

        $this->actingAs($kasir)->put(route('pengaturan.umum.update'), ['nama_perusahaan' => 'Dibajak'])->assertForbidden();
        $this->assertDatabaseMissing('pengaturan_umums', ['nama_perusahaan' => 'Dibajak']);
    }

    public function test_panduan_dan_tooltip_mengganti_penanda_nama_usaha(): void
    {
        $p = Panduan::create(['slug' => 'tes-branding-' . uniqid(), 'judul' => 'Tes', 'konten' => 'Selamat datang di {nama_usaha} ({nama_singkat}).', 'modul' => 'lainnya', 'aktif' => true]);
        $this->assertStringContainsString('Selamat datang di Toko Contoh Makmur (Contoh).', $p->konten_html);

        // Konten seeder sudah memakai penanda, bukan nama Kopi Drip
        $this->assertSame(0, Panduan::where('konten', 'like', '%Kopi Drip%')->count());
        $this->assertSame(0, Tooltip::where('content', 'like', '%Kopi Drip%')->count());

        Tooltip::updateOrCreate(['key' => 'pengaturan_umum.nama_singkat'], ['title' => 'Nama Singkat', 'content' => 'Contoh: {nama_singkat}', 'modul' => 'pengaturan', 'aktif' => true]);
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('pengaturan.umum'))->assertOk()->assertSee('Contoh: Contoh', false);
    }

    public function test_struk_memakai_nama_singkat_dan_footer_bawaan_dari_identitas(): void
    {
        $cabang = Cabang::create([
            'nama_cabang' => 'Outlet Brand ' . uniqid(), 'kode_cabang' => 'BRD' . strtoupper(substr(uniqid(), -5)),
            'tipe' => 'cabang', 'is_active' => true, 'dine_in_aktif' => true, 'takeaway_aktif' => true, 'footer_struk' => null,
        ]);
        $item = Item::where('tipe', 'produk_jual')->firstOrFail();
        $order = Order::create([
            'cabang_id' => $cabang->id, 'nomor_order' => 'ORD-BRD-' . uniqid(), 'tanggal_order' => today(),
            'tipe_order' => 'produk_jadi', 'tipe_transaksi' => 'takeaway', 'total_harga' => 20000, 'total_bayar' => 20000,
            'jumlah_bayar' => 20000, 'tipe_pembayaran' => 'tunai', 'status' => 'selesai',
        ]);
        OrderItem::create(['order_id' => $order->id, 'item_id' => $item->id, 'nama_item' => $item->nama_item, 'qty' => 1,
            'satuan' => 'pcs', 'harga_satuan' => 20000, 'total_harga' => 20000, 'hpp' => 0]);

        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->actingAs($kasir)->get(route('penjualan.struk-modal', $order))->assertOk()
            ->assertSee('<div class="center bold header-store">Contoh</div>', false)
            ->assertSee('Toko Contoh Makmur - Bakery &amp; Cafe', false)
            ->assertSee('"toko":"Contoh"', false)
            ->assertDontSee('Kopi Drip');
    }

    public function test_app_info_publik_tanpa_login(): void
    {
        $this->getJson('/app-info')->assertOk()
            ->assertExactJson([
                'erp' => true, 'nama' => 'Toko Contoh Makmur', 'nama_singkat' => 'Contoh', 'tagline' => 'Bakery & Cafe',
                'logo_url' => asset('images/logo.png'), 'ikon_url' => asset('images/icons/icon-192x192.png'),
            ]);
    }
}
