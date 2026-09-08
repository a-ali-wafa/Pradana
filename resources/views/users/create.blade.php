@extends('layouts.app')

@section('title', 'Tambah User - PRADANA')
@section('page-title', 'Manajemen User')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Manajemen User</a></li>
                <li class="breadcrumb-item active">Tambah User</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-plus me-2 text-primary"></i>Tambah Akun User Baru
            </div>
            <div class="card-body">
                {{-- A3 [LOCKED]: akun dibuat langsung oleh admin, bukan self-register --}}
                <div class="alert alert-info border-0 rounded-3 small mb-4">
                    <i class="fas fa-info-circle me-1"></i>
                    Akun baru dibuat langsung oleh admin. User dapat login menggunakan <strong>email</strong>
                    dan <strong>PIN 6 digit</strong> yang Anda tentukan di sini.
                </div>

                <form method="POST" action="{{ route('users.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap"
                               class="form-control @error('nama_lengkap') is-invalid @enderror"
                               value="{{ old('nama_lengkap') }}"
                               placeholder="Nama lengkap user" required>
                        @error('nama_lengkap') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}"
                               placeholder="email@contoh.com" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="">-- Pilih Role --</option>
                            <option value="admin"     {{ old('role') === 'admin'     ? 'selected' : '' }}>Admin</option>
                            <option value="kepala"    {{ old('role') === 'kepala'    ? 'selected' : '' }}>Kepala</option>
                            <option value="perangkat" {{ old('role') === 'perangkat' ? 'selected' : '' }}>Perangkat</option>
                        </select>
                        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">
                            <strong>Admin</strong>: akses penuh termasuk hapus &amp; konfigurasi.<br>
                            <strong>Kepala/Perangkat</strong>: akses baca + input surat (non-admin).
                        </div>
                    </div>

                    <hr class="text-secondary opacity-25">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">PIN <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="pin" id="pin"
                                   class="form-control font-monospace @error('pin') is-invalid @enderror"
                                   placeholder="6 digit angka" maxlength="6"
                                   pattern="\d{6}" inputmode="numeric"
                                   autocomplete="new-password" required>
                            <button class="btn btn-outline-secondary" type="button" id="btnTogglePin"
                                    onclick="pradanaTogglePin('pin', 'btnTogglePin')">
                                <i class="fas fa-eye"></i>
                            </button>
                            @error('pin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Konfirmasi PIN <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="pin_confirmation" id="pin_confirmation"
                                   class="form-control font-monospace"
                                   placeholder="Ulangi PIN yang sama" maxlength="6"
                                   pattern="\d{6}" inputmode="numeric"
                                   autocomplete="new-password" required>
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="pradanaTogglePin('pin_confirmation', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end border-top pt-3">
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-times me-1"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-user-plus me-1"></i> Buat Akun
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
function pradanaTogglePin(inputId, btnEl) {
    const input = document.getElementById(inputId);
    const icon  = (typeof btnEl === 'string')
        ? document.getElementById(btnEl).querySelector('i')
        : btnEl.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
@endpush
