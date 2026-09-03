@extends('layouts.app')

@section('title', 'Pengaturan Instansi - PRADANA')
@section('page-title', 'Pengaturan Instansi')

{{--
    View Pengaturan Instansi — dibuat 31 Agu 2026, DIROMBAK ULANG (sesi sama) setelah
    user kasih preview HTML sistem lama. Lihat AGENTS.md 12.19.

    Pola interaksi "field readonly, klik Edit baru bisa isi" ini SENGAJA meniru
    `handleEditHeader()`/`cancelEditHeader()` di referensi lama — di sana, field kop
    surat (nama/jenis/alamat instansi, dst) memang readonly sampai user pencet tombol
    "Edit Header", supaya tidak kepencet ubah tidak sengaja. Bedanya: referensi lama
    nyimpannya lewat AJAX ke Apps Script tanpa reload; di sini pakai <form> POST+PUT
    standar Laravel (submit = reload halaman) — lebih sederhana & tidak butuh JS
    tambahan untuk validasi/error handling (`@error` Blade sudah otomatis dari
    `UpdatePengaturanInstansiRequest`, lihat AGENTS.md 12.18).

    Field & controller-nya SUDAH cross-checked (lihat 12.18) — TIDAK ada asumsi baru
    di sini soal data, cuma soal presentasi/UX.

    ⚠️ `Storage::url()` di bawah butuh symlink `storage:link` sudah dijalankan di
    server (`public/storage` → `storage/app/public`) supaya logo bisa tampil — ini
    setup standar Laravel, belum tentu sudah dijalankan di server user, WAJIB dicek.
--}}

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-building me-2"></i>Kop Surat &amp; Data Instansi</span>
                <button type="button" id="btnToggleEdit" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="pradanaToggleEditPengaturanInstansi()">
                    <i class="fas fa-pen me-1"></i> Edit
                </button>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('pengaturan-instansi.update') }}" enctype="multipart/form-data" id="formPengaturanInstansi">
                    @csrf
                    @method('PUT')

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Nama Instansi</label>
                            <input type="text" name="nama_instansi" class="form-control bg-light border-0 pradana-field"
                                   value="{{ old('nama_instansi', $pengaturanInstansi->nama_instansi) }}" readonly required>
                            @error('nama_instansi') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Jenis Instansi</label>
                            <input type="text" name="jenis_instansi" class="form-control bg-light border-0 pradana-field"
                                   value="{{ old('jenis_instansi', $pengaturanInstansi->jenis_instansi) }}" placeholder="Cth: Pemerintah Desa" readonly required>
                            @error('jenis_instansi') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Alamat Instansi</label>
                        <textarea name="alamat_instansi" class="form-control bg-light border-0 pradana-field" rows="2" readonly required>{{ old('alamat_instansi', $pengaturanInstansi->alamat_instansi) }}</textarea>
                        @error('alamat_instansi') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">No. Telepon</label>
                            <input type="text" name="no_telp" class="form-control bg-light border-0 pradana-field"
                                   value="{{ old('no_telp', $pengaturanInstansi->no_telp) }}" readonly required>
                            @error('no_telp') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Email</label>
                            <input type="email" name="email" class="form-control bg-light border-0 pradana-field"
                                   value="{{ old('email', $pengaturanInstansi->email) }}" readonly required>
                            @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if ($pengaturanInstansi->logo_path)
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary d-block">Logo Saat Ini</label>
                            <img src="{{ Storage::url($pengaturanInstansi->logo_path) }}" alt="Logo instansi"
                                 style="max-height: 80px;" class="border rounded p-2 bg-light">
                        </div>
                    @endif

                    <div class="mb-3 d-none" id="logoUploadWrap">
                        <label class="form-label small fw-bold text-secondary">
                            {{ $pengaturanInstansi->logo_path ? 'Ganti Logo (opsional, biarkan kosong kalau tidak ingin ganti)' : 'Upload Logo' }}
                        </label>
                        <input type="file" name="logo" class="form-control bg-light border-0" accept=".jpg,.jpeg,.png">
                        <div class="form-text">Format jpg/jpeg/png, maks 2MB.</div>
                        @error('logo') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-none gap-2" id="submitWrap">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="window.location.reload()">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Mode readonly-sampai-diklik-Edit, meniru pola handleEditHeader() di referensi lama.
    function pradanaToggleEditPengaturanInstansi() {
        document.querySelectorAll('.pradana-field').forEach(function (el) {
            el.readOnly = false;
        });
        document.getElementById('logoUploadWrap').classList.remove('d-none');
        document.getElementById('submitWrap').classList.remove('d-none');
        document.getElementById('submitWrap').classList.add('d-flex');
        document.getElementById('btnToggleEdit').classList.add('d-none');
    }
</script>
@endpush
