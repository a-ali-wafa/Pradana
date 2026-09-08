@extends('layouts.app')

@section('title', 'Edit Klasifikasi Tersier - PRADANA')
@section('page-title', 'Klasifikasi Tersier')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ route('klasifikasi-tersier.index') }}">Klasifikasi Tersier</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
        <div class="card">
            <div class="card-header"><i class="fas fa-pen me-2 text-primary"></i>Edit Klasifikasi Tersier</div>
            <div class="card-body">
                <form method="POST" action="{{ route('klasifikasi-tersier.update', $klasifikasiTersier) }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Klasifikasi Sekunder <span class="text-danger">*</span></label>
                        <select name="klasifikasi_sekunder_id"
                                class="form-select @error('klasifikasi_sekunder_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Sekunder --</option>
                            @foreach($klasifikasiSekunder as $sekunder)
                                <option value="{{ $sekunder->id }}"
                                    {{ old('klasifikasi_sekunder_id', $klasifikasiTersier->klasifikasi_sekunder_id) == $sekunder->id ? 'selected' : '' }}>
                                    {{ $sekunder->kode }} – {{ $sekunder->nama }}
                                    @if($sekunder->primer) ({{ $sekunder->primer->kode }}) @endif
                                </option>
                            @endforeach
                        </select>
                        @error('klasifikasi_sekunder_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kode <span class="text-danger">*</span></label>
                        <input type="text" name="kode" class="form-control font-monospace @error('kode') is-invalid @enderror"
                               value="{{ old('kode', $klasifikasiTersier->kode) }}" required>
                        @error('kode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control @error('nama') is-invalid @enderror"
                               value="{{ old('nama', $klasifikasiTersier->nama) }}" required>
                        @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('klasifikasi-tersier.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Batal</a>
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
