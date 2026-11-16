<?php

namespace App\Http\Controllers;

use App\Enums\RoleUser;
use App\Services\UpdateDatabaseService;

/**
 * Update Database Production (2026-10-19) — halaman Owner-only untuk
 * migrate + seeder konten tanpa hapus data. Logika di UpdateDatabaseService
 * (dipakai juga oleh script public/deploy-*.php). Beda total dengan
 * /go-live-reset (yang mengosongkan database).
 */
class UpdateDatabaseController extends Controller
{
    public function __construct(private UpdateDatabaseService $service)
    {
    }

    private function assertOwner(): void
    {
        abort_unless(auth()->user()?->aksesPenuh(), 403, 'Hanya Owner yang boleh menjalankan update database.');
    }

    public function form()
    {
        $this->assertOwner();

        return view('admin.update-database', $this->dataView());
    }

    public function execute()
    {
        $this->assertOwner();
        set_time_limit(300);
        $logs = $this->service->jalankan();

        return view('admin.update-database', $this->dataView() + ['logs' => $logs]);
    }

    private function dataView(): array
    {
        return [
            'pending'        => $this->service->migrationBelumJalan(),
            'seeders'        => UpdateDatabaseService::SEEDERS,
            'permissionBaru' => UpdateDatabaseService::PERMISSION_BARU,
            'permissionAda'  => \App\Models\Permission::pluck('id', 'name'),
        ];
    }
}
