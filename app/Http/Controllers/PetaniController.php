<?php

namespace App\Http\Controllers;

use App\Http\Requests\PetaniRequest;
use App\Models\Petani;
use Illuminate\Http\Request;

class PetaniController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('petani.view'), 403);

        $query = Petani::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode_petani', 'like', "%{$search}%")
                  ->orWhere('nama_kebun', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'aktif');
        }

        $petanis = $query->orderBy('nama')->paginate(15)->withQueryString();

        return view('petani.index', compact('petanis'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('petani.create'), 403);

        $last = Petani::orderByDesc('id')->first();
        $seq  = $last ? ((int) substr($last->kode_petani, 3)) + 1 : 1;
        $kodeHint = 'PTN-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

        return view('petani.create', compact('kodeHint'));
    }

    public function store(PetaniRequest $request)
    {
        abort_unless(auth()->user()->can('petani.create'), 403);

        $data = $request->validated();

        if (empty($data['kode_petani'])) {
            $last = Petani::orderByDesc('id')->first();
            $seq  = $last ? ((int) substr($last->kode_petani, 3)) + 1 : 1;
            $data['kode_petani'] = 'PTN-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        }

        $data['is_active'] = true;
        Petani::create($data);

        return redirect()->route('petani.index')->with('success', 'Petani berhasil ditambahkan.');
    }

    public function show(Petani $petani)
    {
        abort_unless(auth()->user()->can('petani.view'), 403);

        return view('petani.show', compact('petani'));
    }

    public function edit(Petani $petani)
    {
        abort_unless(auth()->user()->can('petani.edit'), 403);

        return view('petani.edit', compact('petani'));
    }

    public function update(PetaniRequest $request, Petani $petani)
    {
        abort_unless(auth()->user()->can('petani.edit'), 403);

        $petani->update($request->validated());

        return redirect()->route('petani.index')->with('success', 'Data petani berhasil diperbarui.');
    }

    public function destroy(Petani $petani)
    {
        abort_unless(auth()->user()->can('petani.delete'), 403);

        $nama = $petani->nama;
        $petani->delete();

        return redirect()->route('petani.index')->with('success', "Petani <strong>{$nama}</strong> berhasil dihapus.");
    }

    public function toggleAktif(Petani $petani)
    {
        abort_unless(auth()->user()->can('petani.edit'), 403);

        $petani->update(['is_active' => ! $petani->is_active]);
        $status = $petani->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Petani berhasil {$status}.");
    }
}
