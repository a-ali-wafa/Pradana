@extends('layouts.app')

@section('title', 'Edit Klasifikasi Primer - PRADANA')
@section('page-title', 'Klasifikasi Primer')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ route('klasifikasi-primer.index') }}">Klasifikasi Primer</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
        <div class="card">
            <div class="card-header"><i class="fas fa-pen me-2 text-primary"></i>Edit Klasifikasi Primer</div>
            <div class="card-body">
                <form method="POST" action="{{ route('klasifikasi-primer.update', $klasifikasiPrimer) }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kode <span class="text-danger">*</span></label>
                        <input type="text" name="kode" class="form-control font-monospace @error('kode') is-invalid @enderror"
                               value="{{ old('kode', $klasifikasiPrimer->kode) }}" required>
                        @error('kode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control @error('nama') is-invalid @enderror"
                               value="{{ old('nama', $klasifikasiPrimer->nama) }}" required>
                        @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('klasifikasi-primer.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Batal</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
