@extends('layouts.app')

@section('title', 'Edit Roasting Profile')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-fire me-2 text-success"></i>Edit Roasting Profile</h5>
    <x-panduan-button slug="roastery-profile" />
</div>
<div class="card"><div class="card-body">
    <form method="POST" action="{{ route('roastery.profile.update', $profile) }}">
        @csrf
        @method('PUT')
        @include('roastery.profile._form')
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm">Simpan</button>
            <a href="{{ route('roastery.profile.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
        </div>
    </form>
</div></div>
@endsection
