<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PetaniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('petani')?->id;

        return [
            'kode_petani'   => ['required', 'string', 'max:20', Rule::unique('petani', 'kode_petani')->ignore($id)],
            'nama'          => ['required', 'string', 'max:150'],
            'telepon'       => ['nullable', 'string', 'max:20'],
            'alamat'        => ['nullable', 'string', 'max:500'],
            'nama_kebun'    => ['nullable', 'string', 'max:150'],
            'koordinat_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'koordinat_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'catatan'       => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'kode_petani' => 'Kode Petani',
            'nama'        => 'Nama Petani',
            'nama_kebun'  => 'Nama Kebun',
        ];
    }
}
