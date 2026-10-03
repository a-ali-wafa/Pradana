@extends('layouts.app')

@section('title', 'Ganti PIN - PRADANA')
@section('page-title', 'Ganti PIN')

@section('content')

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-key me-2 text-primary"></i>Ganti PIN Saya
            </div>
            <div class="card-body">
                <p class="text-secondary small">
                    Login memakai <strong>{{ auth()->user()->email }}</strong>.
                    PIN baru harus <strong>8 digit angka</strong> dan akan berlaku segera —
                   catat dan simpan baik-baik, PIN tidak bisa dilihat ulang setelah disimpan.
                </p>

                <form method="POST" action="{{ route('profil.pin.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="mb-3">
                        <label for="pin_lama" class="form-label small fw-bold">PIN Lama <span class="text-danger">*</span></label>
                        <input type="password" class="form-control font-monospace @error('pin_lama') is-invalid @enderror"
                               id="pin_lama" name="pin_lama" maxlength="8" pattern="\d{8}" inputmode="numeric"
                               autocomplete="current-password" required>
                        @error('pin_lama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="pin" class="form-label small fw-bold">PIN Baru <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control font-monospace @error('pin') is-invalid @enderror"
                                   id="pin" name="pin" maxlength="8" pattern="\d{8}" inputmode="numeric"
                                   autocomplete="new-password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="gantiPinTampil('pin', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        @error('pin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="pin_confirmation" class="form-label small fw-bold">Ulangi PIN Baru <span class="text-danger">*</span></label>
                        <input type="password" class="form-control font-monospace" id="pin_confirmation"
                               name="pin_confirmation" maxlength="8" pattern="\d{8}" inputmode="numeric"
                               autocomplete="new-password" required>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-save me-1"></i> Simpan PIN Baru
                        </button>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary rounded-pill px-4">Batal</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="alert alert-light border small">
            <i class="fas fa-info-circle me-1"></i>
            Lupa PIN? Halaman ini tetap butuh PIN lama. Kalau benar-benar lupa, minta
            admin mengresetnya di menu <strong>Manajemen User</strong>.
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function gantiPinTampil(id, tombol) {
    const input = document.getElementById(id);
    const menunjukkan = input.type === 'password';
    input.type = menunjukkan ? 'text' : 'password';
    tombol.querySelector('i').className = 'fas fa-eye' + (menunjukkan ? '-slash' : '');
}
</script>
@endpush
