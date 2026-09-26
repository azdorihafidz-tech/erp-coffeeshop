@extends('layouts.app')

@section('title', 'Tambah Petani')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-workspace me-2 text-primary"></i>Tambah Petani</h5>
    <x-panduan-button slug="petani" />
</div>
<div class="card"><div class="card-body">
    <form method="POST" action="{{ route('petani.store') }}">
        @csrf
        @include('petani._form')
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm">Simpan</button>
            <a href="{{ route('petani.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
        </div>
    </form>
</div></div>
@endsection
