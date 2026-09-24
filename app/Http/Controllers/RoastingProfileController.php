<?php

namespace App\Http\Controllers;

use App\Models\RoastingProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoastingProfileController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('roastery.profile.view'), 403);

        return view('roastery.profile.index', [
            'profiles' => RoastingProfile::withCount('batches')->orderBy('avg_susut_percent')->get(),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->can('roastery.profile.create'), 403);

        return view('roastery.profile.create');
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('roastery.profile.create'), 403);

        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['nama']);
        $data['is_active'] = $request->boolean('is_active', true);
        RoastingProfile::create($data);

        return redirect()->route('roastery.profile.index')->with('success', "Profile {$data['nama']} ditambahkan.");
    }

    public function edit(RoastingProfile $profile)
    {
        abort_unless(auth()->user()->can('roastery.profile.edit'), 403);

        return view('roastery.profile.edit', compact('profile'));
    }

    public function update(Request $request, RoastingProfile $profile)
    {
        abort_unless(auth()->user()->can('roastery.profile.edit'), 403);

        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');
        $profile->update($data);

        return redirect()->route('roastery.profile.index')->with('success', "Profile {$profile->nama} diperbarui.");
    }

    public function destroy(RoastingProfile $profile)
    {
        abort_unless(auth()->user()->can('roastery.profile.delete'), 403);

        if ($profile->batches()->exists()) {
            return back()->with('error', 'Profile sudah dipakai batch — nonaktifkan saja, jangan dihapus.');
        }
        $profile->delete();

        return redirect()->route('roastery.profile.index')->with('success', 'Profile dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama'                => ['required', 'string', 'max:50'],
            'level_target'        => ['required', Rule::in(['light', 'medium', 'dark', 'custom'])],
            'avg_susut_percent'   => ['required', 'numeric', 'min:0', 'max:60'],
            'suhu_target_celsius' => ['nullable', 'integer', 'min:100', 'max:300'],
            'waktu_target_menit'  => ['nullable', 'integer', 'min:1', 'max:60'],
            'catatan'             => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function uniqueSlug(string $nama): string
    {
        $base = Str::slug($nama) ?: 'profile';
        $slug = $base;
        $i = 2;
        while (RoastingProfile::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
