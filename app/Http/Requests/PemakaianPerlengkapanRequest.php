<?php

namespace App\Http\Requests;

use App\Models\Cabang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PemakaianPerlengkapanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pemakaian_perlengkapan.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'item_id'           => ['required', 'exists:items,id'],
            // Bug 19 — hanya cabang yang tampil di dropdown (cabang milik user, atau semua untuk akses pusat)
            'cabang_id'         => ['required', Rule::in($this->cabangDiizinkan())],
            'qty'               => ['required', 'numeric', 'min:0.001'],
            'tanggal_pemakaian' => ['required', 'date'],
            'keterangan'        => ['nullable', 'string', 'max:500'],
        ];
    }

    private function cabangDiizinkan(): array
    {
        $user = $this->user();
        if (! $user) {
            return [];
        }

        return ($user->canAccessAllBranches()
            ? Cabang::aktif()->pluck('id')
            : $user->cabangs()->aktif()->pluck('cabangs.id'))
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function messages(): array
    {
        return [
            'cabang_id.in' => 'Cabang tidak tersedia untuk akun Anda.',
        ];
    }

    public function attributes(): array
    {
        return [
            'item_id'           => 'Item',
            'cabang_id'         => 'Cabang',
            'qty'               => 'Qty',
            'tanggal_pemakaian' => 'Tanggal Pemakaian',
        ];
    }
}
