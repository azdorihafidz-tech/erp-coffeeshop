<?php

namespace App\Traits;

use App\Services\ClosingLockService;

/**
 * Sprint Closing Periode (2026-10-17) — tolak create/update/delete/restore
 * model yang tanggalnya masuk periode closing Final.
 *
 * Model pemakai wajib mendefinisikan:
 *   - closingLockTarget(bool $original): array{0:?int,1:mixed}  [cabang_id, tanggal]
 *   - (opsional) closingLockKolomBebas(): array  kolom yang BOLEH berubah
 *     walau periode terkunci (mis. status produksi/antrian, status setoran)
 *   - (opsional) closingLockKonteks(): string  label di pesan error
 *
 * Trait ini SENGAJA ditaruh paling depan di `use` model supaya listener
 * 'deleting'-nya jalan sebelum FillsDeletedBy menulis kolom deleted_by.
 */
trait TerkunciClosing
{
    public static function bootTerkunciClosing(): void
    {
        static::creating(fn ($model) => $model->cekKunciClosing(false));

        static::updating(function ($model) {
            $berubah = array_diff(
                array_keys($model->getDirty()),
                array_merge(['updated_at'], $model->closingLockKolomBebas())
            );
            if (empty($berubah)) {
                return;
            }
            $model->cekKunciClosing(true);
            $model->cekKunciClosing(false);
        });

        static::deleting(fn ($model) => $model->cekKunciClosing(true));

        if (method_exists(static::class, 'restoring')) {
            static::restoring(fn ($model) => $model->cekKunciClosing(false));
        }
    }

    public function closingLockKolomBebas(): array
    {
        return [];
    }

    public function closingLockKonteks(): string
    {
        return 'data';
    }

    protected function cekKunciClosing(bool $original): void
    {
        $lock = app(ClosingLockService::class);
        if (!$lock->adaKunci()) {
            return;
        }

        [$cabangId, $tanggal] = $this->closingLockTarget($original);
        $lock->assertBolehUbah($cabangId !== null ? (int) $cabangId : null, $tanggal, $this->closingLockKonteks());
    }
}
