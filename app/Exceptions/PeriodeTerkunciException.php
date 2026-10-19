<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Sprint Closing Periode (2026-10-17) — dilempar saat ada percobaan
 * tambah/ubah/hapus data yang tanggalnya masuk periode closing Final.
 * Sengaja extends RuntimeException supaya blok catch(\Exception) yang
 * sudah ada di controller (POS, keuangan, dll) otomatis menampilkan
 * pesannya ke user tanpa perlu diubah.
 */
class PeriodeTerkunciException extends RuntimeException
{
}
